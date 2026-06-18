<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { ApiError } from '../../api/client'
import {
  createAdminPlan,
  getAdminPlan,
  updateAdminPlan,
  type AdminPlanUpsertPayload,
} from '../../api/admin'

const route = useRoute()
const router = useRouter()
const isEdit = computed(() => Boolean(route.params.id))
const loading = ref(false)
const saving = ref(false)
const error = ref('')

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
    form.reference_price_cents = plan.reference_price_cents === null ? '' : String(plan.reference_price_cents)
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
      reference_price_cents: toNullableInteger(form.reference_price_cents),
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
            <label class="mb-1.5 block text-sm text-stone-300">Preço de referência (centavos)</label>
            <input v-model="form.reference_price_cents" inputmode="numeric" class="input" placeholder="9900" />
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

      <div class="flex flex-wrap gap-3">
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
      </div>
    </form>
  </div>
</template>

<style scoped>
@reference "../../style.css";
</style>
