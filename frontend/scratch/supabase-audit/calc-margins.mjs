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

async function calcMargins(dateFilterSql, params = []) {
  const sql = `
    WITH line_costs AS (
      SELECT
        h.cod_almacen,
        h.cod_venta,
        h.tipo_venta,
        h.cod_empresa,
        h.cod_caja,
        h.total_amount AS venta,
        COALESCE(SUM(l.precio_coste * l.cantidad), 0) AS coste
      FROM sales_headers h
      LEFT JOIN sales_lines l
        ON l.cod_venta = h.cod_venta
        AND l.tipo_venta = h.tipo_venta
        AND l.cod_empresa = h.cod_empresa
        AND l.cod_caja = h.cod_caja
        AND l.precio_coste IS NOT NULL
      WHERE ${dateFilterSql}
        AND h.tipo_venta IN (2,4,5)
        AND h.anulada IS NOT TRUE
      GROUP BY h.cod_almacen, h.cod_venta, h.tipo_venta, h.cod_empresa, h.cod_caja, h.total_amount
    )
    SELECT
      cod_almacen,
      SUM(venta) AS venta,
      SUM(coste) AS coste,
      SUM(venta) - SUM(coste) AS margen,
      CASE WHEN SUM(venta) > 0 THEN ROUND(((SUM(venta) - SUM(coste)) / SUM(venta)) * 100, 2) ELSE 0 END AS margen_pct
    FROM line_costs
    GROUP BY cod_almacen
    ORDER BY cod_almacen
  `
  return client.query(sql, params)
}

async function run() {
  await client.connect()

  const mHoy = await calcMargins("h.fecha_venta::date = '2026-08-11'")
  console.log('Margenes HOY:', JSON.stringify(mHoy.rows, null, 2))

  const mYear = await calcMargins('EXTRACT(YEAR FROM h.fecha_venta) = 2026')
  console.log('Margenes AÑO 2026:', JSON.stringify(mYear.rows, null, 2))

  const mQ = await calcMargins(`
    h.fecha_venta::date >= date_trunc('month', CURRENT_DATE)::date
    AND h.fecha_venta::date <= date_trunc('month', CURRENT_DATE)::date + 13
  `)
  console.log('Margenes QUINCENA:', JSON.stringify(mQ.rows, null, 2))

  await client.end()
}

run().catch((e) => {
  console.error('FATAL', e)
  process.exit(1)
})
