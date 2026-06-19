<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useTenantRouting } from '../composables/useTenantRouting'
import { useCommandPalette } from '../composables/useCommandPalette'
import { search, adminSearch, type SearchResult } from '../api/search'
import type { RouteLocationRaw } from 'vue-router'

const auth = useAuthStore()
const router = useRouter()
const { tenantRoute } = useTenantRouting()
const { isOpen, close } = useCommandPalette()

const inputRef = ref<HTMLInputElement | null>(null)
const listRef = ref<HTMLElement | null>(null)
const query = ref('')
const results = ref<SearchResult[]>([])
const loading = ref(false)
const selectedIndex = ref(-1)

let debounceTimer: ReturnType<typeof setTimeout> | null = null

// ────────────────── Busca ──────────────────

async function doSearch(q: string) {
  if (q.length < 2) {
    results.value = []
    return
  }
  loading.value = true
  try {
    const fn = auth.isSuperAdmin ? adminSearch : search
    const res = await fn(q)
    results.value = res.data
    selectedIndex.value = res.data.length > 0 ? 0 : -1
  } catch {
    results.value = []
  } finally {
    loading.value = false
  }
}

watch(query, (q) => {
  selectedIndex.value = -1
  if (debounceTimer) clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => doSearch(q.trim()), 250)
})

// Foca o input quando abre
watch(isOpen, (open) => {
  if (open) {
    query.value = ''
    results.value = []
    selectedIndex.value = -1
    nextTick(() => inputRef.value?.focus())
  }
})

// ────────────────── Grupos ──────────────────

const TYPE_META: Record<string, { label: string; icon: string }> = {
  service_order: {
    label: 'Ordens de Serviço',
    icon: 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z',
  },
  customer: {
    label: 'Clientes',
    icon: 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z',
  },
  equipment: {
    label: 'Equipamentos',
    icon: 'M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z',
  },
  member: {
    label: 'Usuários',
    icon: 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z',
  },
  playbook: {
    label: 'Playbooks',
    icon: 'M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25',
  },
  company: {
    label: 'Empresas',
    icon: 'M3.75 6.75A2.25 2.25 0 0 1 6 4.5h3A2.25 2.25 0 0 1 11.25 6.75v12A2.25 2.25 0 0 1 9 21H6a2.25 2.25 0 0 1-2.25-2.25v-12ZM12.75 10.5A2.25 2.25 0 0 1 15 8.25h3A2.25 2.25 0 0 1 20.25 10.5v8.25A2.25 2.25 0 0 1 18 21h-3a2.25 2.25 0 0 1-2.25-2.25V10.5Z',
  },
  part: {
    label: 'Peças',
    icon: 'M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125',
  },
  plan: {
    label: 'Planos',
    icon: 'M3.75 6.75A2.25 2.25 0 0 1 6 4.5h12A2.25 2.25 0 0 1 20.25 6.75v10.5A2.25 2.25 0 0 1 18 19.5H6a2.25 2.25 0 0 1-2.25-2.25V6.75ZM7.5 9.75h4.5m-4.5 4.5h9',
  },
  admin_user: {
    label: 'Usuários Admin',
    icon: 'M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
  },
}

const STATUS_LABELS: Record<string, string> = {
  draft: 'Rascunho',
  open: 'Aberta',
  in_progress: 'Em execução',
  completed: 'Concluída',
  ready_for_pickup: 'Pronta p/ retirada',
  delivered: 'Entregue',
  cancelled: 'Cancelada',
  no_repair: 'Sem reparo',
}

const STATUS_COLORS: Record<string, string> = {
  draft: '#78716c',
  open: '#60a5fa',
  in_progress: '#818cf8',
  completed: '#34d399',
  ready_for_pickup: '#fbbf24',
  delivered: '#6ee7b7',
  cancelled: '#f87171',
  no_repair: '#9ca3af',
}

// Resultados agrupados por tipo
interface GroupedResults {
  type: string
  label: string
  icon: string
  items: (SearchResult & { flatIndex: number })[]
}

const grouped = computed<GroupedResults[]>(() => {
  const map = new Map<string, SearchResult[]>()
  for (const r of results.value) {
    if (!map.has(r.type)) map.set(r.type, [])
    map.get(r.type)!.push(r)
  }
  let flat = 0
  return [...map.entries()].map(([type, items]) => ({
    type,
    label: TYPE_META[type]?.label ?? type,
    icon: TYPE_META[type]?.icon ?? '',
    items: items.map((item) => ({ ...item, flatIndex: flat++ })),
  }))
})

const flatResults = computed(() =>
  grouped.value.flatMap((g) => g.items),
)

// ────────────────── Ações Rápidas ──────────────────

interface QuickAction {
  label: string
  sublabel: string
  icon: string
  action: () => void
}

const quickActions = computed<QuickAction[]>(() => {
  if (auth.isSuperAdmin) {
    return [
      {
        label: 'Nova Empresa',
        sublabel: 'Cadastrar uma nova assistência',
        icon: 'M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        action: () => navigate({ name: 'admin.companies.create' }),
      },
      {
        label: 'Novo Plano',
        sublabel: 'Criar um plano de assinatura',
        icon: 'M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        action: () => navigate({ name: 'admin.plans.create' }),
      },
      {
        label: 'Novo Playbook',
        sublabel: 'Criar guia de procedimento',
        icon: 'M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        action: () => navigate({ name: 'admin.playbooks.create' }),
      },
    ]
  }
  return [
    {
      label: 'Nova Ordem de Serviço',
      sublabel: 'Abrir uma nova OS',
      icon: 'M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
      action: () => navigate(tenantRoute('service-orders.create')),
    },
    {
      label: 'Novo Cliente',
      sublabel: 'Cadastrar um novo cliente',
      icon: 'M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
      action: () => navigate(tenantRoute('customers.create')),
    },
    {
      label: 'Novo Equipamento',
      sublabel: 'Cadastrar um equipamento',
      icon: 'M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
      action: () => navigate(tenantRoute('equipments.create')),
    },
  ]
})

// ────────────────── Navegação ──────────────────

function getRoute(result: SearchResult): RouteLocationRaw {
  if (auth.isSuperAdmin) {
    switch (result.type) {
      case 'company':     return { name: 'admin.company', params: { id: result.id } }
      case 'plan':        return { name: 'admin.plans' }
      case 'playbook':    return { name: 'admin.playbooks.edit', params: { id: result.id } }
      case 'admin_user':  return { name: 'admin.users' }
    }
  } else {
    switch (result.type) {
      case 'service_order': return tenantRoute('service-orders.detail', { params: { id: result.id } })
      case 'customer':      return tenantRoute('customers.detail', { params: { id: result.id } })
      case 'equipment':     return tenantRoute('equipments.detail', { params: { id: result.id } })
      case 'member':        return tenantRoute('members')
      case 'part':          return tenantRoute('parts.edit', { params: { id: result.id } })
      case 'playbook':      return tenantRoute('learning.detail', { params: { id: result.id } })
    }
  }
  return { name: auth.isSuperAdmin ? 'admin' : 'dashboard' }
}

function navigate(route: RouteLocationRaw) {
  close()
  router.push(route)
}

function selectResult(result: SearchResult) {
  navigate(getRoute(result))
}

// ────────────────── Teclado ──────────────────

function handleKeydown(e: KeyboardEvent) {
  const total = flatResults.value.length

  if (e.key === 'Escape') {
    close()
    return
  }

  if (e.key === 'ArrowDown') {
    e.preventDefault()
    selectedIndex.value = total === 0 ? -1 : (selectedIndex.value + 1) % total
    scrollSelected()
    return
  }

  if (e.key === 'ArrowUp') {
    e.preventDefault()
    selectedIndex.value = total === 0 ? -1 : (selectedIndex.value - 1 + total) % total
    scrollSelected()
    return
  }

  if (e.key === 'Enter') {
    if (selectedIndex.value >= 0 && selectedIndex.value < total) {
      selectResult(flatResults.value[selectedIndex.value])
    }
  }
}

function scrollSelected() {
  nextTick(() => {
    const el = listRef.value?.querySelector('[data-selected="true"]') as HTMLElement | null
    el?.scrollIntoView({ block: 'nearest' })
  })
}
</script>

<template>
  <Teleport to="body">
    <Transition name="palette-backdrop">
      <div
        v-if="isOpen"
        class="palette-backdrop"
        @click.self="close"
      >
        <Transition name="palette-modal" appear>
          <div
            v-if="isOpen"
            class="palette-modal"
            role="dialog"
            aria-modal="true"
            aria-label="Busca rápida"
            @keydown="handleKeydown"
          >
            <!-- Input -->
            <div class="palette-input-row">
              <svg class="palette-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
              </svg>
              <input
                ref="inputRef"
                v-model="query"
                type="text"
                class="palette-input"
                placeholder="Buscar em tudo..."
                autocomplete="off"
                spellcheck="false"
              />
              <!-- Spinner -->
              <svg
                v-if="loading"
                class="palette-spinner"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.5"
              >
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
              </svg>
              <kbd class="palette-esc-key" @click="close">Esc</kbd>
            </div>

            <!-- Resultados / Ações Rápidas -->
            <div ref="listRef" class="palette-results">

              <!-- Estado vazio com query -->
              <div
                v-if="query.length >= 2 && !loading && results.length === 0"
                class="palette-empty"
              >
                <svg class="mx-auto mb-2 h-8 w-8 opacity-30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <p>Nenhum resultado para <strong>"{{ query }}"</strong></p>
              </div>

              <!-- Grupos de resultados -->
              <template v-if="results.length > 0">
                <div v-for="group in grouped" :key="group.type">
                  <!-- Cabeçalho do grupo -->
                  <div class="palette-group-header">
                    <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" :d="group.icon" />
                    </svg>
                    {{ group.label }}
                  </div>

                  <!-- Itens do grupo -->
                  <button
                    v-for="item in group.items"
                    :key="`${item.type}-${item.id}`"
                    class="palette-item"
                    :class="{ 'palette-item-selected': item.flatIndex === selectedIndex }"
                    :data-selected="item.flatIndex === selectedIndex"
                    @click="selectResult(item)"
                    @mousemove="selectedIndex = item.flatIndex"
                  >
                    <svg class="palette-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" :d="group.icon" />
                    </svg>
                    <div class="min-w-0 flex-1">
                      <p class="palette-item-label">{{ item.label }}</p>
                      <p class="palette-item-sublabel">{{ item.sublabel }}</p>
                    </div>
                    <!-- Badge de status para OS -->
                    <span
                      v-if="item.type === 'service_order' && item.meta"
                      class="palette-status-badge"
                      :style="{ color: STATUS_COLORS[item.meta] ?? '#78716c' }"
                    >
                      {{ STATUS_LABELS[item.meta] ?? item.meta }}
                    </span>
                    <!-- Badge de status para playbooks no admin -->
                    <span
                      v-else-if="item.type === 'playbook' && item.meta === 'draft'"
                      class="palette-status-badge"
                      style="color: #78716c"
                    >
                      Rascunho
                    </span>
                    <svg class="palette-item-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                    </svg>
                  </button>
                </div>
              </template>

              <!-- Ações rápidas (sem query) -->
              <template v-if="query.length < 2">
                <div class="palette-group-header">
                  <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                  </svg>
                  Ações rápidas
                </div>
                <button
                  v-for="(action, i) in quickActions"
                  :key="i"
                  class="palette-item"
                  @click="action.action()"
                >
                  <svg class="palette-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="action.icon" />
                  </svg>
                  <div class="min-w-0 flex-1">
                    <p class="palette-item-label">{{ action.label }}</p>
                    <p class="palette-item-sublabel">{{ action.sublabel }}</p>
                  </div>
                  <svg class="palette-item-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                  </svg>
                </button>
              </template>
            </div>

            <!-- Rodapé com atalhos -->
            <div class="palette-footer">
              <span class="palette-hint"><kbd>↑↓</kbd> navegar</span>
              <span class="palette-hint"><kbd>Enter</kbd> abrir</span>
              <span class="palette-hint"><kbd>Esc</kbd> fechar</span>
              <span class="palette-hint ml-auto"><kbd>Ctrl</kbd><kbd>H</kbd> alternar</span>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
/* Backdrop */
.palette-backdrop {
  position: fixed;
  inset: 0;
  z-index: 200;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding-top: 12vh;
  background: rgba(0, 0, 0, 0.55);
  backdrop-filter: blur(4px);
}

/* Modal */
.palette-modal {
  width: 100%;
  max-width: 640px;
  margin: 0 1rem;
  border-radius: 1.25rem;
  border: 1px solid var(--c-border);
  background: var(--c-sidebar);
  box-shadow: 0 24px 80px rgba(0, 0, 0, 0.5);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  max-height: 70vh;
}

/* Input row */
.palette-input-row {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding: 0.875rem 1rem;
  border-bottom: 1px solid var(--c-border);
}

.palette-search-icon {
  width: 1.125rem;
  height: 1.125rem;
  flex-shrink: 0;
  color: var(--c-text-muted);
}

.palette-input {
  flex: 1;
  background: transparent;
  border: none;
  outline: none;
  font-size: 0.9375rem;
  color: var(--c-text);
  caret-color: #fbbf24;
}

.palette-input::placeholder {
  color: var(--c-text-dim);
}

.palette-spinner {
  width: 1rem;
  height: 1rem;
  flex-shrink: 0;
  color: var(--c-text-dim);
  animation: spin 0.8s linear infinite;
}

@keyframes spin { to { transform: rotate(360deg); } }

.palette-esc-key {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  padding: 0.125rem 0.4rem;
  border-radius: 0.375rem;
  border: 1px solid var(--c-border);
  font-size: 0.6875rem;
  font-family: inherit;
  color: var(--c-text-dim);
  cursor: pointer;
  background: var(--c-surface-hover);
  transition: border-color 0.1s;
}
.palette-esc-key:hover { border-color: var(--c-text-dim); }

/* Results list */
.palette-results {
  flex: 1;
  overflow-y: auto;
  padding: 0.5rem 0;
}

.palette-empty {
  padding: 2.5rem 1rem;
  text-align: center;
  font-size: 0.875rem;
  color: var(--c-text-dim);
}

/* Group header */
.palette-group-header {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.5rem 1rem 0.25rem;
  font-size: 0.6875rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--c-text-dim);
  margin-top: 0.25rem;
}

/* Result item */
.palette-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  width: 100%;
  padding: 0.5rem 1rem;
  text-align: left;
  border: none;
  background: transparent;
  cursor: pointer;
  transition: background 0.1s;
}

.palette-item:hover,
.palette-item-selected {
  background: var(--c-nav-hover);
}

.palette-item-selected .palette-item-label {
  color: #fbbf24;
}

.palette-item-icon {
  width: 1rem;
  height: 1rem;
  flex-shrink: 0;
  color: var(--c-text-dim);
}

.palette-item-selected .palette-item-icon {
  color: #fbbf24;
}

.palette-item-label {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--c-text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.palette-item-sublabel {
  font-size: 0.75rem;
  color: var(--c-text-dim);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.palette-status-badge {
  flex-shrink: 0;
  font-size: 0.6875rem;
  font-weight: 500;
}

.palette-item-arrow {
  width: 0.875rem;
  height: 0.875rem;
  flex-shrink: 0;
  color: var(--c-text-dim);
  opacity: 0;
  transition: opacity 0.1s;
}

.palette-item:hover .palette-item-arrow,
.palette-item-selected .palette-item-arrow {
  opacity: 1;
}

/* Footer */
.palette-footer {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 0.5rem 1rem;
  border-top: 1px solid var(--c-border);
}

.palette-hint {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.6875rem;
  color: var(--c-text-dim);
}

.palette-hint kbd {
  display: inline-flex;
  align-items: center;
  padding: 0.125rem 0.3rem;
  border-radius: 0.25rem;
  border: 1px solid var(--c-border);
  background: var(--c-surface-hover);
  font-family: inherit;
  font-size: 0.625rem;
  color: var(--c-text-dim);
}

/* Animações */
.palette-backdrop-enter-active,
.palette-backdrop-leave-active {
  transition: opacity 0.15s ease;
}
.palette-backdrop-enter-from,
.palette-backdrop-leave-to {
  opacity: 0;
}

.palette-modal-enter-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}
.palette-modal-leave-active {
  transition: opacity 0.1s ease, transform 0.1s ease;
}
.palette-modal-enter-from {
  opacity: 0;
  transform: scale(0.96) translateY(-8px);
}
.palette-modal-leave-to {
  opacity: 0;
  transform: scale(0.96) translateY(-4px);
}
</style>
