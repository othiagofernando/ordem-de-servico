export function documentMask(value: string | null | undefined) {
  const raw = String(value ?? '')
    .replace(/[^A-Za-z0-9]/g, '')
    .toUpperCase()
    .substring(0, 14)

  const len = raw.length
  const hasLetter = /[A-Z]/.test(raw)

  if (!hasLetter && len <= 11) {
    if (len <= 3) return raw
    if (len <= 6) return `${raw.slice(0, 3)}.${raw.slice(3)}`
    if (len <= 9) return `${raw.slice(0, 3)}.${raw.slice(3, 6)}.${raw.slice(6)}`
    return `${raw.slice(0, 3)}.${raw.slice(3, 6)}.${raw.slice(6, 9)}-${raw.slice(9)}`
  }

  if (len <= 2) return raw
  if (len <= 5) return `${raw.slice(0, 2)}.${raw.slice(2)}`
  if (len <= 8) return `${raw.slice(0, 2)}.${raw.slice(2, 5)}.${raw.slice(5)}`
  if (len <= 12) return `${raw.slice(0, 2)}.${raw.slice(2, 5)}.${raw.slice(5, 8)}/${raw.slice(8)}`
  return `${raw.slice(0, 2)}.${raw.slice(2, 5)}.${raw.slice(5, 8)}/${raw.slice(8, 12)}-${raw.slice(12)}`
}

export function cepMask(value: string | null | undefined) {
  const digits = String(value ?? '')
    .replace(/\D/g, '')
    .substring(0, 8)

  if (digits.length <= 5) return digits
  return `${digits.substring(0, 5)}-${digits.substring(5)}`
}

export function phoneMask(value: string | null | undefined) {
  const digits = String(value ?? '')
    .replace(/\D/g, '')
    .substring(0, 11)

  if (digits.length === 0) return ''
  if (digits.length <= 2) return `(${digits}`

  const ddd = digits.substring(0, 2)
  const rest = digits.substring(2)

  if (rest.charAt(0) === '9') {
    const p2 = rest.substring(1, 5)
    const p3 = rest.substring(5, 9)

    if (rest.length <= 1) return `(${ddd}) ${rest}`
    if (rest.length <= 5) return `(${ddd}) ${rest.charAt(0)} ${p2}`
    return `(${ddd}) ${rest.charAt(0)} ${p2} ${p3}`
  }

  const p1 = rest.substring(0, 4)
  const p2 = rest.substring(4, 8)

  if (rest.length <= 4) return `(${ddd}) ${p1}`
  return `(${ddd}) ${p1} ${p2}`
}

/** Remove all non-digit characters. Use before saving to backend. */
export function stripDigits(value: string | null | undefined): string {
  return String(value ?? '').replace(/\D/g, '')
}

/**
 * Converts a cents integer to a BRL decimal string suitable for a number input.
 * Example: 9900 → "99.00"
 */
export function centsToDecimalString(cents: number | null | undefined): string {
  if (cents === null || cents === undefined) return ''
  return (cents / 100).toFixed(2)
}

/**
 * Parses a user-typed monetary string (BRL format or plain decimal) to integer cents.
 * Handles: "99,90" → 9990, "1.057,90" → 105790, "99.90" → 9990
 * Returns null when input is blank or non-numeric.
 */
export function parseMoneyCents(value: string | null | undefined): number | null {
  const raw = String(value ?? '').trim()
  if (!raw) return null

  // Remove thousand separators (dot in pt-BR) then normalize decimal separator
  // Strategy: if string has both '.' and ',' → dot is thousands, comma is decimal
  //           if string has only ',' → comma is decimal
  //           if string has only '.' → dot is decimal (e.g. "99.90" from <input type="number">)
  let normalized: string
  const hasDot = raw.includes('.')
  const hasComma = raw.includes(',')

  if (hasDot && hasComma) {
    // "1.057,90" → "1057.90"
    normalized = raw.replace(/\./g, '').replace(',', '.')
  } else if (hasComma) {
    // "99,90" → "99.90"
    normalized = raw.replace(',', '.')
  } else {
    // "99.90" or "9900" (plain integer)
    normalized = raw
  }

  const num = parseFloat(normalized)
  if (isNaN(num)) return null
  return Math.round(num * 100)
}

/**
 * Formats cents as a BRL display string (e.g. 9990 → "R$ 99,90").
 */
export function fmtMoneyCents(cents: number | null | undefined, currency = 'BRL'): string {
  if (cents === null || cents === undefined) return '—'
  return (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency })
}

/**
 * ATM-style currency mask for text inputs.
 * Strips non-digits and interprets the whole digit string as cents.
 * Example: "15000" → "150,00", "9990" → "99,90", "" → ""
 * Use on @input to reformat as the user types.
 */
export function moneyInputMask(value: string | null | undefined): string {
  const digits = String(value ?? '').replace(/\D/g, '')
  if (!digits) return ''
  const cents = parseInt(digits, 10)
  if (!cents) return ''
  const reais = Math.floor(cents / 100)
  const centsPart = cents % 100
  return `${new Intl.NumberFormat('pt-BR').format(reais)},${String(centsPart).padStart(2, '0')}`
}
