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
    return { ok: true, rows: res.rows }
  } catch (e) {
    return { ok: false, error: e.message }
  }
}

async function run() {
  await client.connect()

  // Variantes de get_store_dashboard_sales
  const salesVariants = [
    [null, 'no'],
    [null, 'si'],
    [2026, 'no'],
    [2026, 'si'],
  ]
  for (const [year, anioAnt] of salesVariants) {
    const label = `get_store_dashboard_sales(${year ?? 'NULL'}, '${anioAnt}')`
    const r = await call('get_store_dashboard_sales', [year, anioAnt])
    console.log('\n===', label, '===')
    if (r.ok) {
      console.log(JSON.stringify(r.rows[0]?.get_store_dashboard_sales, null, 2))
    } else {
      console.log('ERROR:', r.error)
    }
  }

  // Otras RPCs con parámetros
  const others = [
    ['get_store_dashboard_albaranes', [2026]],
    ['get_store_dashboard_purchases_periods', [2026]],
    ['get_purchases_tax_summary', [2026]],
    ['get_receivables_summary', []],
    ['get_receivables_aging', []],
  ]
  for (const [fn, args] of others) {
    const r = await call(fn, args)
    console.log('\n===', fn, args.length ? `(${args.join(', ')})` : '()', '===')
    if (r.ok) {
      const key = Object.keys(r.rows[0])[0]
      console.log(JSON.stringify(r.rows[0][key], null, 2))
    } else {
      console.log('ERROR:', r.error)
    }
  }

  // Buscar funciones de ventas/facturas adicionales
  const extra = await client.query(`
    SELECT p.proname, pg_get_function_arguments(p.oid) AS args
    FROM pg_proc p
    JOIN pg_namespace n ON n.oid = p.pronamespace
    WHERE n.nspname = 'public'
      AND (
        p.proname LIKE '%invoice%' OR
        p.proname LIKE '%sale%' OR
        p.proname LIKE '%margin%' OR
        p.proname LIKE '%albaran%' OR
        p.proname LIKE '%venta%'
      )
    ORDER BY p.proname
  `)
  console.log('\n=== EXTRA FUNCTIONS ===')
  console.log(JSON.stringify(extra.rows, null, 2))

  await client.end()
}

run().catch(e => {
  console.error('FATAL', e)
  process.exit(1)
})
