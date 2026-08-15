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

async function call(fn, args = []) {
  const argList = args.map(a => typeof a === 'string' ? `'${a}'` : a === null ? 'NULL' : a).join(', ')
  const sql = `SELECT * FROM ${fn}(${argList})`
  try {
    const res = await client.query(sql)
    return { ok: true, rows: res.rows, columns: res.rows.length ? Object.keys(res.rows[0]) : [] }
  } catch (e) {
    return { ok: false, error: e.message }
  }
}

async function run() {
  await client.connect()

  // get_dashboard_sales_by_warehouse for current and previous month/year
  const periods = [
    [2026, 1, 2026, 8],   // year to date
    [2026, 7, 2026, 7],   // previous month? August current
    [2026, 8, 2026, 8],   // current month
    [2025, 1, 2025, 12],  // previous year
  ]
  for (const [yf, mf, yt, mt] of periods) {
    const r = await call('get_dashboard_sales_by_warehouse', [yf, mf, yt, mt])
    console.log(`\n=== get_dashboard_sales_by_warehouse(${yf},${mf},${yt},${mt}) ===`)
    if (r.ok) {
      console.log('columns:', r.columns)
      console.log('rows:', JSON.stringify(r.rows, null, 2))
    } else {
      console.log('ERROR:', r.error)
    }
  }

  const evo = await call('get_dashboard_sales_evolution', [2025, 1, 2026, 8])
  console.log('\n=== get_dashboard_sales_evolution(2025,1,2026,8) ===')
  if (evo.ok) {
    console.log('columns:', evo.columns)
    console.log('rows:', JSON.stringify(evo.rows, null, 2))
  } else {
    console.log('ERROR:', evo.error)
  }

  await client.end()
}

run().catch(e => {
  console.error('FATAL', e)
  process.exit(1)
})
