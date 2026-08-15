import pg from 'pg'
import { readFileSync } from 'fs'
import { fileURLToPath } from 'url'
import { dirname, join } from 'path'

const { Client } = pg
const rootDir = dirname(fileURLToPath(import.meta.url))
const env = readFileSync(join(rootDir, '..', 'frontend', '.env'), 'utf8')
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
  await client.query('SET statement_timeout = 300000')

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

  for (const port of ['1433', '49753']) {
    try {
      const r = await client.query(`
        SELECT * FROM dblink('hostaddr=${erpHost} dbname=${erpDb} user=${erpUser} password=${erpPass} port=${port}',
          'SELECT TOP 1 cod_venta, importe FROM hist_ventas_cabecera')
        AS t(cod_venta varchar, importe numeric)
      `)
      console.log(`Port ${port} OK:`, r.rows)
    } catch (e) {
      console.log(`Port ${port} error:`, e.message)
    }
  }
  await client.end()
}

run().catch((e) => {
  console.error('FATAL', e)
  process.exit(1)
})
