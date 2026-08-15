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
    SELECT column_name, data_type
    FROM information_schema.columns
    WHERE table_schema='public' AND table_name='sales_headers'
      AND column_name IN ('total_amount','base_imponible','importe','importe_impuestos','importe_bruto','impuestos','neto')
  `)
  console.log('sales_headers candidate columns:', JSON.stringify(r.rows, null, 2))

  const r2 = await client.query(`
    SELECT column_name, data_type
    FROM information_schema.columns
    WHERE table_schema='public' AND table_name='sales_lines'
      AND column_name IN ('precio','total_amount','precio_coste','cantidad','base_imponible','importe','descuento','impuesto')
  `)
  console.log('sales_lines candidate columns:', JSON.stringify(r2.rows, null, 2))

  const sample = await client.query(`
    SELECT h.cod_venta, h.tipo_venta, h.cod_empresa, h.cod_caja, h.total_amount,
           SUM(l.total_amount) AS lines_total_amount,
           SUM(l.precio * l.cantidad) AS lines_precio_x_cantidad,
           SUM(l.precio_coste * l.cantidad) AS lines_cost,
           h.fecha_venta
    FROM sales_headers h
    JOIN sales_lines l ON l.cod_venta = h.cod_venta
      AND l.tipo_venta = h.tipo_venta
      AND l.cod_empresa = h.cod_empresa
      AND l.cod_caja = h.cod_caja
    WHERE h.fecha_venta::date = '2026-08-11'
      AND h.tipo_venta IN (2,4,5)
      AND h.anulada IS NOT TRUE
    GROUP BY h.cod_venta, h.tipo_venta, h.cod_empresa, h.cod_caja, h.total_amount, h.fecha_venta
    LIMIT 5
  `)
  console.log('Sample header vs lines:', JSON.stringify(sample.rows, null, 2))

  await client.end()
}

run().catch((e) => {
  console.error('FATAL', e)
  process.exit(1)
})
