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
  const dates = await client.query(`
    SELECT fecha_venta::date AS d, COUNT(*) AS n, SUM(total_amount) AS total
    FROM sales_headers
    WHERE tipo_venta IN (2,4,5) AND anulada IS NOT TRUE
    GROUP BY fecha_venta::date
    ORDER BY d DESC
    LIMIT 5
  `)
  console.log('Last 5 sales days:', JSON.stringify(dates.rows, null, 2))

  const maxDate = await client.query(`
    SELECT MAX(fecha_venta::date) AS max_d, MAX(fecha_venta) AS max_ts
    FROM sales_headers WHERE tipo_venta IN (2,4,5) AND anulada IS NOT TRUE
  `)
  console.log('Max date:', JSON.stringify(maxDate.rows[0], null, 2))

  const daySales = await client.query(`
    SELECT cod_almacen, COUNT(*) AS n, SUM(total_amount) AS total
    FROM sales_headers
    WHERE fecha_venta::date = (SELECT MAX(fecha_venta::date) FROM sales_headers WHERE tipo_venta IN (2,4,5) AND anulada IS NOT TRUE)
      AND tipo_venta IN (2,4,5) AND anulada IS NOT TRUE
    GROUP BY cod_almacen
  `)
  console.log('Sales on max day:', JSON.stringify(daySales.rows, null, 2))

  await client.end()
}

run().catch((e) => {
  console.error('FATAL', e)
  process.exit(1)
})
