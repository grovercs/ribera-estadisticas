'use client'

import { useEffect, useMemo, useState } from 'react'
import { Loader2, X } from 'lucide-react'
import { createClient } from '@/lib/supabase/client'

export interface SalesDocumentKey {
  cod_venta: string
  tipo_venta: number
  cod_empresa: string
  cod_caja: string
  cod_cliente: string | null
  razon_social: string | null
}

interface SalesLine {
  linea: number
  cod_articulo: string | null
  descripcion: string | null
  cantidad: string | number | null
  precio: string | number | null
  precio_coste: string | number | null
  total_amount: string | number | null
}

interface SalesLinesDrawerProps {
  document: SalesDocumentKey | null
  onClose: () => void
}

const moneyFormatter = new Intl.NumberFormat('es-ES', {
  style: 'currency',
  currency: 'EUR',
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
})

const quantityFormatter = new Intl.NumberFormat('es-ES', {
  maximumFractionDigits: 3,
})

function toNumber(value: string | number | null): number {
  const numeric = typeof value === 'string' ? Number.parseFloat(value) : Number(value)
  return Number.isFinite(numeric) ? numeric : 0
}

export default function SalesLinesDrawer({ document, onClose }: SalesLinesDrawerProps) {
  const supabase = useMemo(() => createClient(), [])
  const [lines, setLines] = useState<SalesLine[]>([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!document) return

    const selectedDocument = document

    let cancelled = false

    async function loadLines() {
      setLoading(true)
      setError(null)
      setLines([])

      const { data, error: queryError } = await supabase
        .from('sales_lines')
        .select('linea, cod_articulo, descripcion, cantidad, precio, precio_coste, total_amount')
        .eq('cod_venta', selectedDocument.cod_venta)
        .eq('tipo_venta', selectedDocument.tipo_venta)
        .eq('cod_empresa', selectedDocument.cod_empresa)
        .eq('cod_caja', selectedDocument.cod_caja)
        .order('linea', { ascending: true })

      if (cancelled) return

      if (queryError) {
        setError('No se han podido cargar las líneas de la venta.')
      } else {
        setLines((data || []) as SalesLine[])
      }
      setLoading(false)
    }

    void loadLines()

    return () => {
      cancelled = true
    }
  }, [document, supabase])

  useEffect(() => {
    if (!document) return

    const closeOnEscape = (event: KeyboardEvent) => {
      if (event.key === 'Escape') onClose()
    }

    window.addEventListener('keydown', closeOnEscape)
    return () => window.removeEventListener('keydown', closeOnEscape)
  }, [document, onClose])

  if (!document) return null

  return (
    <>
      <button
        type="button"
        className="fixed inset-0 z-50 bg-black/40 backdrop-blur-sm"
        aria-label="Cerrar detalle de líneas"
        onClick={onClose}
      />

      <aside
        className="fixed inset-y-0 right-0 z-[60] flex w-full max-w-4xl flex-col border-l border-[#e1e2e6] bg-[#f8f9fc] shadow-2xl"
        aria-label={`Líneas de la venta ${document.cod_venta}`}
      >
        <header className="flex items-start justify-between gap-4 border-b border-[#e1e2e6] bg-white px-5 py-4">
          <div className="min-w-0">
            <p className="text-xs font-black uppercase tracking-wider text-[#206393]">Detalle de venta</p>
            <h2 className="mt-0.5 text-xl font-black tracking-tight text-[#191c1e]">Venta {document.cod_venta}</h2>
            <p className="mt-1 truncate text-sm font-medium text-[#747878]">
              {document.cod_cliente || 'Cliente no indicado'} · {document.razon_social || 'Sin razón social'}
            </p>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="rounded-lg p-2 text-[#747878] transition-colors hover:bg-[#f0f4f8] hover:text-[#191c1e]"
            aria-label="Cerrar detalle de venta"
          >
            <X className="h-5 w-5" aria-hidden="true" />
          </button>
        </header>

        <div className="flex-1 overflow-auto p-5">
          {loading && (
            <div className="flex items-center gap-2 py-8 text-sm font-semibold text-[#747878]">
              <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" /> Cargando líneas…
            </div>
          )}

          {error && <p className="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-700">{error}</p>}

          {!loading && !error && lines.length === 0 && (
            <p className="rounded-lg border border-[#e1e2e6] bg-white p-4 text-sm text-[#747878]">Esta venta no tiene líneas disponibles.</p>
          )}

          {!loading && !error && lines.length > 0 && (
            <div className="overflow-x-auto rounded-lg border border-[#e1e2e6] bg-white shadow-sm">
              <table className="w-full min-w-[760px] border-collapse text-sm tabular-nums">
                <thead className="border-b border-[#e1e2e6] bg-[#f0f4f8] text-left text-[11px] font-black uppercase tracking-wide text-[#747878]">
                  <tr>
                    <th className="px-3 py-2">Artículo</th>
                    <th className="px-3 py-2">Descripción</th>
                    <th className="px-3 py-2 text-right">Cantidad</th>
                    <th className="px-3 py-2 text-right">Precio</th>
                    <th className="px-3 py-2 text-right">Coste</th>
                    <th className="px-3 py-2 text-right">Total</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-[#f0f4f8]">
                  {lines.map((line) => (
                    <tr key={line.linea} className="hover:bg-[#f8f9fc]">
                      <td className="px-3 py-2 font-semibold text-[#191c1e]">{line.cod_articulo || '—'}</td>
                      <td className="px-3 py-2 text-[#5a5e60]">{line.descripcion || '—'}</td>
                      <td className="px-3 py-2 text-right">{quantityFormatter.format(toNumber(line.cantidad))}</td>
                      <td className="px-3 py-2 text-right">{moneyFormatter.format(toNumber(line.precio))}</td>
                      <td className="px-3 py-2 text-right">{moneyFormatter.format(toNumber(line.precio_coste))}</td>
                      <td className="px-3 py-2 text-right font-bold text-[#191c1e]">{moneyFormatter.format(toNumber(line.total_amount))}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </aside>
    </>
  )
}
