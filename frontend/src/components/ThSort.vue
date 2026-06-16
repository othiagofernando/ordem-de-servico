<script setup lang="ts">
defineProps<{ state: 'asc' | 'desc' | null }>()
defineEmits<{ click: [] }>()
</script>

<template>
  <th class="th-sort" @click="$emit('click')">
    <span class="th-sort-inner">
      <slot />
      <span class="th-sort-icon" :class="{ 'th-sort-active': state }">
        <!-- Up arrow -->
        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"
          class="th-arrow" :class="state === 'asc' ? 'th-arrow-on' : 'th-arrow-off'" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M8 12V4M4 8l4-4 4 4" />
        </svg>
        <!-- Down arrow -->
        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"
          class="th-arrow" :class="state === 'desc' ? 'th-arrow-on' : 'th-arrow-off'" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M8 4v8m-4-4l4 4 4-4" />
        </svg>
      </span>
    </span>
  </th>
</template>

<style scoped>
@reference "../style.css";

.th-sort {
  @apply px-5 py-3 text-left text-xs font-semibold uppercase tracking-widest select-none cursor-pointer;
  color: var(--c-text-muted);
  transition: color 0.15s;
  white-space: nowrap;
}

.th-sort:hover {
  color: var(--c-text);
}

.th-sort-inner {
  @apply flex items-center gap-1.5;
}

.th-sort-icon {
  @apply flex flex-col gap-px shrink-0;
}

.th-arrow {
  @apply h-2.5 w-2.5;
  transition: color 0.15s, opacity 0.15s;
}

.th-arrow-on {
  color: #fbbf24;
  opacity: 1;
}

.th-arrow-off {
  color: var(--c-text-dim);
  opacity: 0.5;
}

/* When active column: the non-active arrow is even fainter */
.th-sort-active .th-arrow-off {
  opacity: 0.25;
}
</style>
