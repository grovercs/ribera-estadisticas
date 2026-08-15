import pg from 'pg'
import { readFileSync } from 'fs'
import { fileURLToPath } from 'url'
import { dirname, join } from 'path'

const { Client } = pg
const rootDir = dirname(fileURLToPath(import.meta.url))
const env = readFileSync(join(rootDir, '..', '..', '..', '.env'), 'utf8')
const envVar = (n) => env.match(new RegExp(`^${n}=(.+)$`, 'm'))?.[1]?.trim()

const client = new Client({
  host: envVar('SUPABASE_DB_HOST'),
  port: +envVar('SUPABASE_DB_PORT'),
  database: envVar('SUPABASE_DB_DATABASE'),
  user: envVar('SUPABASE_DB_USERNAME'),
  password: envVar('SUPABASE_DB_PASSWORD'),
  ssl: { rejectUnauthorized: false },
})

async function calcMargins(label, dateWhereSql) {
  const sql = `
    WITH line_costs AS (
      SELECT
        h.cod_almacen,
        SUM(l.precio * l.cantidad) AS venta_neta,
        COALESCE(SUM(l.precio_coste * l.cantidad), 0) AS coste
      FROM sales_headers h
      JOIN sales_lines l
        ON l.cod_venta = h.cod_venta
        AND l.tipo_venta = h.tipo_venta
        AND l.cod_empresa = h.cod_empresa
        AND l.cod_caja = h.cod_caja
        AND l.precio IS NOT NULL
        AND l.cantidad IS NOT NULL
      WHERE ${dateWhereSql}
        AND h.tipo_venta IN (2,4,5)
        AND h.anulada IS NOT TRUE
      GROUP BY h.cod_almacen, h.cod_venta, h.tipo_venta, h.cod_empresa, h.cod_caja
    )
    SELECT
      cod_almacen,
      SUM(venta_neta) AS venta,
      SUM(coste) AS coste,
      SUM(venta_neta) - SUM(coste) AS margen,
      CASE WHEN SUM(venta_neta) > 0
        THEN ROUND(((SUM(venta_neta) - SUM(coste)) / SUM(venta_neta)) * 100, 2)
        ELSE 0 END AS margen_pct
    FROM line_costs
    GROUP BY cod_almacen
    ORDER BY cod_almacen
  `
  const res = await client.query(sql)
  console.log(`\n=== ${label} ===`)
  console.log(JSON.stringify(res.rows, null, 2))
}

async function run() {
  await client.connect()

  await calcMargins('HOY (2026-08-11)', "h.fecha_venta::date = '2026-08-11'")
  await calcMargins('AÑO ACTUAL 2026', 'EXTRACT(YEAR FROM h.fecha_venta) = 2026')

  // Quincena actual según lógica de StoreDashboardController: 1-14 agosto (estamos a 13)
  await calcMargins('QUINCENA ACTUAL (1-14 ago 2026)',
    "h.fecha_venta::date >= '2026-08-01' AND h.fecha_venta::date <= '2026-08-14'")

  await client.end()
}

run().catch((e) => {
  console.error('FATAL', e)
  process.exit(1)
})
