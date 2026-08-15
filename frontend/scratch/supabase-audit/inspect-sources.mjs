import pg from 'pg'
import { readFileSync } from 'fs'
import { fileURLToPath } from 'url'
import { dirname, join } from 'path'

const { Client } = pg

const rootDir = dirname(fileURLToPath(import.meta.url))
const envPath = join(rootDir, '..', '..', '..', '.env')
const env = readFileSync(envPath, 'utf8')

function envVar(name) {
  const m = env.match(new RegExp(`^${name}=(.+)$`, 'm'))
  return m ? m[1].trim() : null
}

const client = new Client({
  host: envVar('SUPABASE_DB_HOST'),
  port: Number(envVar('SUPABASE_DB_PORT')),
  database: envVar('SUPABASE_DB_DATABASE'),
  user: envVar('SUPABASE_DB_USERNAME'),
  password: envVar('SUPABASE_DB_PASSWORD'),
  ssl: { rejectUnauthorized: false },
})

const names = [
  'get_store_dashboard_sales',
  'get_store_dashboard_impagados',
  'get_store_dashboard_payables',
  'get_store_dashboard_purchases_periods',
  'get_store_dashboard_albaranes',
  'get_dashboard_sales_by_warehouse',
  'get_dashboard_sales_evolution',
  'get_receivables_summary',
]

async function run() {
  await client.connect()
  for (const name of names) {
    const res = await client.query(`SELECT pg_get_functiondef(p.oid) AS def FROM pg_proc p JOIN pg_namespace n ON n.oid = p.pronamespace WHERE n.nspname = 'public' AND p.proname = $1`, [name])
    console.log('\n===', name, '===')
    if (res.rows[0]) {
      console.log(res.rows[0].def)
    } else {
      console.log('NOT FOUND')
    }
  }
  await client.end()
}

run().catch(e => {
  console.error('FATAL', e)
  process.exit(1)
})
