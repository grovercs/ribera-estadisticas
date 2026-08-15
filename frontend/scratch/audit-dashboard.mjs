import { createClient } from '@supabase/supabase-js'
import { readFileSync } from 'fs'
import { fileURLToPath } from 'url'
import { dirname, join } from 'path'

const __dirname = dirname(fileURLToPath(import.meta.url))
const envPath = join(__dirname, '..', '.env.local')
const env = readFileSync(envPath, 'utf8')

const url = env.match(/NEXT_PUBLIC_SUPABASE_URL=(.+)/)?.[1]?.trim()
const key = env.match(/NEXT_PUBLIC_SUPABASE_PUBLISHABLE_KEY=(.+)/)?.[1]?.trim()

if (!url || !key) {
  console.error('Missing Supabase env vars')
  process.exit(1)
}

const supabase = createClient(url, key)

const now = new Date()
const todayStr = now.toISOString().slice(0, 10)
const currentYear = now.getFullYear()

function logSection(title) {
  console.log('\n' + '='.repeat(60))
  console.log(title)
  console.log('='.repeat(60))
}

function logResult(label, res) {
  console.log('\n--- ' + label + ' ---')
  console.log('error:', res.error ? { message: res.error.message, code: res.error.code, details: res.error.details } : null)
  console.log('count:', res.data?.length ?? 0)
  if (res.data && res.data.length > 0) {
    console.log('columns:', Object.keys(res.data[0]))
    console.log('sample:', JSON.stringify(res.data.slice(0, 3), null, 2))
  } else {
    console.log('data:', res.data)
  }
}

async function run() {
  logSection('AUDIT /dashboard data flow')
  console.log('todayStr:', todayStr)
  console.log('currentYear:', currentYear)

  // 1. sales_daily (used for Hoy/Ayer and margins Hoy)
  const r1 = await supabase
    .from('sales_daily')
    .select('sale_date, warehouse_code, total_sales, total_cost, total_orders')
    .lte('sale_date', todayStr)
    .order('sale_date', { ascending: false })
    .limit(20)
  logResult('sales_daily (last 20 days <= today)', r1)

  // 2. stats_sales_monthly (used for margins year)
  const r2 = await supabase
    .from('stats_sales_monthly')
    .select('warehouse_code, total_sales, total_cost, total_orders')
    .eq('year', currentYear)
  logResult('stats_sales_monthly (year=' + currentYear + ')', r2)

  // 3. RPCs used by page.tsx
  const rpcs = [
    'get_sales_invoices_summary',
    'get_receivables_summary',
    'get_purchase_albaranes_month',
    'get_purchase_invoices_summary',
    'get_pending_payments_summary',
  ]
  for (const rpc of rpcs) {
    const r = await supabase.rpc(rpc)
    logResult('RPC ' + rpc, r)
  }

  // 4. RPCs mentioned by user but maybe not used
  logSection('CHECK ADDITIONAL RPCs (may not exist)')
  const extraRpcs = [
    'get_store_dashboard_sales',
    'get_store_dashboard_invoices',
    'get_store_dashboard_impagados',
    'get_store_dashboard_margins',
    'get_store_dashboard_purchases_periods',
    'get_store_dashboard_payables',
    'get_purchases_tax_summary',
  ]
  for (const rpc of extraRpcs) {
    const r = await supabase.rpc(rpc)
    console.log('\n--- RPC ' + rpc + ' ---')
    console.log('error:', r.error ? { message: r.error.message, code: r.error.code } : null)
    console.log('count:', r.data?.length ?? 0)
    if (r.data && r.data.length > 0) {
      console.log('columns:', Object.keys(r.data[0]))
      console.log('sample:', JSON.stringify(r.data.slice(0, 2), null, 2))
    }
  }
}

run().catch(e => {
  console.error('FATAL', e)
  process.exit(1)
})
