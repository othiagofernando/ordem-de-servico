<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { getCustomer, type Customer } from '../../api/customers'
import { useTenantRouting } from '../../composables/useTenantRouting'
import { documentMask, phoneMask, cepMask } from '../../utils/masks'

const route = useRoute()
const { tenantRoute } = useTenantRouting()
const customer = ref<Customer | null>(null)
const loading = ref(true)

onMounted(async () => {
  const res = await getCustomer(Number(route.params.id))
  customer.value = res.data
  loading.value = false
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center gap-4">
      <RouterLink :to="tenantRoute('customers')" class="text-stone-500 hover:text-stone-200">← Clientes</RouterLink>
    </div>

    <div v-if="loading" class="py-16 text-center text-stone-500">Carregando…</div>

    <template v-else-if="customer">
      <div class="flex items-start justify-between gap-3">
        <div>
          <h1 class="text-2xl font-semibold text-white">{{ customer.name }}</h1>
          <p v-if="customer.trade_name" class="text-stone-400">{{ customer.trade_name }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <RouterLink
            :to="tenantRoute('service-orders.create', { query: { customer_id: customer.id } })"
            class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-stone-950 transition hover:bg-amber-400"
          >
            + Nova OS
          </RouterLink>
          <RouterLink
            :to="tenantRoute('customers.edit', { params: { id: customer.id } })"
            class="rounded-xl border border-white/10 px-4 py-2 text-sm text-stone-300 transition hover:border-amber-500/30 hover:text-amber-300"
          >
            Editar
          </RouterLink>
        </div>
      </div>

      <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
          <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Tipo</p>
          <p class="mt-2 text-lg font-semibold text-white">{{ customer.customer_type }}</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
          <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Contatos</p>
          <p class="mt-2 text-lg font-semibold text-white">{{ customer.contacts.length }}</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
          <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Endereços</p>
          <p class="mt-2 text-lg font-semibold text-white">{{ customer.addresses.length }}</p>
        </div>
      </div>

      <div class="grid gap-4 lg:grid-cols-2">
        <!-- Dados gerais -->
        <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
          <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-stone-500">Dados Gerais</h2>
          <dl class="space-y-3">
            <div class="flex justify-between">
              <dt class="text-sm text-stone-500">Tipo</dt>
              <dd>
                <span
                  :class="customer.customer_type === 'PJ' ? 'bg-sky-500/10 text-sky-300' : 'bg-violet-500/10 text-violet-300'"
                  class="rounded-full px-2 py-0.5 text-xs font-semibold"
                >
                  {{ customer.customer_type }}
                </span>
              </dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sm text-stone-500">Documento</dt>
              <dd class="text-sm text-stone-200">{{ customer.document ? documentMask(customer.document) : '—' }}</dd>
            </div>
            <div v-if="customer.notes" class="border-t border-white/5 pt-3">
              <dt class="mb-1 text-sm text-stone-500">Observações</dt>
              <dd class="text-sm text-stone-300">{{ customer.notes }}</dd>
            </div>
          </dl>
        </div>

        <!-- Contatos -->
        <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
          <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-stone-500">Contatos</h2>
          <div v-if="customer.contacts.length === 0" class="text-sm text-stone-500">Nenhum contato cadastrado.</div>
          <div class="space-y-3">
            <div
              v-for="(contact, i) in customer.contacts"
              :key="i"
              class="rounded-xl border border-white/5 bg-black/20 px-4 py-3"
            >
              <p v-if="contact.label" class="mb-1 text-xs text-stone-500">{{ contact.label }}</p>
              <p class="text-sm font-medium text-stone-200">{{ contact.name ?? '—' }}</p>
              <p v-if="contact.phone" class="text-sm text-stone-400">{{ phoneMask(contact.phone) }}</p>
              <p v-if="contact.email" class="text-sm text-stone-400">{{ contact.email }}</p>
            </div>
          </div>
        </div>

        <!-- Endereços -->
        <div v-if="customer.addresses.length > 0" class="rounded-2xl border border-white/10 bg-white/5 p-6 lg:col-span-2">
          <h2 class="mb-4 text-sm font-semibold uppercase tracking-widest text-stone-500">Endereços</h2>
          <div class="grid gap-3 sm:grid-cols-2">
            <div
              v-for="(addr, i) in customer.addresses"
              :key="i"
              class="rounded-xl border border-white/5 bg-black/20 px-4 py-3"
            >
              <p v-if="addr.label" class="mb-1 text-xs text-stone-500">{{ addr.label }}</p>
              <p class="text-sm text-stone-200">{{ addr.line_1 }}<span v-if="addr.line_2">, {{ addr.line_2 }}</span></p>
              <p class="text-sm text-stone-400">{{ addr.district }} — {{ addr.city }}/{{ addr.state }}</p>
              <p v-if="addr.postal_code" class="text-sm text-stone-500">CEP {{ cepMask(addr.postal_code) }}</p>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
