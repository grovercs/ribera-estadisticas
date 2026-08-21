import Link from 'next/link'
import { ArrowLeft } from 'lucide-react'
import { createClient } from '@/lib/supabase/server'
import SalesDrilldownTable, { type SalesHeaderRow, type SalesSortColumn } from '../../components/SalesDrilldownTable'

export const dynamic = 'force-dynamic'

const PAGE_SIZE = 25
const SORT_COLUMNS: SalesSortColumn[] = [
  'cod_venta',
  'tipo_venta',
  'cod_almacen',
  'cod_cliente',
  'razon_social',
  'total_amount',
]

interface PageProps {
  searchParams: Promise<{
    period?: string
    date?: string
    search?: string
    sort?: string
    dir?: string
    page?: string
  }>
}

function isValidDate(value: string | undefined): value is string {
  if (!value || !/^\d{4}-\d{2}-\d{2}$/.test(value)) return false

  const [year, month, day] = value.split('-').map(Number)
  const date = new Date(Date.UTC(year, month - 1, day))
  return date.getUTCFullYear() === year && date.getUTCMonth() === month - 1 && date.getUTCDate() === day
}

function nextDay(value: string): string {
  const [year, month, day] = value.split('-').map(Number)
  const date = new Date(Date.UTC(year, month - 1, day + 1))
  return date.toISOString().slice(0, 10)
}

function escapeSearchTerm(value: string): string {
  return value.replace(/[,%_().]/g, '\\$&')
}

function formatDate(value: string): string {
  const [year, month, day] = value.split('-').map(Number)
  return new Date(year, month - 1, day, 12).toLocaleDateString('es-ES', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
}

export default async function SalesDetailPage({ searchParams }: PageProps) {
  const params = await searchParams
  const date = params.date
  const period = params.period
  const search = (params.search || '').trim().slice(0, 100)
  const sort = SORT_COLUMNS.includes(params.sort as SalesSortColumn)
    ? (params.sort as SalesSortColumn)
    : 'cod_venta'
  const direction = params.dir === 'desc' ? 'desc' : 'asc'
  const requestedPage = Number.parseInt(params.page || '1', 10)
  const currentPage = Number.isFinite(requestedPage) && requestedPage > 0 ? requestedPage : 1

  if (period !== 'today' || !isValidDate(date)) {
    return (
      <div className="mx-auto max-w-3xl rounded-xl border border-amber-200 bg-amber-50 p-6 text-[#191c1e] shadow-sm">
        <h1 className="text-xl font-black">No se ha indicado una fecha de ventas válida</h1>
        <p className="mt-2 text-sm text-[#5a5e60]">Vuelve al Cuadro de Dirección y abre el detalle desde la fila de hoy.</p>
        <Link href="/dashboard" className="mt-4 inline-flex items-center gap-2 rounded-lg bg-[#206393] px-4 py-2 text-sm font-bold text-white hover:bg-[#1a5078]">
          <ArrowLeft className="h-4 w-4" aria-hidden="true" /> Volver al Cuadro de Dirección
        </Link>
      </div>
    )
  }

  const supabase = await createClient()
  const from = `${date}T00:00:00`
  const to = `${nextDay(date)}T00:00:00`
  const rangeStart = (currentPage - 1) * PAGE_SIZE
  const rangeEnd = rangeStart + PAGE_SIZE - 1

  let query = supabase
    .from('sales_headers')
    .select('cod_venta, tipo_venta, cod_empresa, cod_caja, cod_almacen, cod_cliente, razon_social, total_amount', { count: 'exact' })
    .gte('fecha_venta', from)
    .lt('fecha_venta', to)
    .in('tipo_venta', [2, 4, 5])
    .not('anulada', 'is', true)
    .in('cod_almacen', ['1', '2'])

  if (search) {
    const term = escapeSearchTerm(search)
    query = query.or(`cod_venta.ilike.%${term}%,cod_cliente.ilike.%${term}%,razon_social.ilike.%${term}%`)
  }

  const { data, count, error } = await query
    .order(sort, { ascending: direction === 'asc' })
    .order('cod_venta', { ascending: true })
    .order('tipo_venta', { ascending: true })
    .order('cod_empresa', { ascending: true })
    .order('cod_caja', { ascending: true })
    .range(rangeStart, rangeEnd)

  const rows = (data || []) as SalesHeaderRow[]

  return (
    <div className="mx-auto w-full max-w-[1600px] space-y-4 text-[#191c1e]">
      <header className="flex flex-col gap-3 border-b border-[#e1e2e6] pb-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <Link href="/dashboard" className="inline-flex items-center gap-1 text-sm font-bold text-[#206393] hover:underline">
            <ArrowLeft className="h-4 w-4" aria-hidden="true" /> Volver al Cuadro de Dirección
          </Link>
          <h1 className="mt-2 text-2xl font-black tracking-tight">Detalle de ventas</h1>
          <p className="mt-1 text-sm font-medium text-[#747878]">Hoy: {formatDate(date)}</p>
        </div>
      </header>

      {error ? (
        <p className="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-700">No se han podido cargar las ventas del periodo.</p>
      ) : (
        <SalesDrilldownTable
          rows={rows}
          totalCount={count || 0}
          currentPage={currentPage}
          pageSize={PAGE_SIZE}
          sort={sort}
          direction={direction}
          search={search}
        />
      )}
    </div>
  )
}
