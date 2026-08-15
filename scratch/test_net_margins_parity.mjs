import pg from 'pg'
import { readFileSync } from 'fs'
import { fileURLToPath } from 'url'
import { dirname, join } from 'path'

const { Client } = pg
const rootDir = dirname(fileURLToPath(import.meta.url))

// Lee variables de entorno desde .env en la raíz del frontend
const envPath = join(rootDir, '..', 'frontend', '.env')
const env = readFileSync(envPath, 'utf8')
const envVar = (n) =>
  env.match(new RegExp(`^${n}=(.+)$`, 'm'))?.[1]?.trim()

const client = new Client({
  host: envVar('SUPABASE_DB_HOST'),
  port: +envVar('SUPABASE_DB_PORT'),
  database: envVar('SUPABASE_DB_DATABASE'),
  user: envVar('SUPABASE_DB_USERNAME'),
  password: envVar('SUPABASE_DB_PASSWORD'),
  ssl: { rejectUnauthorized: false },
})

const fmtEur = (n) =>
  new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' }).format(n)

function logSection(t) {
  console.log('\n' + '='.repeat(70))
  console.log(t)
  console.log('='.repeat(70))
}

async function testRange(label, yf, mf, yt, mt) {
  const res = await client.query(
    'SELECT * FROM public.get_dashboard_net_margins($1, $2, $3, $4)',
    [yf, mf, yt, mt]
  )
  const row = res.rows[0] || {}
  console.log(`\n${label}`)
  console.log(`  Venta:    ${fmtEur(row.venta ?? 0)}`)
  console.log(`  Coste:    ${fmtEur(row.coste ?? 0)}`)
  console.log(`  Margen:   ${fmtEur(row.margen ?? 0)}`)
  console.log(`  Margen %: ${row.margen_porcentaje ?? 0} %`)
  return row
}

async function referenceYear2026() {
  const res = await client.query(
    "SELECT * FROM public.get_store_dashboard_margins('year')"
  )
  const data = res.rows[0]?.year_rows || []
  const total = data.reduce(
    (acc, r) => ({
      venta: acc.venta + (r.venta ?? 0),
      coste: acc.coste + (r.coste ?? 0),
    }),
    { venta: 0, coste: 0 }
  )
  total.margen = total.venta - total.coste
  total.margen_porcentaje =
    total.venta > 0 ? ((total.margen / total.venta) * 100).toFixed(2) : 0

  logSection('Referencia get_store_dashboard_margins(year) - 2026 total')
  console.log(`  Venta:    ${fmtEur(total.venta)}`)
  console.log(`  Coste:    ${fmtEur(total.coste)}`)
  console.log(`  Margen:   ${fmtEur(total.margen)}`)
  console.log(`  Margen %: ${total.margen_porcentaje} %`)
}

async function run() {
  await client.connect()

  await referenceYear2026()

  // A) 2026-01 → 2026-08
  await testRange('A) 2026-01 → 2026-08', 2026, 1, 2026, 8)

  // B) 2025-01 → 2025-08
  await testRange('B) 2025-01 → 2025-08', 2025, 1, 2025, 8)

  // C) 2025-11 → 2026-02 (cruce de año)
  await testRange('C) 2025-11 → 2026-02', 2025, 11, 2026, 2)

  await client.end()
}

run().catch((e) => {
  console.error('FATAL', e)
  process.exit(1)
})
