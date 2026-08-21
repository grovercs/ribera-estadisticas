import Link from 'next/link'
import { ArrowLeft } from 'lucide-react'
import { createClient } from '@/lib/supabase/server'
import PurchaseDrilldownTable, {
  type PurchaseHeaderRow,
  type PurchaseSortColumn,
} from '../../../components/PurchaseDrilldownTable'

export const dynamic = 'force-dynamic'

const PAGE_SIZE = 25
const SORT_COLUMNS: PurchaseSortColumn[] = [
  'cod_compra',
  'fecha_compra',
  'cod_almacen',
  'cod_proveedor',
  'razon_social',
  'importe',
]

interface PageProps {
  searchParams: Promise<{
    year?: string
    month?: string
    search?: string
    sort?: string
    dir?: string
    page?: string
  }>
}

function escapeSearchTerm(value: string): string {
  return value.replace(/[,%_().]/g, '\\$&')
}

function monthStart(year: number, month: number): string {
  return `${year}-${String(month).padStart(2, '0')}-01`
}

function nextMonthStart(year: number, month: number): string {
  const date = new Date(Date.UTC(year, month, 1))
  return date.toISOString().slice(0, 10)
}

function monthLabel(year: number, month: number): string {
  const label = new Intl.DateTimeFormat('es-ES', {
    month: 'long',
    year: 'numeric',
    timeZone: 'UTC',
  }).format(new Date(Date.UTC(year, month - 1, 1)))

  return label.charAt(0).toUpperCase() + label.slice(1)
}

export default async function PurchaseDeliveryNotesDetailPage({ searchParams }: PageProps) {
  const params = await searchParams
  const year = Number.parseInt(params.year || '', 10)
  const month = Number.parseInt(params.month || '', 10)
  const validPeriod = Number.isInteger(year) && year >= 2000 && year <= 2100
    && Number.isInteger(month) && month >= 1 && month <= 12

  if (!validPeriod) {
    return (
      <div className="mx-auto max-w-3xl rounded-xl border border-amber-200 bg-amber-50 p-6 text-[#191c1e] shadow-sm">
        <h1 className="text-xl font-black">No se ha indicado un mes de compras válido</h1>
        <p className="mt-2 text-sm text-[#5a5e60]">Vuelve al Cuadro de Dirección y abre el detalle desde el bloque de albaranes.</p>
        <Link href="/dashboard" className="mt-4 inline-flex items-center gap-2 rounded-lg bg-[#206393] px-4 py-2 text-sm font-bold text-white hover:bg-[#1a5078]">
          <ArrowLeft className="h-4 w-4" aria-hidden="true" /> Volver al Cuadro de Dirección
        </Link>
      </div>
    )
  }

  const search = (params.search || '').trim().slice(0, 100)
  const sort = SORT_COLUMNS.includes(params.sort as PurchaseSortColumn)
    ? (params.sort as PurchaseSortColumn)
    : 'cod_compra'
  const direction = params.dir === 'desc' ? 'desc' : 'asc'
  const requestedPage = Number.parseInt(params.page || '1', 10)
  const currentPage = Number.isFinite(requestedPage) && requestedPage > 0 ? requestedPage : 1
  const startDate = monthStart(year, month)
  const endDate = nextMonthStart(year, month)
  const periodLabel = monthLabel(year, month)
  const rangeStart = (currentPage - 1) * PAGE_SIZE
  const rangeEnd = rangeStart + PAGE_SIZE - 1

  const supabase = await createClient()
  let query = supabase
    .from('purchase_headers')
    .select('cod_compra, tipo_compra, cod_empresa, cod_proveedor, cod_almacen, nombre_comercial, razon_social, fecha_compra, importe', { count: 'exact' })
    .eq('tipo_compra', 2)
    .gte('fecha_compra', `${startDate}T00:00:00`)
    .lt('fecha_compra', `${endDate}T00:00:00`)
    .in('cod_almacen', [1, 2])

  if (search) {
    const term = escapeSearchTerm(search)
    const filters = [
      `razon_social.ilike.%${term}%`,
      `nombre_comercial.ilike.%${term}%`,
    ]
    if (/^\d+$/.test(search)) {
      filters.unshift(`cod_compra.eq.${search}`, `cod_proveedor.eq.${search}`)
    }
    query = query.or(filters.join(','))
  }

  const { data, count, error } = await query
    .order(sort, { ascending: direction === 'asc' })
    .order('cod_compra', { ascending: true })
    .order('tipo_compra', { ascending: true })
    .order('cod_empresa', { ascending: true })
    .order('cod_proveedor', { ascending: true })
    .range(rangeStart, rangeEnd)

  const rows = (data || []) as PurchaseHeaderRow[]

  return (
    <div className="mx-auto w-full max-w-[1600px] space-y-4 text-[#191c1e]">
      <header className="flex flex-col gap-3 border-b border-[#e1e2e6] pb-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <Link href="/dashboard" className="inline-flex items-center gap-1 text-sm font-bold text-[#206393] hover:underline">
            <ArrowLeft className="h-4 w-4" aria-hidden="true" /> Volver al Cuadro de Dirección
          </Link>
          <h1 className="mt-2 text-2xl font-black tracking-tight">Detalle de albaranes de compra</h1>
          <p className="mt-1 text-sm font-medium text-[#747878]">{periodLabel}</p>
        </div>
      </header>

      {error ? (
        <p className="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-700">No se han podido cargar los albaranes del periodo.</p>
      ) : (
        <PurchaseDrilldownTable
          rows={rows}
          totalCount={count || 0}
          currentPage={currentPage}
          pageSize={PAGE_SIZE}
          sort={sort}
          direction={direction}
          search={search}
          periodLabel={periodLabel}
        />
      )}
    </div>
  )
}
