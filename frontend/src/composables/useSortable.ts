import { computed, ref } from 'vue'

type Dir = 'asc' | 'desc' | null

function getByPath(obj: unknown, path: string): unknown {
  return path.split('.').reduce<unknown>((acc, k) => {
    if (acc === null || acc === undefined) return null
    return (acc as Record<string, unknown>)[k]
  }, obj)
}

function compareValues(a: unknown, b: unknown, dir: 'asc' | 'desc'): number {
  // Nulls always to the bottom
  if (a === null || a === undefined) return 1
  if (b === null || b === undefined) return -1

  let cmp = 0
  if (typeof a === 'string' && typeof b === 'string') {
    cmp = a.localeCompare(b, 'pt-BR', { sensitivity: 'base' })
  } else if (typeof a === 'number' && typeof b === 'number') {
    cmp = a - b
  } else {
    cmp = String(a).localeCompare(String(b), 'pt-BR', { sensitivity: 'base' })
  }

  return dir === 'asc' ? cmp : -cmp
}

export function useSortable<T>(
  data: { readonly value: T[] },
  getters?: Record<string, (item: T) => unknown>,
) {
  const sortKey = ref<string | null>(null)
  const sortDir = ref<Dir>(null)

  function toggleSort(key: string) {
    if (sortKey.value === key) {
      if (sortDir.value === null) {
        sortDir.value = 'asc'
      } else if (sortDir.value === 'asc') {
        sortDir.value = 'desc'
      } else {
        sortDir.value = null
        sortKey.value = null
      }
    } else {
      sortKey.value = key
      sortDir.value = 'asc'
    }
  }

  const sorted = computed((): T[] => {
    if (!sortKey.value || !sortDir.value) return data.value

    const key = sortKey.value
    const dir = sortDir.value
    const getter = getters?.[key] ?? ((item: T) => getByPath(item, key))

    return [...data.value].sort((a, b) => compareValues(getter(a), getter(b), dir))
  })

  function sortState(key: string): Dir {
    return sortKey.value === key ? sortDir.value : null
  }

  return { sorted, toggleSort, sortState }
}
