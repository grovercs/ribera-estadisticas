/**
 * Formateadores normalizados para Ribera Estadísticas (formato español es-ES).
 * - Separador de miles: punto (.) forzado mediante useGrouping: 'always' (incluso en 4 dígitos)
 * - Separador decimal: coma (,) con 2 decimales
 * - Moneda: sufijo ' €'
 * - Contadores / Operaciones: números enteros sin decimales
 * - Porcentajes: coma decimal y sufijo ' %'
 */

const moneyFormatter = new Intl.NumberFormat('es-ES', {
  useGrouping: 'always',
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
})

const countFormatter = new Intl.NumberFormat('es-ES', {
  useGrouping: false,
  maximumFractionDigits: 0,
})

const pctFormatter = new Intl.NumberFormat('es-ES', {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
})

export const fmtMoney = (value: number | string | null | undefined): string => {
  const n = typeof value === 'string' ? parseFloat(value.replace(/\s/g, '').replace(',', '.')) : Number(value)
  return moneyFormatter.format(Number.isFinite(n) ? n : 0)
}

export const fmtEur = (value: number | string | null | undefined): string => {
  return `${fmtMoney(value)} €`
}

export const fmtCount = (value: number | string | null | undefined): string => {
  const n = typeof value === 'string' ? parseInt(value.replace(/\s/g, ''), 10) : Number(value)
  return countFormatter.format(Number.isFinite(n) ? Math.trunc(n) : 0)
}

export const fmtPct = (value: number | string | null | undefined): string => {
  const n = typeof value === 'string' ? parseFloat(value.replace(/\s/g, '').replace(',', '.')) : Number(value)
  return `${pctFormatter.format(Number.isFinite(n) ? n : 0)} %`
}
