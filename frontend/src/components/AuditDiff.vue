<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  before: Record<string, unknown> | null | undefined
  after: Record<string, unknown> | null | undefined
}>()

type DiffLine = {
  key: string
  type: 'added' | 'removed' | 'changed' | 'unchanged'
  before: string | null
  after: string | null
}

function fmt(v: unknown): string {
  if (v === null || v === undefined) return 'null'
  if (typeof v === 'string') return v
  return JSON.stringify(v, null, 2)
}

const diffLines = computed((): DiffLine[] => {
  const b = props.before ?? {}
  const a = props.after ?? {}
  const allKeys = Array.from(new Set([...Object.keys(b), ...Object.keys(a)]))

  return allKeys.map((key) => {
    const hasB = key in b
    const hasA = key in a
    const bStr = hasB ? fmt(b[key]) : null
    const aStr = hasA ? fmt(a[key]) : null

    if (!hasB) return { key, type: 'added', before: null, after: aStr }
    if (!hasA) return { key, type: 'removed', before: bStr, after: null }
    if (bStr !== aStr) return { key, type: 'changed', before: bStr, after: aStr }
    return { key, type: 'unchanged', before: bStr, after: aStr }
  })
})

const hasChanges = computed(() =>
  diffLines.value.some((l) => l.type !== 'unchanged'),
)

const noData = computed(() =>
  !props.before && !props.after,
)
</script>

<template>
  <div v-if="noData" class="text-xs text-stone-500 italic">Sem dados registrados.</div>

  <div v-else-if="!hasChanges" class="text-xs text-stone-500 italic">Sem alterações de campos.</div>

  <div v-else class="space-y-1">
    <template v-for="line in diffLines" :key="line.key">
      <!-- Unchanged: mostrar de forma discreta -->
      <div v-if="line.type === 'unchanged'" class="flex gap-2 rounded px-2 py-0.5 text-xs text-stone-600">
        <span class="shrink-0 font-mono font-semibold">{{ line.key }}</span>
        <span class="font-mono">{{ line.before }}</span>
      </div>

      <!-- Removido -->
      <div v-else-if="line.type === 'removed'" class="rounded border border-red-500/20 bg-red-500/10 px-2 py-1 text-xs">
        <span class="font-mono font-semibold text-red-300">- {{ line.key }}</span>
        <span class="ml-2 font-mono text-red-400">{{ line.before }}</span>
      </div>

      <!-- Adicionado -->
      <div v-else-if="line.type === 'added'" class="rounded border border-emerald-500/20 bg-emerald-500/10 px-2 py-1 text-xs">
        <span class="font-mono font-semibold text-emerald-300">+ {{ line.key }}</span>
        <span class="ml-2 font-mono text-emerald-400">{{ line.after }}</span>
      </div>

      <!-- Alterado -->
      <div v-else-if="line.type === 'changed'" class="space-y-0.5">
        <div class="rounded border border-red-500/20 bg-red-500/10 px-2 py-1 text-xs">
          <span class="font-mono font-semibold text-red-300">- {{ line.key }}</span>
          <span class="ml-2 font-mono text-red-400">{{ line.before }}</span>
        </div>
        <div class="rounded border border-emerald-500/20 bg-emerald-500/10 px-2 py-1 text-xs">
          <span class="font-mono font-semibold text-emerald-300">+ {{ line.key }}</span>
          <span class="ml-2 font-mono text-emerald-400">{{ line.after }}</span>
        </div>
      </div>
    </template>
  </div>
</template>
