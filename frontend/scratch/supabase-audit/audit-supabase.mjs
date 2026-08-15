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

function logSection(title) {
  console.log('\n' + '='.repeat(70))
  console.log(title)
  console.log('='.repeat(70))
}

function fmtRows(rows) {
  if (!rows || rows.length === 0) return '[]'
  return JSON.stringify(rows.slice(0, 3), null, 2)
}

async function query(sql, params = []) {
  const res = await client.query(sql, params)
  return res.rows
}

async function run() {
  await client.connect()

  logSection('CONEXIÓN')
  const conn = await query('SELECT current_database(), current_user, version()')
  console.log(conn[0])

  logSection('1. INVENTARIO DE FUNCIONES RELACIONADAS')
  const funcs = await query(`
    SELECT
      n.nspname AS schema,
      p.proname AS name,
      pg_get_function_arguments(p.oid) AS args,
      pg_get_function_result(p.oid) AS returns,
      CASE WHEN p.prosecdef THEN 'DEFINER' ELSE 'INVOKER' END AS security,
      pg_get_userbyid(p.proowner) AS owner,
      p.provolatile,
      p.prokind
    FROM pg_proc p
    JOIN pg_namespace n ON n.oid = p.pronamespace
    WHERE n.nspname = 'public'
      AND (
        p.proname LIKE 'get_store_dashboard_%'
        OR p.proname LIKE 'get_receivables_%'
        OR p.proname LIKE 'get_purchases_%'
        OR p.proname LIKE 'get_purchase_%'
        OR p.proname LIKE 'get_pending_%'
        OR p.proname LIKE 'get_sales_%'
      )
    ORDER BY p.proname
  `)
  for (const f of funcs) {
    console.log('\n---', f.name, '---')
    console.log('  args:', f.args)
    console.log('  returns:', f.returns)
    console.log('  security:', f.security)
    console.log('  owner:', f.owner)
    console.log('  volatile:', f.provolatile)

    // Permisos EXECUTE
    const perms = await query(`
      SELECT grantee, privilege_type
      FROM information_schema.role_routine_grants
      WHERE routine_schema = 'public'
        AND routine_name = $1
      ORDER BY grantee
    `, [f.name])
    console.log('  grants:', perms.map(p => `${p.grantee}:${p.privilege_type}`).join(', ') || 'none')
  }

  logSection('2. INVENTARIO DE TABLAS UTILIZADAS')
  const tables = [
    'sales_headers',
    'sales_lines',
    'stats_sales_monthly',
    'stats_warehouses',
    'receivables',
    'vendor_payables',
    'purchases_albaranes_summary',
    'purchases_invoices_daily_summary',
    'purchases_tax_summary',
  ]
  for (const t of tables) {
    const exists = await query(
      `SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = $1`,
      [t]
    )
    console.log('\n---', t, exists.length ? 'EXISTS' : 'MISSING', '---')
    if (exists.length) {
      const cols = await query(
        `SELECT column_name, data_type, is_nullable
         FROM information_schema.columns
         WHERE table_schema = 'public' AND table_name = $1
         ORDER BY ordinal_position`,
        [t]
      )
      console.log('  columns:', cols.map(c => `${c.column_name}:${c.data_type}${c.is_nullable === 'YES' ? '?' : ''}`).join(', '))
      const sample = await query(`SELECT * FROM ${t} LIMIT 2`)
      console.log('  sample:', fmtRows(sample))
    }
  }

  logSection('3. PRUEBAS FUNCIONALES DE RPCs')
  const testFuncs = funcs
    .filter(f =>
      f.name.startsWith('get_store_dashboard_') ||
      f.name.startsWith('get_receivables_') ||
      f.name.startsWith('get_purchases_') ||
      f.name.startsWith('get_purchase_') ||
      f.name.startsWith('get_pending_') ||
      f.name.startsWith('get_sales_')
    )
    .map(f => f.name)

  for (const fn of testFuncs) {
    try {
      const rows = await query(`SELECT * FROM ${fn}() LIMIT 3`)
      console.log('\n---', fn, '() ---')
      console.log('  rows:', rows.length)
      console.log('  columns:', rows.length ? Object.keys(rows[0]) : 'n/a')
      console.log('  sample:', fmtRows(rows))
    } catch (e) {
      console.log('\n---', fn, '() ERROR ---')
      console.log('  message:', e.message)
    }
  }

  await client.end()
}

run().catch(e => {
  console.error('FATAL', e)
  process.exit(1)
})
