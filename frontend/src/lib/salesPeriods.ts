export interface DateRange {
  start: string
  end: string
}

function datePartsInMadrid(date: Date) {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Europe/Madrid',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(date)

  return Object.fromEntries(parts.filter((part) => part.type !== 'literal').map((part) => [part.type, part.value])) as {
    year: string
    month: string
    day: string
  }
}

function formatDate(year: number, month: number, day: number): string {
  return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`
}

export function getCurrentFortnightRange(referenceDate = new Date()): DateRange {
  const { year, month, day } = datePartsInMadrid(referenceDate)
  const numericYear = Number(year)
  const numericMonth = Number(month)
  const numericDay = Number(day)
  const lastDay = new Date(Date.UTC(numericYear, numericMonth, 0)).getUTCDate()

  return numericDay >= 15
    ? { start: formatDate(numericYear, numericMonth, 15), end: formatDate(numericYear, numericMonth, lastDay) }
    : { start: formatDate(numericYear, numericMonth, 1), end: formatDate(numericYear, numericMonth, 14) }
}
