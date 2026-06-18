<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { ApiError } from '../../api/client'
import {
  createAdminPlan,
  deleteAdminPlan,
  getAdminPlan,
  updateAdminPlan,
  type AdminPlanUpsertPayload,
} from '../../api/admin'
import { moneyInputMask, parseMoneyCents } from '../../utils/masks'

const route = useRoute()
const router = useRouter()
const isEdit = computed(() => Boolean(route.params.id))
const loading = ref(false)
const saving = ref(false)
const deleting = ref(false)
const error = ref('')
const activeCompaniesCount = ref(0)
const showDeleteConfirm = ref(false)

const form = reactive({
  slug: '',
  name: '',
  max_users: '',
  max_service_orders_per_month: '',
  max_attachments_storage_mb: '',
  reference_price_cents: '',
})

onMounted(async () => {
  if (!isEdit.value) return

  loading.value = true
  error.value = ''

  try {
    const response = await getAdminPlan(Number(route.params.id))
    const plan = response.data

    form.slug = plan.slug
    form.name = plan.name
    form.max_users = plan.limits.max_users === null ? '' : String(plan.limits.max_users)
    form.max_service_orders_per_month =
      plan.limits.max_service_orders_per_month === null ? '' : String(plan.limits.max_service_orders_per_month)
    form.max_attachments_storage_mb =
      plan.limits.max_attachments_storage_mb === null ? '' : String(plan.limits.max_attachments_storage_mb)
    form.reference_price_cents = moneyInputMask(String(plan.reference_price_cents))
    activeCompaniesCount.value = plan.active_companies_count
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Erro ao carregar plano.'
  } finally {
    loading.value = false
  }
})

function toNullableInteger(value: string) {
  const normalized = value.trim()
  if (!normalized) return null
  return Number(normalized)
}

async function confirmDelete() {
  deleting.value = true
  error.value = ''
  try {
    await deleteAdminPlan(Number(route.params.id))
    await router.push({ name: 'admin.plans' })
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Erro ao excluir plano.'
    showDeleteConfirm.value = false
  } finally {
    deleting.value = false
  }
}

async function submit() {
  saving.value = true
  error.value = ''

  try {
    const payload: AdminPlanUpsertPayload = {
      slug: form.slug.trim(),
      name: form.name.trim(),
      max_users: toNullableInteger(form.max_users),
      max_service_orders_per_month: toNullableInteger(form.max_service_orders_per_month),
      max_attachments_storage_mb: toNullableInteger(form.max_attachments_storage_mb),
      reference_price_cents: parseMoneyCents(form.reference_price_cents),
    }

    if (isEdit.value) {
      await updateAdminPlan(Number(route.params.id), payload)
    } else {
      await createAdminPlan(payload)
    }

    await router.push({ name: 'admin.plans' })
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Erro ao salvar plano.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center gap-4">
      <RouterLink :to="{ name: 'admin.plans' }" class="text-sm text-stone-500 hover:text-stone-300">← Planos</RouterLink>
    </div>

    <div>
      <h1 class="text-2xl font-semibold text-white">{{ isEdit ? 'Editar Plano' : 'Novo Plano' }}</h1>
      <p class="mt-1 text-sm text-stone-400">
        {{ isEdit ? 'Atualize limites e preço de referência do plano.' : 'Cadastre um novo plano global para novas assinaturas.' }}
      </p>
    </div>

    <div v-if="error" class="rounded-2xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-300">
      {{ error }}
    </div>

    <div
      v-if="isEdit && activeCompaniesCount > 0"
      class="rounded-2xl border border-amber-500/20 bg-amber-500/10 px-5 py-4 text-sm text-amber-200"
    >
      <p class="font-semibold text-amber-300">Atenção: plano com empresas ativas</p>
      <p class="mt-1">
        Este plano possui <strong>{{ activeCompaniesCount }} empresa{{ activeCompaniesCount === 1 ? '' : 's' }} ativa{{ activeCompaniesCount === 1 ? '' : 's' }}</strong>.
        Alterações nos limites (usuários, OS/mês, armazenamento) têm efeito imediato sobre essas empresas.
      </p>
    </div>

    <div v-if="loading" class="py-16 text-center text-stone-500">Carregando plano…</div>

    <form v-else class="space-y-6" @submit.prevent="submit">
      <section class="rounded-3xl border border-white/10 bg-white/5 p-6">
        <h2 class="text-sm font-semibold uppercase tracking-widest text-stone-400">Identificação</h2>
        <div class="mt-5 grid gap-4 md:grid-cols-2">
          <div>
            <label class="mb-1.5 block text-sm text-stone-300">Slug *</label>
            <input v-model="form.slug" required class="input" placeholder="starter" />
          </div>
          <div>
            <label class="mb-1.5 block text-sm text-stone-300">Nome *</label>
            <input v-model="form.name" required class="input" placeholder="Starter" />
          </div>
          <div>
            <label class="mb-1.5 block text-sm text-stone-300">Preço de referência (R$)</label>
            <input
              :value="form.reference_price_cents"
              type="text"
              inputmode="decimal"
              class="input"
              placeholder="99,00"
              @input="form.reference_price_cents = moneyInputMask(($event.target as HTMLInputElement).value)"
            />
          </div>
        </div>
      </section>

      <section class="rounded-3xl border border-white/10 bg-white/5 p-6">
        <h2 class="text-sm font-semibold uppercase tracking-widest text-stone-400">Limites</h2>
        <div class="mt-5 grid gap-4 md:grid-cols-3">
          <div>
            <label class="mb-1.5 block text-sm text-stone-300">Máximo de usuários</label>
            <input v-model="form.max_users" inputmode="numeric" class="input" placeholder="Deixe vazio para ilimitado" />
          </div>
          <div>
            <label class="mb-1.5 block text-sm text-stone-300">OS por mês</label>
            <input
              v-model="form.max_service_orders_per_month"
              inputmode="numeric"
              class="input"
              placeholder="Deixe vazio para ilimitado"
            />
          </div>
          <div>
            <label class="mb-1.5 block text-sm text-stone-300">Armazenamento (MB)</label>
            <input
              v-model="form.max_attachments_storage_mb"
              inputmode="numeric"
              class="input"
              placeholder="Deixe vazio para ilimitado"
            />
          </div>
        </div>
      </section>

      <div class="flex flex-wrap items-center gap-3">
        <button
          type="submit"
          :disabled="saving"
          class="rounded-xl bg-amber-500 px-6 py-2.5 text-sm font-semibold text-stone-950 transition hover:bg-amber-400 disabled:opacity-50"
        >
          {{ saving ? 'Salvando…' : isEdit ? 'Salvar alterações' : 'Criar plano' }}
        </button>
        <RouterLink
          :to="{ name: 'admin.plans' }"
          class="rounded-xl border border-white/10 px-6 py-2.5 text-sm text-stone-400 transition hover:text-stone-200"
        >
          Cancelar
        </RouterLink>
        <button
          v-if="isEdit"
          type="button"
          class="ml-auto rounded-xl border border-rose-500/20 bg-rose-500/10 px-4 py-2.5 text-sm font-semibold text-rose-300 transition hover:bg-rose-500/20"
          @click="showDeleteConfirm = true"
        >
          Excluir plano
        </button>
      </div>
    </form>

    <!-- Modal de confirmação de exclusão -->
    <div
      v-if="showDeleteConfirm"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
    >
      <div class="w-full max-w-sm rounded-2xl border border-white/10 bg-stone-900 p-6 shadow-2xl">
        <h2 class="text-base font-semibold text-white">Excluir plano?</h2>
        <p class="mt-2 text-sm text-stone-400">
          Esta ação é irreversível. O plano será removido permanentemente.<br>
          Só é possível excluir planos sem assinaturas ativas.
        </p>
        <div class="mt-6 flex gap-3">
          <button
            type="button"
            :disabled="deleting"
            class="flex-1 rounded-xl border border-rose-500/20 bg-rose-500/10 py-2.5 text-sm font-semibold text-rose-300 transition hover:bg-rose-500/20 disabled:opacity-50"
            @click="confirmDelete"
          >
            {{ deleting ? 'Excluindo…' : 'Sim, excluir' }}
          </button>
          <button
            type="button"
            class="flex-1 rounded-xl border border-white/10 py-2.5 text-sm text-stone-400 transition hover:text-stone-200"
            @click="showDeleteConfirm = false"
          >
            Cancelar
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@reference "../../style.css";
</style>
