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

async function salesByWarehouse(dateFilterSql, label) {
  const sql = `
    SELECT
      h.cod_almacen,
      COUNT(*) AS tickets,
      SUM(h.total_amount) AS importe_con_iva,
      SUM(doc.line_total_amount) AS importe_lineas_con_iva,
      SUM(doc.neto) AS importe_sin_iva,
      SUM(doc.coste) AS coste
    FROM sales_headers h
    LEFT JOIN LATERAL (
      SELECT
        SUM(l.total_amount) AS line_total_amount,
        SUM(l.precio * l.cantidad) AS neto,
        SUM(l.precio_coste * l.cantidad) AS coste
      FROM sales_lines l
      WHERE l.cod_venta = h.cod_venta
        AND l.tipo_venta = h.tipo_venta
        AND l.cod_empresa = h.cod_empresa
        AND l.cod_caja = h.cod_caja
        AND l.precio IS NOT NULL
        AND l.cantidad IS NOT NULL
    ) doc ON TRUE
    WHERE ${dateFilterSql}
      AND h.tipo_venta IN (2,4,5)
      AND h.anulada IS NOT TRUE
    GROUP BY h.cod_almacen
    ORDER BY h.cod_almacen
  `
  const res = await client.query(sql)
  console.log(`\n=== ${label} ===`)
  console.log(JSON.stringify(res.rows, null, 2))
}

async function run() {
  await client.connect()

  await salesByWarehouse("h.fecha_venta::date = '2026-08-11'", 'HOY (2026-08-11)')
  await salesByWarehouse("h.fecha_venta::date = '2026-08-10'", 'AYER (2026-08-10)')
  await salesByWarehouse("h.fecha_venta::date >= '2026-08-01' AND h.fecha_venta::date <= '2026-08-14'", 'QUINCENA ACTUAL (1-14 ago)')
  await salesByWarehouse("h.fecha_venta::date >= '2026-07-16' AND h.fecha_venta::date <= '2026-07-31'", 'QUINCENA ANTERIOR (16-31 jul)')
  await salesByWarehouse("h.fecha_venta::date >= '2026-07-01' AND h.fecha_venta::date <= '2026-07-15'", 'QUINCENA ANTERIOR2 (1-15 jul)')
  await salesByWarehouse('EXTRACT(YEAR FROM h.fecha_venta) = 2026', 'AÑO 2026')
  await salesByWarehouse('EXTRACT(YEAR FROM h.fecha_venta) = 2025', 'AÑO 2025')

  await client.end()
}

run().catch((e) => {
  console.error('FATAL', e)
  process.exit(1)
})
