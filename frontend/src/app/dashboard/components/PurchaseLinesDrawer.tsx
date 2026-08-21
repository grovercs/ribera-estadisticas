'use client'

import { useEffect, useMemo, useState } from 'react'
import { Loader2, X } from 'lucide-react'
import { createClient } from '@/lib/supabase/client'

export interface PurchaseDocumentKey {
  cod_compra: number
  tipo_compra: number
  cod_empresa: number
  cod_proveedor: number
  razon_social: string | null
  nombre_comercial: string | null
}

interface PurchaseLine {
  linea: number
  cod_articulo: string | null
  descripcion: string | null
  cantidad: string | number | null
  tarifa: string | number | null
  precio_coste: string | number | null
  dto1: string | number | null
  dto2: string | number | null
  dto3: string | number | null
  dto4: string | number | null
  importe: string | number | null
}

interface PurchaseLinesDrawerProps {
  document: PurchaseDocumentKey | null
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

export default function PurchaseLinesDrawer({ document, onClose }: PurchaseLinesDrawerProps) {
  const supabase = useMemo(() => createClient(), [])
  const [lines, setLines] = useState<PurchaseLine[]>([])
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
        .from('purchase_lines')
        .select('linea, cod_articulo, descripcion, cantidad, tarifa, precio_coste, dto1, dto2, dto3, dto4, importe')
        .eq('cod_compra', selectedDocument.cod_compra)
        .eq('tipo_compra', selectedDocument.tipo_compra)
        .eq('cod_empresa', selectedDocument.cod_empresa)
        .eq('cod_proveedor', selectedDocument.cod_proveedor)
        .order('linea', { ascending: true })

      if (cancelled) return

      if (queryError) {
        setError('No se han podido cargar las líneas del albarán.')
      } else {
        setLines((data || []) as PurchaseLine[])
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

  const supplierName = document.razon_social || document.nombre_comercial || 'Sin razón social'

  return (
    <>
      <button
        type="button"
        className="fixed inset-0 z-50 bg-black/40 backdrop-blur-sm"
        aria-label="Cerrar detalle de líneas"
        onClick={onClose}
      />

      <aside
        className="fixed inset-y-0 right-0 z-[60] flex w-full max-w-6xl flex-col border-l border-[#e1e2e6] bg-[#f8f9fc] shadow-2xl"
        aria-label={`Líneas del albarán ${document.cod_compra}`}
      >
        <header className="flex items-start justify-between gap-4 border-b border-[#e1e2e6] bg-white px-5 py-4">
          <div className="min-w-0">
            <p className="text-xs font-black uppercase tracking-wider text-[#206393]">Detalle de albarán de compra</p>
            <h2 className="mt-0.5 text-xl font-black tracking-tight text-[#191c1e]">Albarán {document.cod_compra}</h2>
            <p className="mt-1 truncate text-sm font-medium text-[#747878]">
              Proveedor {document.cod_proveedor} · {supplierName}
            </p>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="rounded-lg p-2 text-[#747878] transition-colors hover:bg-[#f0f4f8] hover:text-[#191c1e]"
            aria-label="Cerrar detalle de albarán"
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
            <p className="rounded-lg border border-[#e1e2e6] bg-white p-4 text-sm text-[#747878]">Este albarán no tiene líneas disponibles.</p>
          )}

          {!loading && !error && lines.length > 0 && (
            <div className="overflow-x-auto rounded-lg border border-[#e1e2e6] bg-white shadow-sm">
              <table className="w-full min-w-[980px] border-collapse text-sm tabular-nums">
                <thead className="border-b border-[#e1e2e6] bg-[#f0f4f8] text-left text-[11px] font-black uppercase tracking-wide text-[#747878]">
                  <tr>
                    <th className="px-3 py-2">Artículo</th>
                    <th className="px-3 py-2">Descripción</th>
                    <th className="px-3 py-2 text-right">Cantidad</th>
                    <th className="px-3 py-2 text-right">Tarifa / Precio</th>
                    <th className="px-3 py-2 text-right">Coste</th>
                    <th className="px-3 py-2 text-right">Dto. 1</th>
                    <th className="px-3 py-2 text-right">Dto. 2</th>
                    <th className="px-3 py-2 text-right">Total línea</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-[#f0f4f8]">
                  {lines.map((line) => (
                    <tr key={line.linea} className="hover:bg-[#f8f9fc]">
                      <td className="px-3 py-2 font-semibold text-[#191c1e]">{line.cod_articulo || '—'}</td>
                      <td className="px-3 py-2 text-[#5a5e60]">{line.descripcion || '—'}</td>
                      <td className="px-3 py-2 text-right">{quantityFormatter.format(toNumber(line.cantidad))}</td>
                      <td className="px-3 py-2 text-right">{moneyFormatter.format(toNumber(line.tarifa))}</td>
                      <td className="px-3 py-2 text-right">{moneyFormatter.format(toNumber(line.precio_coste))}</td>
                      <td className="px-3 py-2 text-right">{quantityFormatter.format(toNumber(line.dto1))} %</td>
                      <td className="px-3 py-2 text-right">{quantityFormatter.format(toNumber(line.dto2))} %</td>
                      <td className="px-3 py-2 text-right font-bold text-[#191c1e]">{moneyFormatter.format(toNumber(line.importe))}</td>
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
