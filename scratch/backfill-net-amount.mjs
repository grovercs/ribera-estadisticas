import pg from 'pg'
import { readFileSync } from 'fs'
import { fileURLToPath } from 'url'
import { dirname, join } from 'path'

const { Client } = pg
const rootDir = dirname(fileURLToPath(import.meta.url))
const env = readFileSync(join(rootDir, '..', 'frontend', '.env'), 'utf8')
const envVar = (n) => env.match(new RegExp(`^${n}=(.+)$`, 'm'))?.[1]?.trim()

// ERP connection credentials must be provided via environment variables.
const erpHost = envVar('ERP_DB_HOST')
const erpDb = envVar('ERP_DB_DATABASE')
const erpUser = envVar('ERP_DB_USERNAME')
const erpPass = envVar('ERP_DB_PASSWORD')

if (!erpHost || !erpDb || !erpUser || !erpPass) {
  console.error(
    'FATAL: configure ERP_DB_HOST, ERP_DB_DATABASE, ERP_DB_USERNAME y ERP_DB_PASSWORD en .env'
  )
  process.exit(1)
}

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

  const ext = await client.query(`SELECT 1 FROM pg_extension WHERE extname = 'dblink'`)
  if (ext.rows.length === 0) {
    console.log('Creando extensión dblink...')
    await client.query('CREATE EXTENSION IF NOT EXISTS dblink')
  }

  let connStr = null
  for (const port of ['49753', '1433']) {
    try {
      const test = await client.query(`
        SELECT 1 FROM dblink('hostaddr=${erpHost} dbname=${erpDb} user=${erpUser} password=${erpPass} port=${port}', 'SELECT 1 AS n')
        AS t(n int)
      `)
      if (test.rows.length > 0) {
        connStr = `hostaddr=${erpHost} dbname=${erpDb} user=${erpUser} password=${erpPass} port=${port}`
        console.log('Conexión ERP exitosa por puerto', port)
        break
      }
    } catch (e) {
      console.log('Puerto', port, 'falló:', e.message)
    }
  }

  if (!connStr) {
    console.error('No se pudo conectar al ERP desde PostgreSQL vía dblink')
    process.exit(1)
  }

  const update = await client.query(`
    WITH erp_src AS (
      SELECT cod_venta, tipo_venta, cod_empresa, cod_caja, importe
      FROM dblink('${connStr}',
        'SELECT cod_venta, tipo_venta, cod_empresa, cod_caja, importe FROM hist_ventas_cabecera WHERE tipo_venta IN (2,4,5)')
      AS t(cod_venta varchar, tipo_venta int, cod_empresa varchar, cod_caja varchar, importe numeric)
    )
    UPDATE sales_headers sh
    SET net_amount = erp_src.importe
    FROM erp_src
    WHERE sh.cod_venta = erp_src.cod_venta
      AND sh.tipo_venta = erp_src.tipo_venta
      AND sh.cod_empresa = erp_src.cod_empresa
      AND sh.cod_caja = erp_src.cod_caja
      AND sh.net_amount IS NULL
  `)
  console.log('UPDATE rows:', update.rowCount)

  const summary = await client.query(`
    SELECT
      COUNT(*) AS total,
      COUNT(net_amount) AS with_value,
      SUM(net_amount) AS sum_net,
      SUM(total_amount) AS sum_total
    FROM sales_headers
  `)
  console.log('Summary after backfill:', JSON.stringify(summary.rows[0], null, 2))

  await client.end()
}

run().catch((e) => {
  console.error('FATAL', e)
  process.exit(1)
})
