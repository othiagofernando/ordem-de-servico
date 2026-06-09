<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { RouterLink, useRoute } from "vue-router";
import { ApiError } from "../../api/client";
import {
  getPrintableServiceOrder,
  type PrintableServiceOrderPayload,
  type ServiceOrderStatus,
} from "../../api/serviceOrders";

const route = useRoute();

const payload = ref<PrintableServiceOrderPayload | null>(null);
const loading = ref(true);
const error = ref("");

const statusLabels: Record<ServiceOrderStatus, string> = {
  draft: "Rascunho",
  open: "Aberta",
  in_progress: "Em execucao",
  completed: "Concluida",
  ready_for_pickup: "Pronta para retirada",
  delivered: "Entregue",
  cancelled: "Cancelada",
  no_repair: "Sem reparo",
};

const orderDisplayNumber = computed(() => {
  const order = payload.value?.service_order;
  return (
    order?.reference ??
    (order?.order_number
      ? `OS-${String(order.order_number).padStart(6, "0")}`
      : "OS")
  );
});

const customerRows = computed(() => {
  const customer = payload.value?.service_order.customer;
  if (!customer) return [];

  return [
    { label: "Nome", value: customer.name },
    { label: "Documento", value: customer.document },
    { label: "Contato", value: customer.contact_name },
    { label: "Telefone", value: customer.contact_phone },
  ].filter((row) => row.value && String(row.value).trim() !== "");
});

const equipmentRows = computed(() => {
  const equipment = payload.value?.service_order.equipment;
  const description = payload.value?.service_order.equipment_description;
  if (!equipment) return [];

  return [
    { label: "Marca", value: equipment.brand },
    { label: "Modelo", value: equipment.model },
    { label: "Serial", value: equipment.serial_number },
    { label: "Descricao registrada", value: description },
    { label: "Acessorios", value: equipment.accessories },
  ].filter((row) => row.value && String(row.value).trim() !== "");
});

const equipmentPrimaryRows = computed(() => {
  const equipment = payload.value?.service_order.equipment;

  return [
    { label: "Marca", value: equipment?.brand },
    { label: "Modelo", value: equipment?.model },
  ].filter((row) => row.value && String(row.value).trim() !== "");
});

const equipmentIdentityRows = computed(() => {
  const equipment = payload.value?.service_order.equipment;

  return [
    { label: "Serial", value: equipment?.serial_number },
    { label: "Acessorios", value: equipment?.accessories },
  ].filter((row) => row.value && String(row.value).trim() !== "");
});

const equipmentDescription = computed(
  () => payload.value?.service_order.equipment_description?.trim() ?? "",
);

const checklistItems = computed(
  () => payload.value?.service_order.intake_checklist ?? [],
);
const observations = computed(
  () => payload.value?.service_order.observations ?? [],
);
const companyContacts = computed(() => {
  const company = payload.value?.company;
  if (!company) return [] as string[];

  return [
    company.document ? `Documento: ${company.document}` : null,
    company.email ? `E-mail: ${company.email}` : null,
    company.phone ? `Telefone: ${company.phone}` : null,
  ].filter((line): line is string => line !== null);
});

const openedDateLabel = computed(() => {
  const openedAt = payload.value?.service_order.opened_at;
  if (!openedAt) return "";

  return new Date(openedAt).toLocaleDateString("pt-BR");
});

function printDocument() {
  window.print();
}

onMounted(async () => {
  try {
    const response = await getPrintableServiceOrder(Number(route.params.id));
    payload.value = response.data;
  } catch (err) {
    if (err instanceof ApiError) {
      error.value =
        (err.data as { message?: string }).message ??
        "Nao foi possivel carregar a versao imprimivel.";
    } else {
      error.value = "Nao foi possivel carregar a versao imprimivel.";
    }
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <div class="print-app min-h-screen bg-stone-100 text-stone-900">
    <div
      class="mx-auto flex max-w-[210mm] items-center justify-between gap-3 px-4 py-4 print:hidden"
    >
      <RouterLink
        :to="{ name: 'service-orders.detail', params: { id: route.params.id } }"
        class="text-sm text-stone-600 hover:text-stone-900"
      >
        ← Voltar para a O.S.
      </RouterLink>
      <button
        type="button"
        class="rounded-lg bg-stone-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-stone-700"
        @click="printDocument"
      >
        Imprimir agora
      </button>
    </div>

    <div v-if="loading" class="py-20 text-center text-stone-500">
      Carregando versao imprimivel...
    </div>

    <div v-else-if="error" class="mx-auto max-w-3xl px-4 py-10">
      <div
        class="rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700"
      >
        {{ error }}
      </div>
    </div>

    <div
      v-else-if="payload"
      class="mx-auto max-w-[210mm] space-y-4 px-4 pb-8 print:space-y-0 print:px-0 print:pb-0"
    >
      <section
        v-for="copy in payload.copies"
        :key="copy.key"
        class="print-sheet overflow-hidden bg-white shadow-sm ring-1 ring-stone-200 print:shadow-none print:ring-0"
      >
        <header class="print-header px-6 pb-3 pt-5">
          <div class="space-y-1 text-center">
            <h1
              class="text-[24px] font-bold uppercase leading-none text-stone-950"
            >
              {{ payload.company.name || "Empresa sem nome" }}
            </h1>
            <div
              v-if="companyContacts.length"
              class="space-y-0.5 text-[12px] leading-5 text-stone-700"
            >
              <p v-for="line in companyContacts" :key="line">{{ line }}</p>
            </div>
          </div>

          <div class="mt-4 bg-stone-100 px-4 py-2 text-center">
            <p
              class="text-[12px] font-bold uppercase tracking-[0.08em] text-stone-900"
            >
              Ordem de Servico - {{ orderDisplayNumber }}
            </p>
          </div>

          <div
            class="mt-3 flex items-start justify-between gap-6 text-[12px] leading-5 text-stone-700"
          >
            <div>
              <p
                class="font-semibold uppercase tracking-[0.18em] text-stone-500"
              >
                {{ copy.label }}
              </p>
            </div>
            <div class="text-right">
              <p>
                Status:
                {{
                  statusLabels[payload.service_order.status] ??
                  payload.service_order.status
                }}
              </p>
              <p v-if="openedDateLabel">Abertura: {{ openedDateLabel }}</p>
            </div>
          </div>
        </header>

        <div class="space-y-4 px-6 py-4">
          <div class="grid gap-4 md:grid-cols-2">
            <section v-if="customerRows.length" class="print-block">
              <h2 class="print-section-title">Cliente</h2>
              <div class="space-y-1.5 text-[12px] leading-5 text-stone-700">
                <div
                  v-for="row in customerRows"
                  :key="row.label"
                  class="grid min-w-0 grid-cols-[auto_minmax(0,1fr)] items-start gap-x-1.5"
                >
                  <span class="font-semibold text-stone-950"
                    >{{ row.label }}:</span
                  >
                  <span class="min-w-0 break-words">{{ row.value }}</span>
                </div>
              </div>
            </section>

            <section v-if="equipmentRows.length" class="print-block">
              <h2 class="print-section-title">Equipamento</h2>
              <div class="space-y-1.5 text-[12px] leading-5 text-stone-700">
                <div
                  v-if="equipmentPrimaryRows.length"
                  class="grid gap-x-4 gap-y-2 sm:grid-cols-2"
                >
                  <div
                    v-for="row in equipmentPrimaryRows"
                    :key="row.label"
                    class="grid min-w-0 grid-cols-[auto_minmax(0,1fr)] items-start gap-x-1.5"
                  >
                    <span class="font-semibold text-stone-950"
                      >{{ row.label }}:</span
                    >
                    <span class="min-w-0 break-words">{{ row.value }}</span>
                  </div>
                </div>
                <div
                  v-for="row in equipmentIdentityRows"
                  :key="row.label"
                  class="grid grid-cols-[auto_minmax(0,1fr)] items-start gap-x-1.5"
                  >
                  <span class="font-semibold text-stone-950"
                    >{{ row.label }}:</span
                  >
                  <span class="min-w-0 break-words">{{ row.value }}</span>
                </div>
                <div v-if="equipmentDescription" class="space-y-1 pt-0.5">
                  <p class="font-semibold text-stone-950">
                    Descricao registrada:
                  </p>
                  <p class="break-words">{{ equipmentDescription }}</p>
                </div>
              </div>
            </section>
          </div>

          <section
            v-if="payload.service_order.reported_problem"
            class="print-block"
          >
            <h2 class="print-section-title">Defeito relatado</h2>
            <p class="text-[12px] leading-6 text-stone-800">
              {{ payload.service_order.reported_problem }}
            </p>
          </section>

          <section v-if="observations.length" class="print-block">
            <h2 class="print-section-title">Observacoes</h2>
            <ul class="space-y-1 text-[12px] leading-5 text-stone-800">
              <li v-for="note in observations" :key="note">{{ note }}</li>
            </ul>
          </section>

          <section v-if="checklistItems.length" class="print-block">
            <h2 class="print-section-title">Checklist de entrada</h2>
            <table class="w-full border-collapse text-[12px]">
              <thead>
                <tr class="border-b border-stone-300 text-left text-stone-600">
                  <th class="py-2 pr-3 font-semibold">Item</th>
                  <th class="py-2 pr-3 font-semibold">Status</th>
                  <th class="py-2 font-semibold">Observacao</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="item in checklistItems"
                  :key="item.label"
                  class="border-b border-stone-200 text-stone-800 last:border-b-0"
                >
                  <td class="py-2 pr-3 align-top">{{ item.label }}</td>
                  <td class="py-2 pr-3 align-top">
                    {{ item.checked ? "Conferido" : "Nao conferido" }}
                  </td>
                  <td class="py-2 align-top">{{ item.notes || "—" }}</td>
                </tr>
              </tbody>
            </table>
          </section>
        </div>

        <footer class="grid grid-cols-2 gap-10 px-6 pb-6 pt-8">
          <div
            v-for="signature in payload.service_order.signature_blocks"
            :key="`${copy.key}-${signature.key}`"
            class="space-y-2"
          >
            <div class="h-10 border-b border-stone-400"></div>
            <p class="text-[12px] text-stone-700">{{ signature.label }}</p>
          </div>
        </footer>
      </section>
    </div>
  </div>
</template>

<style scoped>
.print-sheet {
  width: 100%;
}

.print-block {
  border: 1px solid #d6d3d1;
  padding: 10px 12px;
  break-inside: avoid;
}

.print-section-title {
  margin-bottom: 8px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: #292524;
}

@page {
  size: A4;
  margin: 10mm;
}

@media print {
  .print-app {
    background: #fff;
  }

  .print-sheet {
    min-height: auto;
    break-after: page;
  }

  .print-sheet:last-child {
    break-after: auto;
  }

  .print-header,
  .print-block,
  footer {
    break-inside: avoid;
  }
}
</style>
