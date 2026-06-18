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
