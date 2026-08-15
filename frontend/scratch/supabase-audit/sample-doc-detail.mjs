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

  // Muestra de documentos completos: cabecera vs líneas
  const docs = await client.query(`
    SELECT h.cod_venta, h.total_amount AS header_with_tax,
           (SELECT SUM(l.total_amount) FROM sales_lines l WHERE l.cod_venta = h.cod_venta AND l.tipo_venta = h.tipo_venta AND l.cod_empresa = h.cod_empresa AND l.cod_caja = h.cod_caja) AS line_with_tax,
           (SELECT SUM(l.precio * l.cantidad) FROM sales_lines l WHERE l.cod_venta = h.cod_venta AND l.tipo_venta = h.tipo_venta AND l.cod_empresa = h.cod_empresa AND l.cod_caja = h.cod_caja) AS sum_precio_cantidad,
           (SELECT SUM(l.precio_coste * l.cantidad) FROM sales_lines l WHERE l.cod_venta = h.cod_venta AND l.tipo_venta = h.tipo_venta AND l.cod_empresa = h.cod_empresa AND l.cod_caja = h.cod_caja) AS sum_coste,
           h.cod_almacen
    FROM sales_headers h
    WHERE h.fecha_venta::date = '2026-08-11' AND h.tipo_venta IN (2,4,5) AND h.anulada IS NOT TRUE
    ORDER BY h.cod_venta
    LIMIT 10
  `)
  console.log('Document-level detail:', JSON.stringify(docs.rows, null, 2))

  // Totales día por campo
  const totals = await client.query(`
    SELECT
      SUM(h.total_amount) AS header_total_amount,
      SUM(doc.line_with_tax) AS line_total_amount,
      SUM(doc.sum_precio_cantidad) AS sum_precio_cantidad,
      SUM(doc.sum_coste) AS sum_coste
    FROM sales_headers h
    LEFT JOIN LATERAL (
      SELECT
        SUM(l.total_amount) AS line_with_tax,
        SUM(l.precio * l.cantidad) AS sum_precio_cantidad,
        SUM(l.precio_coste * l.cantidad) AS sum_coste
      FROM sales_lines l
      WHERE l.cod_venta = h.cod_venta AND l.tipo_venta = h.tipo_venta AND l.cod_empresa = h.cod_empresa AND l.cod_caja = h.cod_caja
    ) doc ON TRUE
    WHERE h.fecha_venta::date = '2026-08-11' AND h.tipo_venta IN (2,4,5) AND h.anulada IS NOT TRUE
  `)
  console.log('Day totals:', JSON.stringify(totals.rows[0], null, 2))

  await client.end()
}

run().catch((e) => {
  console.error('FATAL', e)
  process.exit(1)
})
