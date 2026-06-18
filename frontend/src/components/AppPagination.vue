<script setup lang="ts">
import { computed } from 'vue'

export interface PaginationMeta {
  total: number
  per_page: number
  current_page: number
  last_page: number
  from: number
  to: number
}

const props = defineProps<{
  meta: PaginationMeta
  perPageOptions?: number[]
}>()

const emit = defineEmits<{
  'update:page': [page: number]
  'update:perPage': [perPage: number]
}>()

const perPageOptions = computed(() => props.perPageOptions ?? [15, 30, 50])

function goTo(page: number) {
  if (page < 1 || page > props.meta.last_page || page === props.meta.current_page) return
  emit('update:page', page)
}

function setPerPage(val: number) {
  emit('update:perPage', val)
}

// Compute visible page numbers with ellipsis
// e.g. [1, 2, '…', 5, 6, 7, '…', 10, 11]
const pages = computed((): (number | '…')[] => {
  const { current_page: cur, last_page: last } = props.meta
  if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1)

  const result: (number | '…')[] = []
  const near = new Set([1, 2, cur - 1, cur, cur + 1, last - 1, last].filter(p => p >= 1 && p <= last))
  let prev: number | null = null
  for (const p of Array.from(near).sort((a, b) => a - b)) {
    if (prev !== null && p - prev > 1) result.push('…')
    result.push(p)
    prev = p
  }
  return result
})
</script>

<template>
  <div v-if="meta.last_page > 1" class="pagination-bar">
    <!-- Info -->
    <span class="pg-info">
      {{ meta.from }}–{{ meta.to }} de {{ meta.total }}
    </span>

    <!-- Controls -->
    <div class="pg-controls">
      <!-- First -->
      <button class="pg-btn" :disabled="meta.current_page === 1" @click="goTo(1)" title="Primeira">
        <svg viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5"><path fill-rule="evenodd" d="M15.79 14.77a.75.75 0 0 1-1.06.02L9.45 10 14.73 5.21a.75.75 0 0 1 1.04 1.08L11.1 10l4.67 3.71a.75.75 0 0 1 .02 1.06Zm-6.5 0a.75.75 0 0 1-1.06.02L2.95 10 8.23 5.21a.75.75 0 0 1 1.04 1.08L4.6 10l4.67 3.71a.75.75 0 0 1 .02 1.06Z" clip-rule="evenodd"/></svg>
      </button>
      <!-- Prev -->
      <button class="pg-btn" :disabled="meta.current_page === 1" @click="goTo(meta.current_page - 1)" title="Anterior">
        <svg viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5"><path fill-rule="evenodd" d="M11.78 5.22a.75.75 0 0 1 0 1.06L8.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd"/></svg>
      </button>

      <!-- Page numbers -->
      <template v-for="(p, i) in pages" :key="i">
        <span v-if="p === '…'" class="pg-ellipsis">…</span>
        <button
          v-else
          :class="['pg-btn pg-num', { 'pg-num-active': p === meta.current_page }]"
          @click="goTo(p as number)"
        >{{ p }}</button>
      </template>

      <!-- Next -->
      <button class="pg-btn" :disabled="meta.current_page === meta.last_page" @click="goTo(meta.current_page + 1)" title="Próxima">
        <svg viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5"><path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 1 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
      </button>
      <!-- Last -->
      <button class="pg-btn" :disabled="meta.current_page === meta.last_page" @click="goTo(meta.last_page)" title="Última">
        <svg viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5"><path fill-rule="evenodd" d="M4.21 14.77a.75.75 0 0 0 1.06.02L10.55 10 5.27 5.21a.75.75 0 0 0-1.04 1.08L8.9 10l-4.67 3.71a.75.75 0 0 0-.02 1.06Zm6.5 0a.75.75 0 0 0 1.06.02L17.05 10 11.77 5.21a.75.75 0 0 0-1.04 1.08L15.4 10l-4.67 3.71a.75.75 0 0 0-.02 1.06Z" clip-rule="evenodd"/></svg>
      </button>
    </div>

    <!-- Per-page -->
    <div class="pg-per-page">
      <span class="pg-per-label">por página</span>
      <select class="pg-select" :value="meta.per_page" @change="setPerPage(+($event.target as HTMLSelectElement).value)">
        <option v-for="n in perPageOptions" :key="n" :value="n">{{ n }}</option>
      </select>
    </div>
  </div>
</template>

<style scoped>
@reference "../style.css";

.pagination-bar {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex-wrap: wrap;
  padding-top: 0.5rem;
}

.pg-info {
  font-size: 0.8125rem;
  color: var(--c-text-dim);
  min-width: 7rem;
}

.pg-controls {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.pg-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 2rem;
  height: 2rem;
  padding: 0 0.375rem;
  border-radius: 0.5rem;
  border: 1px solid var(--c-border);
  background: transparent;
  color: var(--c-text-muted);
  font-size: 0.8125rem;
  cursor: pointer;
  transition: background 0.1s, border-color 0.1s, color 0.1s;
}

.pg-btn:hover:not(:disabled) {
  border-color: var(--c-border-strong);
  color: var(--c-text);
  background: var(--c-nav-hover);
}

.pg-btn:disabled {
  opacity: 0.35;
  cursor: default;
}

.pg-num-active {
  background: rgba(251, 146, 60, 0.12) !important;
  border-color: rgba(251, 146, 60, 0.4) !important;
  color: #fbbf24 !important;
  font-weight: 600;
}

.pg-ellipsis {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.5rem;
  height: 2rem;
  color: var(--c-text-dim);
  font-size: 0.8125rem;
  pointer-events: none;
  user-select: none;
}

.pg-per-page {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-left: auto;
}

.pg-per-label {
  font-size: 0.8125rem;
  color: var(--c-text-dim);
}

.pg-select {
  border-radius: 0.5rem;
  border: 1px solid var(--c-border);
  background: var(--c-input-bg);
  color: var(--c-text);
  font-size: 0.8125rem;
  padding: 0.25rem 0.5rem;
  cursor: pointer;
  outline: none;
}

.pg-select:focus {
  border-color: rgba(251, 146, 60, 0.4);
}
</style>
