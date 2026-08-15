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

async function run() {
  await client.connect()

  const r1 = await client.query(`
    SELECT h.cod_venta, h.total_amount AS header_total_with_tax,
           SUM(l.line_total) AS sum_line_total,
           SUM(l.precio * l.cantidad) AS sum_precio_x_cantidad,
           SUM(l.precio_coste * l.cantidad) AS sum_coste
    FROM sales_headers h
    JOIN (
      SELECT cod_venta, tipo_venta, cod_empresa, cod_caja, total_amount AS line_total, precio, cantidad, precio_coste
      FROM sales_lines
    ) l USING (cod_venta, tipo_venta, cod_empresa, cod_caja)
    WHERE h.fecha_venta::date = '2026-08-11' AND h.tipo_venta IN (2,4,5) AND h.anulada IS NOT TRUE
    GROUP BY h.cod_venta, h.total_amount
    HAVING ABS(h.total_amount - SUM(l.line_total)) > 0.01
    ORDER BY ABS(h.total_amount - SUM(l.line_total)) DESC
    LIMIT 10
  `)
  console.log('Discrepancias header vs lines.total_amount:', JSON.stringify(r1.rows, null, 2))

  const r2 = await client.query(`
    SELECT COUNT(*) AS total_docs,
           SUM(h.total_amount) AS header_total,
           SUM(l.line_total) AS sum_line_total,
           SUM(l.precio * l.cantidad) AS sum_precio_x_cantidad,
           SUM(l.precio_coste * l.cantidad) AS sum_coste
    FROM sales_headers h
    JOIN (
      SELECT cod_venta, tipo_venta, cod_empresa, cod_caja, total_amount AS line_total, precio, cantidad, precio_coste
      FROM sales_lines
    ) l USING (cod_venta, tipo_venta, cod_empresa, cod_caja)
    WHERE h.fecha_venta::date = '2026-08-11' AND h.tipo_venta IN (2,4,5) AND h.anulada IS NOT TRUE
  `)
  console.log('Totales día 2026-08-11:', JSON.stringify(r2.rows[0], null, 2))

  await client.end()
}

run().catch((e) => {
  console.error('FATAL', e)
  process.exit(1)
})
