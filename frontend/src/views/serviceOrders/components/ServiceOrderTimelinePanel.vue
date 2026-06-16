<script setup lang="ts">
import type { TimelineEvent } from '../../../api/serviceOrders'

defineProps<{
  timeline: TimelineEvent[]
  formatDateTime: (value: string | null) => string
}>()
</script>

<template>
  <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
    <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-stone-500">Linha do Tempo</h2>
    <div v-if="timeline.length === 0" class="text-sm text-stone-500">Nenhum evento registrado.</div>
    <div v-else class="relative space-y-4 pl-4">
      <div class="absolute left-0 top-0 bottom-0 w-px bg-white/10" />
      <div
        v-for="event in timeline"
        :key="event.id"
        class="relative"
      >
        <div class="absolute -left-4 top-1.5 size-2 rounded-full bg-amber-500/60" />
        <div class="ml-2">
          <p class="text-sm font-medium text-stone-200">{{ event.title }}</p>
          <p class="text-xs text-stone-500">{{ formatDateTime(event.occurred_at) }}</p>
        </div>
      </div>
    </div>
  </div>
</template>
