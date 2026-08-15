import pg from 'pg'
import { readFileSync } from 'fs'
import { fileURLToPath } from 'url'
import { dirname, join } from 'path'

const { Client } = pg
const rootDir = dirname(fileURLToPath(import.meta.url))
const env = readFileSync(join(rootDir, '..', '..', '..', '.env'), 'utf8')
const envVar = (n) =>
  env.match(new RegExp(`^${n}=(.+)$`, 'm'))?.[1]?.trim()

const client = new Client({
  host: envVar('SUPABASE_DB_HOST'),
  port: +envVar('SUPABASE_DB_PORT'),
  database: envVar('SUPABASE_DB_DATABASE'),
  user: envVar('SUPABASE_DB_USERNAME'),
  password: envVar('SUPABASE_DB_PASSWORD'),
  ssl: { rejectUnauthorized: false },
})

function logSection(t) {
  console.log('\n' + '='.repeat(70))
  console.log(t)
  console.log('='.repeat(70))
}

async function run() {
  await client.connect()

  logSection('1. get_store_dashboard_sales: corrigiendo timestamp/date')
  const salesFix = await client.query(`
    SELECT json_build_object(
      'ultimo_dia', v_ultimo_dia,
      'penultimo_dia', v_penultimo_dia,
      'hoy', (
        SELECT json_agg(json_build_object('cod_almacen', cod_almacen, 'tickets', tickets, 'importe', importe_impuestos))
        FROM (
          SELECT cod_almacen, COUNT(cod_venta) as tickets, SUM(total_amount) as importe_impuestos
          FROM sales_headers
          WHERE fecha_venta::date = v_ultimo_dia
            AND tipo_venta IN (2,4,5) AND anulada IS NOT TRUE
          GROUP BY cod_almacen
        ) t
      ),
      'ayer', (
        SELECT json_agg(json_build_object('cod_almacen', cod_almacen, 'tickets', tickets, 'importe', importe_impuestos))
        FROM (
          SELECT cod_almacen, COUNT(cod_venta) as tickets, SUM(total_amount) as importe_impuestos
          FROM sales_headers
          WHERE fecha_venta::date = v_penultimo_dia
            AND tipo_venta IN (2,4,5) AND anulada IS NOT TRUE
          GROUP BY cod_almacen
        ) t
      ),
      'quincena', (
        SELECT json_agg(json_build_object('cod_almacen', cod_almacen, 'tickets', tickets, 'importe', importe_impuestos))
        FROM (
          SELECT cod_almacen, COUNT(cod_venta) as tickets, SUM(total_amount) as importe_impuestos
          FROM sales_headers
          WHERE fecha_venta::date >= v_q_start AND fecha_venta::date <= v_q_end
            AND tipo_venta IN (2,4,5) AND anulada IS NOT TRUE
          GROUP BY cod_almacen
        ) t
      ),
      'anteriores', (
        SELECT json_agg(json_build_object('cod_almacen', cod_almacen, 'tickets', tickets, 'importe', importe_impuestos))
        FROM (
          SELECT cod_almacen, COUNT(cod_venta) as tickets, SUM(total_amount) as importe_impuestos
          FROM sales_headers
          WHERE fecha_venta::date >= '2000-01-01' AND fecha_venta::date < v_q_start
            AND tipo_venta IN (2,4,5) AND anulada IS NOT TRUE
          GROUP BY cod_almacen
        ) t
      )
    ) AS result
    FROM (
      SELECT
        (SELECT MAX(fecha_venta::date) FROM sales_headers WHERE tipo_venta IN (2,4,5) AND anulada IS NOT TRUE) AS v_ultimo_dia,
        (SELECT MAX(fecha_venta::date) FROM sales_headers WHERE tipo_venta IN (2,4,5) AND anulada IS NOT TRUE AND fecha_venta::date < (SELECT MAX(fecha_venta::date) FROM sales_headers WHERE tipo_venta IN (2,4,5) AND anulada IS NOT TRUE)) AS v_penultimo_dia,
        '2026-08-01'::date AS v_q_start,
        '2026-08-14'::date AS v_q_end
    ) x
  `)
  console.log(JSON.stringify(salesFix.rows[0]?.result, null, 2))

  logSection('2. get_store_dashboard_quincena_anterior')
  const qAnt = await client.query(`
    SELECT
      h.cod_almacen,
      COUNT(*) AS tickets,
      SUM(h.total_amount) AS importe_con_iva,
      SUM(doc.neto) AS importe_sin_iva,
      SUM(doc.coste) AS coste
    FROM sales_headers h
    LEFT JOIN LATERAL (
      SELECT
        SUM(l.precio * l.cantidad) AS neto,
        SUM(l.precio_coste * l.cantidad) AS coste
      FROM sales_lines l
      WHERE l.cod_venta = h.cod_venta AND l.tipo_venta = h.tipo_venta
        AND l.cod_empresa = h.cod_empresa AND l.cod_caja = h.cod_caja
        AND l.precio IS NOT NULL AND l.cantidad IS NOT NULL
    ) doc ON TRUE
    WHERE h.fecha_venta::date >= '2026-07-16' AND h.fecha_venta::date <= '2026-07-31'
      AND h.tipo_venta IN (2,4,5) AND h.anulada IS NOT TRUE
    GROUP BY h.cod_almacen
    ORDER BY h.cod_almacen
  `)
  console.log(JSON.stringify(qAnt.rows, null, 2))

  logSection('3. get_store_dashboard_margins_hoy')
  const mHoy = await client.query(`
    SELECT
      h.cod_almacen,
      SUM(doc.neto) AS venta,
      SUM(doc.coste) AS coste,
      SUM(doc.neto) - SUM(doc.coste) AS margen,
      CASE WHEN SUM(doc.neto) > 0 THEN ROUND(((SUM(doc.neto) - SUM(doc.coste)) / SUM(doc.neto)) * 100, 2) ELSE 0 END AS margen_pct
    FROM sales_headers h
    LEFT JOIN LATERAL (
      SELECT
        SUM(l.precio * l.cantidad) AS neto,
        SUM(l.precio_coste * l.cantidad) AS coste
      FROM sales_lines l
      WHERE l.cod_venta = h.cod_venta AND l.tipo_venta = h.tipo_venta
        AND l.cod_empresa = h.cod_empresa AND l.cod_caja = h.cod_caja
        AND l.precio IS NOT NULL AND l.cantidad IS NOT NULL
    ) doc ON TRUE
    WHERE h.fecha_venta::date = (SELECT MAX(fecha_venta::date) FROM sales_headers WHERE tipo_venta IN (2,4,5) AND anulada IS NOT TRUE)
      AND h.tipo_venta IN (2,4,5) AND h.anulada IS NOT TRUE
    GROUP BY h.cod_almacen
    ORDER BY h.cod_almacen
  `)
  console.log(JSON.stringify(mHoy.rows, null, 2))

  logSection('4. get_store_dashboard_margins_year')
  const mYear = await client.query(`
    SELECT
      h.cod_almacen,
      SUM(doc.neto) AS venta,
      SUM(doc.coste) AS coste,
      SUM(doc.neto) - SUM(doc.coste) AS margen,
      CASE WHEN SUM(doc.neto) > 0 THEN ROUND(((SUM(doc.neto) - SUM(doc.coste)) / SUM(doc.neto)) * 100, 2) ELSE 0 END AS margen_pct
    FROM sales_headers h
    LEFT JOIN LATERAL (
      SELECT
        SUM(l.precio * l.cantidad) AS neto,
        SUM(l.precio_coste * l.cantidad) AS coste
      FROM sales_lines l
      WHERE l.cod_venta = h.cod_venta AND l.tipo_venta = h.tipo_venta
        AND l.cod_empresa = h.cod_empresa AND l.cod_caja = h.cod_caja
        AND l.precio IS NOT NULL AND l.cantidad IS NOT NULL
    ) doc ON TRUE
    WHERE EXTRACT(YEAR FROM h.fecha_venta) = 2026
      AND h.tipo_venta IN (2,4,5) AND h.anulada IS NOT TRUE
    GROUP BY h.cod_almacen
    ORDER BY h.cod_almacen
  `)
  console.log(JSON.stringify(mYear.rows, null, 2))

  logSection('5. get_store_dashboard_payables_with_count')
  const payables = await client.query(`
    SELECT
      COALESCE(SUM(importe_pendiente), 0) AS total,
      COUNT(*) AS total_ops,
      periodo,
      SUM(importe_pendiente) AS importe,
      COUNT(*) AS ops
    FROM vendor_payables
    GROUP BY periodo
    ORDER BY periodo
  `)
  console.log(JSON.stringify(payables.rows, null, 2))

  await client.end()
}

run().catch((e) => {
  console.error('FATAL', e)
  process.exit(1)
})
