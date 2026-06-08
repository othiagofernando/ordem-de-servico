<script setup lang="ts">
import { computed, onMounted } from 'vue'

import { useSystemStore } from '../stores/system'

const system = useSystemStore()

const statusLabel = computed(() => {
  switch (system.healthState) {
    case 'loading':
      return 'Checking backend foundation'
    case 'ok':
      return 'Backend API healthy'
    case 'error':
      return 'Backend unavailable'
    default:
      return 'Health check not started'
  }
})

onMounted(() => {
  void system.checkHealth()
})
</script>

<template>
  <main class="min-h-screen px-6 py-10 text-stone-100 sm:px-10">
    <div class="mx-auto flex max-w-6xl flex-col gap-8">
      <section class="overflow-hidden rounded-[2rem] border border-white/10 bg-black/20 shadow-2xl shadow-black/30 backdrop-blur">
        <div class="grid gap-8 px-6 py-8 sm:px-10 lg:grid-cols-[1.5fr_0.9fr] lg:px-12 lg:py-12">
          <div class="space-y-6">
            <div class="inline-flex rounded-full border border-amber-400/30 bg-amber-300/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.3em] text-amber-200">
              Slice 01 · Foundation
            </div>

            <div class="space-y-4">
              <h1 class="max-w-3xl text-4xl font-semibold tracking-tight text-white sm:text-5xl">
                Bootable API surface and frontend shell for the service-order platform.
              </h1>
              <p class="max-w-2xl text-base leading-7 text-stone-300 sm:text-lg">
                This workspace proves the agreed baseline: versioned Laravel API, generated docs, queue-ready backend structure, and a Vue shell aligned to the product stack.
              </p>
            </div>

            <div class="flex flex-wrap gap-3">
              <button
                class="rounded-full bg-amber-500 px-5 py-3 text-sm font-semibold text-stone-950 transition hover:bg-amber-400"
                type="button"
                @click="system.checkHealth"
              >
                Recheck API health
              </button>

              <a
                :href="system.docsUrl"
                class="rounded-full border border-white/15 px-5 py-3 text-sm font-semibold text-white transition hover:border-amber-300 hover:text-amber-100"
                target="_blank"
                rel="noreferrer"
              >
                Open API docs
              </a>
            </div>
          </div>

          <aside class="rounded-[1.5rem] border border-white/10 bg-white/5 p-5">
            <div class="space-y-4">
              <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-stone-400">Runtime status</p>
                <p class="mt-2 text-2xl font-semibold text-white">{{ statusLabel }}</p>
              </div>

              <div class="rounded-2xl border border-white/10 bg-black/20 p-4">
                <p class="text-sm text-stone-400">GET /api/v1/health</p>
                <p class="mt-3 font-mono text-sm text-emerald-300">
                  {{ system.healthPayload ? JSON.stringify(system.healthPayload) : '{"status":"unreachable"}' }}
                </p>
              </div>

              <p class="text-sm text-stone-400">
                Last checked:
                <span class="text-stone-200">{{ system.lastCheckedAt || 'never' }}</span>
              </p>
            </div>
          </aside>
        </div>
      </section>

      <section class="grid gap-4 lg:grid-cols-3">
        <article class="rounded-[1.5rem] border border-white/10 bg-white/5 p-5">
          <p class="text-sm font-semibold uppercase tracking-[0.22em] text-stone-400">API contract</p>
          <p class="mt-3 text-lg font-medium text-white">Laravel 13 with `/api/v1` routing and executable Scribe docs.</p>
        </article>

        <article class="rounded-[1.5rem] border border-white/10 bg-white/5 p-5">
          <p class="text-sm font-semibold uppercase tracking-[0.22em] text-stone-400">Frontend shell</p>
          <p class="mt-3 text-lg font-medium text-white">Vue 3, TypeScript, Pinia, Router, and Tailwind wired for later slices.</p>
        </article>

        <article class="rounded-[1.5rem] border border-white/10 bg-white/5 p-5">
          <p class="text-sm font-semibold uppercase tracking-[0.22em] text-stone-400">Modular growth</p>
          <p class="mt-3 text-lg font-medium text-white">Domain directories are ready for tenant, auth, customer, and order slices.</p>
        </article>
      </section>
    </div>
  </main>
</template>
