export function formatPrice(value: number | string): string {
  const parsed = Number(value)
  if (!Number.isFinite(parsed)) {
    return '0.00'
  }

  return parsed.toFixed(2)
}
