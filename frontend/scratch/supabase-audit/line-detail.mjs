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
  const r = await client.query(`
    SELECT h.cod_venta, h.tipo_venta, h.total_amount, l.linea, l.precio, l.cantidad, l.precio_coste, l.total_amount AS line_total
    FROM sales_headers h
    JOIN sales_lines l ON l.cod_venta = h.cod_venta AND l.tipo_venta = h.tipo_venta AND l.cod_empresa = h.cod_empresa AND l.cod_caja = h.cod_caja
    WHERE h.fecha_venta::date = '2026-08-11' AND h.tipo_venta IN (2,4,5) AND h.anulada IS NOT TRUE
    ORDER BY h.cod_venta, l.linea
    LIMIT 20
  `)
  console.log('Line detail sample:', JSON.stringify(r.rows, null, 2))
  await client.end()
}

run().catch((e) => {
  console.error('FATAL', e)
  process.exit(1)
})
