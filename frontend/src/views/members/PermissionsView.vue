<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { listMemberships, type Membership } from '../../api/memberships'

// ── Estado ────────────────────────────────────────────────────────────────────
const loading = ref(true)
const members = ref<Membership[]>([])
const error = ref('')
const search = ref('')
const focusedMember = ref<Membership | null>(null)

// ── Permissões e grupos ───────────────────────────────────────────────────────
const permissionGroups: { label: string; permissions: string[] }[] = [
  {
    label: 'Equipe',
    permissions: ['memberships.manage'],
  },
  {
    label: 'Configurações',
    permissions: ['company_settings.manage'],
  },
  {
    label: 'Clientes',
    permissions: ['customers.view_any', 'customers.manage'],
  },
  {
    label: 'Equipamentos',
    permissions: [
      'equipments.view_any',
      'equipments.manage',
      'equipments.override_serial_uniqueness',
    ],
  },
  {
    label: 'Ordens de Serviço',
    permissions: [
      'service_orders.view_any',
      'service_orders.view_assigned',
      'service_orders.manage',
      'service_orders.assign',
      'service_orders.diagnose',
      'service_orders.execute',
      'service_orders.finance',
      'service_orders.override_delivery_balance',
    ],
  },
]

const allPermissions = permissionGroups.flatMap((g) => g.permissions)

const permissionLabels: Record<string, string> = {
  'memberships.manage': 'Gerenciar equipe',
  'company_settings.manage': 'Gerenciar configurações',
  'customers.view_any': 'Visualizar clientes',
  'customers.manage': 'Gerenciar clientes',
  'equipments.view_any': 'Visualizar equipamentos',
  'equipments.manage': 'Gerenciar equipamentos',
  'equipments.override_serial_uniqueness': 'Ignorar unicidade de serial',
  'service_orders.view_any': 'Visualizar todas as O.S.',
  'service_orders.view_assigned': 'Visualizar O.S. atribuídas',
  'service_orders.manage': 'Gerenciar O.S.',
  'service_orders.assign': 'Atribuir técnico',
  'service_orders.diagnose': 'Diagnosticar',
  'service_orders.execute': 'Executar',
  'service_orders.finance': 'Financeiro',
  'service_orders.override_delivery_balance': 'Liberar entrega com saldo',
}

const roleLabels: Record<string, string> = {
  administrator: 'Administrador',
  manager: 'Gerente',
  attendant: 'Atendente',
  technician: 'Técnico',
  finance: 'Financeiro',
}

// ── Membros filtrados e ativos ────────────────────────────────────────────────
const activeMembers = computed(() =>
  members.value.filter((m) => m.status === 'active'),
)

const filteredMembers = computed(() => {
  const q = search.value.toLowerCase().trim()
  if (!q) return activeMembers.value
  return activeMembers.value.filter(
    (m) =>
      (m.user_name ?? '').toLowerCase().includes(q) ||
      (m.user_email ?? '').toLowerCase().includes(q),
  )
})

// ── Utilidades de permissão ───────────────────────────────────────────────────
function hasPerm(member: Membership, perm: string): boolean {
  return (member.resolved_permissions ?? []).includes(perm)
}

function isExtra(member: Membership, perm: string): boolean {
  return (member.permissions ?? []).includes(perm)
}

function isPreset(member: Membership, perm: string): boolean {
  return hasPerm(member, perm) && !isExtra(member, perm)
}

function permCount(member: Membership): number {
  return (member.resolved_permissions ?? []).length
}

// ── Por permissão: quem tem o quê ────────────────────────────────────────────
function membersWithPerm(perm: string): Membership[] {
  return activeMembers.value.filter((m) => hasPerm(m, perm))
}

// ── Tabs ──────────────────────────────────────────────────────────────────────
type Tab = 'matrix' | 'by-permission'
const activeTab = ref<Tab>('matrix')

// ── Carregar ──────────────────────────────────────────────────────────────────
onMounted(load)

async function load() {
  loading.value = true
  error.value = ''
  try {
    members.value = (await listMemberships(false)).data
  } catch {
    error.value = 'Não foi possível carregar as permissões.'
  } finally {
    loading.value = false
  }
}

function effectiveRole(member: Membership): string {
  if (member.company_role_name) return member.company_role_name
  return roleLabels[member.role ?? ''] ?? member.role ?? '—'
}
</script>

<template>
  <div class="space-y-6">
    <!-- Cabeçalho -->
    <div>
      <h1 class="text-2xl font-semibold" style="color: var(--c-text)">Mapa de Permissões</h1>
      <p class="mt-1 text-sm" style="color: var(--c-text-muted)">
        Visão consolidada de quem tem acesso a quê na sua empresa.
        <span class="inline-flex items-center gap-1.5 ml-2">
          <span class="legend-dot legend-preset"></span><span>Pelo perfil</span>
          <span class="legend-dot legend-extra ml-2"></span><span>Permissão extra</span>
        </span>
      </p>
    </div>

    <div v-if="error" class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">{{ error }}</div>

    <div v-if="loading" class="card py-16 text-center text-sm" style="color: var(--c-text-dim)">Carregando…</div>

    <template v-else>
      <!-- Tabs -->
      <div class="flex gap-1 rounded-xl border p-1" style="border-color: var(--c-border); background: var(--c-card); width: fit-content">
        <button
          :class="['tab-btn', { 'tab-btn-active': activeTab === 'matrix' }]"
          @click="activeTab = 'matrix'"
        >Matriz</button>
        <button
          :class="['tab-btn', { 'tab-btn-active': activeTab === 'by-permission' }]"
          @click="activeTab = 'by-permission'"
        >Por Permissão</button>
      </div>

      <!-- ── Tab Matriz ─────────────────────────────────────────────── -->
      <template v-if="activeTab === 'matrix'">
        <div class="flex items-center gap-3">
          <input
            v-model="search"
            type="search"
            placeholder="Filtrar membros…"
            class="search-input max-w-xs"
          />
          <span class="text-sm" style="color: var(--c-text-dim)">{{ filteredMembers.length }} membro{{ filteredMembers.length !== 1 ? 's' : '' }}</span>
        </div>

        <div v-if="filteredMembers.length === 0" class="card py-10 text-center text-sm" style="color: var(--c-text-muted)">
          Nenhum membro ativo encontrado.
        </div>

        <div v-else class="card overflow-x-auto p-0">
          <table class="matrix-table">
            <thead>
              <!-- Linha de grupos de permissão -->
              <tr class="matrix-group-row">
                <th class="matrix-member-th" rowspan="2">Membro</th>
                <th class="matrix-total-th" rowspan="2">Total</th>
                <th
                  v-for="group in permissionGroups"
                  :key="group.label"
                  :colspan="group.permissions.length"
                  class="matrix-group-th"
                >{{ group.label }}</th>
              </tr>
              <!-- Linha das permissões individuais -->
              <tr class="matrix-perm-row">
                <th
                  v-for="perm in allPermissions"
                  :key="perm"
                  class="matrix-perm-th"
                  :title="permissionLabels[perm]"
                >
                  <span class="matrix-perm-label">{{ permissionLabels[perm] ?? perm }}</span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="member in filteredMembers"
                :key="member.id"
                class="matrix-data-row"
                :class="{ 'matrix-row-focused': focusedMember?.id === member.id }"
                @mouseenter="focusedMember = member"
                @mouseleave="focusedMember = null"
              >
                <!-- Nome + perfil -->
                <td class="matrix-member-td">
                  <div class="flex items-center gap-2">
                    <div class="avatar-sm">{{ (member.user_name ?? '?')[0].toUpperCase() }}</div>
                    <div class="min-w-0">
                      <p class="text-xs font-semibold truncate" style="color: var(--c-text)">{{ member.user_name ?? '—' }}</p>
                      <p class="text-[10px] truncate" style="color: var(--c-text-dim)">{{ effectiveRole(member) }}</p>
                    </div>
                  </div>
                </td>
                <!-- Total de permissões -->
                <td class="matrix-total-td">
                  <span class="perm-count">{{ permCount(member) }}</span>
                </td>
                <!-- Célula por permissão -->
                <td
                  v-for="perm in allPermissions"
                  :key="perm"
                  class="matrix-cell"
                >
                  <span
                    v-if="isPreset(member, perm)"
                    class="perm-dot perm-dot-preset"
                    :title="`${permissionLabels[perm]} — pelo perfil`"
                  ></span>
                  <span
                    v-else-if="isExtra(member, perm)"
                    class="perm-dot perm-dot-extra"
                    :title="`${permissionLabels[perm]} — permissão extra`"
                  ></span>
                  <span v-else class="perm-dot perm-dot-none"></span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>

      <!-- ── Tab Por Permissão ─────────────────────────────────────── -->
      <template v-else>
        <div class="space-y-4">
          <div
            v-for="group in permissionGroups"
            :key="group.label"
            class="card space-y-3"
          >
            <h2 class="section-title">{{ group.label }}</h2>
            <div class="space-y-2">
              <div
                v-for="perm in group.permissions"
                :key="perm"
                class="perm-row-item"
              >
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium" style="color: var(--c-text)">{{ permissionLabels[perm] ?? perm }}</p>
                  <p class="text-xs font-mono mt-0.5" style="color: var(--c-text-dim)">{{ perm }}</p>
                </div>
                <div class="flex flex-wrap gap-1.5 justify-end">
                  <span
                    v-if="membersWithPerm(perm).length === 0"
                    class="text-xs"
                    style="color: var(--c-text-dim)"
                  >Nenhum membro</span>
                  <span
                    v-for="member in membersWithPerm(perm)"
                    :key="member.id"
                    :class="['member-chip', isExtra(member, perm) ? 'member-chip-extra' : 'member-chip-preset']"
                    :title="isExtra(member, perm) ? 'Permissão extra' : 'Pelo perfil'"
                  >
                    {{ member.user_name ?? member.user_email ?? `#${member.id}` }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>
    </template>
  </div>
</template>

<style scoped>
@reference "../../style.css";

/* Card base */
.card {
  @apply rounded-2xl border;
  background: var(--c-surface);
  border-color: var(--c-border);
}

/* Tabs */
.tab-btn {
  @apply rounded-lg px-4 py-1.5 text-sm font-medium transition;
  color: var(--c-text-muted);
}
.tab-btn:hover { color: var(--c-text); }
.tab-btn-active {
  @apply font-semibold;
  background: rgba(251, 146, 60, 0.1);
  color: #fbbf24;
}

/* Search */
.search-input {
  @apply w-full rounded-xl border px-3 py-2 text-sm outline-none transition;
  color: var(--c-text);
  background: var(--c-input-bg);
  border-color: var(--c-input-border);
}
.search-input:focus { border-color: rgba(251,146,60,.5); box-shadow: 0 0 0 2px rgba(251,146,60,.08); }
.search-input::placeholder { color: var(--c-text-dim); }

/* Matrix table */
.matrix-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.75rem;
  white-space: nowrap;
}

.matrix-group-row {
  background: var(--c-table-head);
  border-bottom: 1px solid var(--c-border);
}

.matrix-member-th {
  @apply sticky left-0 z-10 px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider;
  color: var(--c-text-muted);
  background: var(--c-table-head);
  min-width: 160px;
  border-right: 1px solid var(--c-border);
}

.matrix-total-th {
  @apply px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider;
  color: var(--c-text-muted);
  border-right: 1px solid var(--c-border);
}

.matrix-group-th {
  @apply px-3 py-2 text-center text-xs font-semibold uppercase tracking-wider;
  color: var(--c-text-muted);
  border-left: 1px solid var(--c-border);
  border-bottom: 1px solid var(--c-border);
}

.matrix-perm-row {
  background: var(--c-table-head);
  border-bottom: 1px solid var(--c-border);
}

.matrix-perm-th {
  @apply px-2 py-2 text-center;
  border-left: 1px solid var(--c-border);
  max-width: 80px;
  vertical-align: middle;
}

.matrix-perm-label {
  display: block;
  writing-mode: vertical-rl;
  transform: rotate(180deg);
  font-size: 10px;
  font-weight: 500;
  color: var(--c-text-muted);
  max-height: 100px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.matrix-data-row {
  border-top: 1px solid var(--c-border);
  transition: background 0.1s;
}
.matrix-data-row:hover { background: var(--c-nav-hover); }
.matrix-row-focused { background: var(--c-nav-hover); }

.matrix-member-td {
  @apply sticky left-0 z-10 px-4 py-3;
  background: inherit;
  border-right: 1px solid var(--c-border);
  min-width: 160px;
}

.matrix-total-td {
  @apply px-3 py-3 text-center;
  border-right: 1px solid var(--c-border);
}

.matrix-cell {
  @apply px-2 py-3 text-center;
  border-left: 1px solid var(--c-border);
}

/* Dots */
.perm-dot {
  display: inline-block;
  width: 10px;
  height: 10px;
  border-radius: 50%;
}
.perm-dot-preset {
  background: #f59e0b;
  box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.2);
}
.perm-dot-extra {
  background: #818cf8;
  box-shadow: 0 0 0 2px rgba(129, 140, 248, 0.2);
}
.perm-dot-none {
  background: transparent;
  border: 1px solid var(--c-border);
}

/* Legend dots */
.legend-dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: 50%;
}
.legend-preset { background: #f59e0b; }
.legend-extra  { background: #818cf8; }

/* Avatar pequeño */
.avatar-sm {
  @apply flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[10px] font-semibold;
  background: rgba(245, 158, 11, 0.15);
  color: #fbbf24;
}

/* Perm count badge */
.perm-count {
  @apply inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full px-1 text-[10px] font-semibold;
  background: rgba(245, 158, 11, 0.1);
  color: #fbbf24;
}

/* Section title */
.section-title {
  @apply text-xs font-semibold uppercase tracking-wider;
  color: var(--c-text-muted);
}

/* By-permission tab */
.perm-row-item {
  @apply flex flex-wrap items-start gap-3 rounded-xl border px-3 py-3;
  border-color: var(--c-border);
  background: var(--c-card);
}

.member-chip {
  @apply rounded-full px-2.5 py-0.5 text-xs font-medium;
}
.member-chip-preset {
  background: rgba(245, 158, 11, 0.1);
  color: #fbbf24;
  border: 1px solid rgba(245, 158, 11, 0.2);
}
.member-chip-extra {
  background: rgba(129, 140, 248, 0.1);
  color: #a5b4fc;
  border: 1px solid rgba(129, 140, 248, 0.2);
}
</style>
