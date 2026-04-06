const dateFormatter = new Intl.DateTimeFormat('en-GB', {
  day: '2-digit',
  month: '2-digit',
  year: 'numeric',
})

const timeFormatter = new Intl.DateTimeFormat('en-GB', {
  hour: '2-digit',
  minute: '2-digit',
  hour12: false,
})

const dateTimeFormatter = new Intl.DateTimeFormat('en-GB', {
  day: '2-digit',
  month: '2-digit',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
  hour12: false,
})

const monthYearFormatter = new Intl.DateTimeFormat('en-GB', {
  month: 'long',
  year: 'numeric',
})

function isValidDate(value: Date): boolean {
  return !Number.isNaN(value.getTime())
}

export function parseDateTime(value: string): Date {
  const isoLike = value.includes('T') ? value : value.replace(' ', 'T')
  return new Date(isoLike)
}

export function formatDate(value: string): string {
  const date = parseDateTime(value)
  if (!isValidDate(date)) {
    return value.slice(0, 10)
  }

  return dateFormatter.format(date)
}

export function formatTime(value: string): string {
  const date = parseDateTime(value)
  if (!isValidDate(date)) {
    return value.slice(11, 16)
  }

  return timeFormatter.format(date)
}

export function formatDateTime(value: string): string {
  const date = parseDateTime(value)
  if (!isValidDate(date)) {
    return value
  }

  return dateTimeFormatter.format(date)
}

export function formatDateTimeFromDate(value: Date): string {
  if (!isValidDate(value)) {
    return 'Invalid date'
  }

  return dateTimeFormatter.format(value)
}

export function toInputDateTime(value: string): string {
  const parsed = parseDateTime(value)
  if (isValidDate(parsed)) {
    const local = new Date(parsed.getTime() - parsed.getTimezoneOffset() * 60000)
    return local.toISOString().slice(0, 16)
  }

  const fallback = value.trim().replace(' ', 'T')
  return fallback.length >= 16 ? fallback.slice(0, 16) : ''
}

export function dateKey(value: string): string {
  return value.slice(0, 10)
}

export function isIsoDate(value: string): boolean {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
    return false
  }

  const year = Number(value.slice(0, 4))
  const month = Number(value.slice(5, 7))
  const day = Number(value.slice(8, 10))

  if (!Number.isInteger(year) || !Number.isInteger(month) || !Number.isInteger(day)) {
    return false
  }

  const check = new Date(year, month - 1, day)
  return (
    check.getFullYear() === year &&
    check.getMonth() === month - 1 &&
    check.getDate() === day
  )
}

export function isPastDateTime(value: string): boolean {
  const date = parseDateTime(value)
  if (!isValidDate(date)) {
    return false
  }

  return date.getTime() < Date.now()
}

export function isPastOrNowDateTime(value: string): boolean {
  const date = parseDateTime(value)
  if (!isValidDate(date)) {
    return false
  }

  return date.getTime() <= Date.now()
}

export function hoursUntil(value: string): number {
  const date = parseDateTime(value)
  if (!isValidDate(date)) {
    return 0
  }

  return (date.getTime() - Date.now()) / 3600000
}

export function formatIsoDate(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

export function formatMonthYear(value: Date): string {
  if (!isValidDate(value)) {
    return ''
  }

  return monthYearFormatter.format(value)
}
