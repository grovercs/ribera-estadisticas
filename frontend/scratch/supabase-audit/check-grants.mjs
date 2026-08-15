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

const names = [
  'get_dashboard_sales_by_warehouse',
  'get_dashboard_sales_evolution',
  'get_store_dashboard_sales',
  'get_store_dashboard_impagados',
  'get_store_dashboard_payables',
  'get_store_dashboard_purchases_periods',
  'get_store_dashboard_albaranes',
  'get_purchases_tax_summary',
  'get_receivables_summary',
]

async function run() {
  await client.connect()
  for (const n of names) {
    const res = await client.query(
      `SELECT grantee, privilege_type FROM information_schema.role_routine_grants WHERE routine_schema='public' AND routine_name=$1 ORDER BY grantee`,
      [n]
    )
    console.log(n + ':', res.rows.map((r) => `${r.grantee}:${r.privilege_type}`).join(', '))
  }
  await client.end()
}

run().catch((e) => {
  console.error('FATAL', e)
  process.exit(1)
})
