<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { RouterLink, useRoute } from "vue-router";
import { ApiError } from "../../api/client";
import {
  getPrintableCurrentQuote,
  getPrintableDeliveryTerm,
  getPrintablePaymentReceipt,
  type PrintableOperationalDocumentPayload,
} from "../../api/serviceOrders";
import { useTenantRouting } from "../../composables/useTenantRouting";
import { documentMask, phoneMask } from "../../utils/masks";

const route = useRoute();
const { tenantRoute } = useTenantRouting();

const payload = ref<PrintableOperationalDocumentPayload | null>(null);
const loading = ref(true);
const error = ref("");

const documentType = computed(() => String(route.meta.documentType ?? ""));

const titleMap: Record<string, string> = {
  quote: "Orcamento",
  receipt: "Recibo de Pagamento",
  delivery_term: "Termo de Entrega",
};

const statusLabelMap: Record<string, string> = {
  draft: "Rascunho",
  open: "Aberta",
  in_progress: "Em execucao",
  completed: "Concluida",
  ready_for_pickup: "Pronta para retirada",
  delivered: "Entregue",
  cancelled: "Cancelada",
  no_repair: "Sem reparo",
  pending: "Pendente",
  approved: "Aprovado",
  rejected: "Recusado",
  expired: "Expirado",
};

const pageTitle = computed(() => titleMap[documentType.value] ?? "Documento");

const companyLines = computed(() => {
  const company = payload.value?.company;
  if (!company) return [] as string[];

  return [
    company.document ? `Documento: ${documentMask(company.document)}` : null,
    company.email ? `E-mail: ${company.email}` : null,
    company.phone ? `Telefone: ${phoneMask(company.phone)}` : null,
  ].filter((line): line is string => line !== null);
});

const orderDisplayNumber = computed(() => {
  const order = payload.value?.service_order;
  return order?.reference ?? (order?.order_number ? `OS-${String(order.order_number).padStart(6, "0")}` : "OS");
});

const customerRows = computed(() => {
  const customer = payload.value?.customer;
  if (!customer) return [];

  return [
    { label: "Nome", value: customer.name },
    { label: "Documento", value: customer.document ? documentMask(customer.document) : null },
    { label: "Contato", value: customer.contact_name },
    { label: "Telefone", value: customer.contact_phone ? phoneMask(customer.contact_phone) : null },
  ].filter((row) => row.value && String(row.value).trim() !== "");
});

const orderRows = computed(() => {
  const order = payload.value?.service_order;
  if (!order) return [];

  return [
    { label: "Status", value: statusLabelMap[order.status] ?? order.status },
    { label: "Abertura", value: formatDateTime(order.opened_at) },
    { label: "Equipamento", value: [order.equipment_brand, order.equipment_model].filter(Boolean).join(" ") || null },
    { label: "Serial", value: order.equipment_serial_number },
  ].filter((row) => row.value && String(row.value).trim() !== "");
});

const isQuote = computed(() => payload.value?.document_type === "quote");
const isReceipt = computed(() => payload.value?.document_type === "receipt");
const isDeliveryTerm = computed(() => payload.value?.document_type === "delivery_term");

function formatMoney(cents: number, currency = "BRL") {
  return (cents / 100).toLocaleString("pt-BR", { style: "currency", currency });
}

function formatDateTime(value: string | null | undefined) {
  if (!value) return "";
  return new Date(value).toLocaleString("pt-BR");
}

function printDocument() {
  window.print();
}

async function loadPayload() {
  try {
    if (documentType.value === "quote") {
      payload.value = (await getPrintableCurrentQuote(Number(route.params.id))).data;
      return;
    }

    if (documentType.value === "receipt") {
      payload.value = (
        await getPrintablePaymentReceipt(Number(route.params.id), Number(route.params.paymentId))
      ).data;
      return;
    }

    if (documentType.value === "delivery_term") {
      payload.value = (await getPrintableDeliveryTerm(Number(route.params.id))).data;
      return;
    }

    error.value = "Tipo de documento nao suportado.";
  } catch (err) {
    if (err instanceof ApiError) {
      error.value =
        (err.data as { message?: string }).message ??
        "Nao foi possivel carregar o documento imprimivel.";
    } else {
      error.value = "Nao foi possivel carregar o documento imprimivel.";
    }
  } finally {
    loading.value = false;
  }
}

onMounted(loadPayload);
</script>

<template>
  <div class="min-h-screen bg-stone-100 text-stone-900">
    <div class="mx-auto flex max-w-[210mm] items-center justify-between gap-3 px-4 py-4 print:hidden">
      <RouterLink
        :to="tenantRoute('service-orders.detail', { params: { id: route.params.id } })"
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
      Carregando documento...
    </div>

    <div v-else-if="error" class="mx-auto max-w-3xl px-4 py-10">
      <div class="rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ error }}
      </div>
    </div>

    <section
      v-else-if="payload"
      class="mx-auto max-w-[210mm] bg-white px-6 py-6 shadow-sm ring-1 ring-stone-200 print:shadow-none print:ring-0"
    >
      <header class="border-b border-stone-200 pb-4">
        <div class="space-y-1 text-center">
          <h1 class="text-[24px] font-bold uppercase leading-none text-stone-950">
            {{ payload.company.name || "Empresa sem nome" }}
          </h1>
          <div v-if="companyLines.length" class="space-y-0.5 text-[12px] leading-5 text-stone-700">
            <p v-for="line in companyLines" :key="line">{{ line }}</p>
          </div>
        </div>

        <div class="mt-4 bg-stone-100 px-4 py-2 text-center">
          <p class="text-[12px] font-bold uppercase tracking-[0.08em] text-stone-900">
            {{ pageTitle }} - {{ orderDisplayNumber }}
          </p>
        </div>

        <div class="mt-3 flex items-start justify-between gap-6 text-[12px] leading-5 text-stone-700">
          <div>
            <p class="font-semibold uppercase tracking-[0.18em] text-stone-500">
              Documento operacional
            </p>
          </div>
          <div class="text-right">
            <p>Gerado em: {{ formatDateTime(payload.generated_at) }}</p>
          </div>
        </div>
      </header>

      <div class="mt-4 grid gap-4 md:grid-cols-2">
        <section class="rounded border border-stone-200 px-4 py-3">
          <h2 class="mb-3 text-[12px] font-bold uppercase tracking-[0.18em] text-stone-900">Cliente</h2>
          <div class="space-y-1.5 text-[12px] leading-5 text-stone-700">
            <div v-for="row in customerRows" :key="row.label" class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-1.5">
              <span class="font-semibold text-stone-950">{{ row.label }}:</span>
              <span class="break-words">{{ row.value }}</span>
            </div>
          </div>
        </section>

        <section class="rounded border border-stone-200 px-4 py-3">
          <h2 class="mb-3 text-[12px] font-bold uppercase tracking-[0.18em] text-stone-900">Ordem de Serviço</h2>
          <div class="space-y-1.5 text-[12px] leading-5 text-stone-700">
            <div v-for="row in orderRows" :key="row.label" class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-1.5">
              <span class="font-semibold text-stone-950">{{ row.label }}:</span>
              <span class="break-words">{{ row.value }}</span>
            </div>
          </div>
        </section>
      </div>

      <section v-if="payload.service_order.reported_problem" class="mt-4 rounded border border-stone-200 px-4 py-3">
        <h2 class="mb-2 text-[12px] font-bold uppercase tracking-[0.18em] text-stone-900">Problema relatado</h2>
        <p class="text-[12px] leading-5 text-stone-700">{{ payload.service_order.reported_problem }}</p>
      </section>

      <section v-if="payload.service_order.equipment_description" class="mt-4 rounded border border-stone-200 px-4 py-3">
        <h2 class="mb-2 text-[12px] font-bold uppercase tracking-[0.18em] text-stone-900">Descrição registrada</h2>
        <p class="text-[12px] leading-5 text-stone-700">{{ payload.service_order.equipment_description }}</p>
      </section>

      <section v-if="isQuote && payload.document_type === 'quote'" class="mt-4 rounded border border-stone-200 px-4 py-3">
        <div class="flex items-start justify-between gap-4">
          <div>
            <h2 class="text-[12px] font-bold uppercase tracking-[0.18em] text-stone-900">Orçamento vigente</h2>
            <p class="mt-1 text-[12px] text-stone-700">
              Revisão {{ payload.quote.revision_number }} · {{ statusLabelMap[payload.quote.status] ?? payload.quote.status }}
            </p>
          </div>
          <div class="text-right text-[12px] text-stone-700">
            <p v-if="payload.quote.submitted_at">Enviado em: {{ formatDateTime(payload.quote.submitted_at) }}</p>
            <p v-if="payload.quote.valid_until">Válido até: {{ formatDateTime(payload.quote.valid_until) }}</p>
          </div>
        </div>

        <div class="mt-4 overflow-hidden rounded border border-stone-200">
          <table class="w-full text-left text-[12px]">
            <thead class="bg-stone-100 text-stone-900">
              <tr>
                <th class="px-3 py-2 font-semibold">Item</th>
                <th class="px-3 py-2 font-semibold">Qtd.</th>
                <th class="px-3 py-2 font-semibold">Unitário</th>
                <th class="px-3 py-2 font-semibold text-right">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, index) in payload.quote.line_items" :key="index" class="border-t border-stone-200">
                <td class="px-3 py-2 text-stone-700">{{ item.description }}</td>
                <td class="px-3 py-2 text-stone-700">{{ item.quantity }}</td>
                <td class="px-3 py-2 text-stone-700">{{ formatMoney(item.unit_price_cents, payload.quote.currency) }}</td>
                <td class="px-3 py-2 text-right font-medium text-stone-900">
                  {{ formatMoney(item.line_total_cents, payload.quote.currency) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="mt-3 flex justify-between border-t border-stone-200 pt-3 text-[13px]">
          <span class="font-semibold text-stone-900">Total</span>
          <span class="font-semibold text-stone-900">{{ formatMoney(payload.quote.total_cents, payload.quote.currency) }}</span>
        </div>

        <div v-if="payload.quote.notes" class="mt-3 border-t border-stone-200 pt-3">
          <p class="mb-1 text-[12px] font-semibold uppercase tracking-[0.16em] text-stone-900">Observações do orçamento</p>
          <p class="text-[12px] leading-5 text-stone-700">{{ payload.quote.notes }}</p>
        </div>
      </section>

      <section v-if="isReceipt && payload.document_type === 'receipt'" class="mt-4 rounded border border-stone-200 px-4 py-3">
        <h2 class="text-[12px] font-bold uppercase tracking-[0.18em] text-stone-900">Recibo de pagamento</h2>
        <div class="mt-3 grid gap-2 text-[12px] text-stone-700 md:grid-cols-2">
          <p><span class="font-semibold text-stone-950">Pagamento:</span> #{{ payload.receipt.payment_id }}</p>
          <p><span class="font-semibold text-stone-950">Método:</span> {{ payload.receipt.payment_method }}</p>
          <p><span class="font-semibold text-stone-950">Recebido em:</span> {{ formatDateTime(payload.receipt.received_at) }}</p>
          <p><span class="font-semibold text-stone-950">Valor:</span> {{ formatMoney(payload.receipt.amount_cents, payload.receipt.currency) }}</p>
          <p><span class="font-semibold text-stone-950">Saldo anterior:</span> {{ formatMoney(payload.receipt.outstanding_balance_before_payment_cents, payload.receipt.currency) }}</p>
          <p><span class="font-semibold text-stone-950">Saldo após pagamento:</span> {{ formatMoney(payload.receipt.balance_after_payment_cents, payload.receipt.currency) }}</p>
        </div>
        <div v-if="payload.receipt.notes" class="mt-3 border-t border-stone-200 pt-3">
          <p class="mb-1 text-[12px] font-semibold uppercase tracking-[0.16em] text-stone-900">Observações</p>
          <p class="text-[12px] leading-5 text-stone-700">{{ payload.receipt.notes }}</p>
        </div>
      </section>

      <section v-if="isDeliveryTerm && payload.document_type === 'delivery_term'" class="mt-4 rounded border border-stone-200 px-4 py-3">
        <h2 class="text-[12px] font-bold uppercase tracking-[0.18em] text-stone-900">Comprovante de entrega</h2>
        <div class="mt-3 grid gap-2 text-[12px] text-stone-700 md:grid-cols-2">
          <p><span class="font-semibold text-stone-950">Recebido por:</span> {{ payload.service_order.received_by_name || "—" }}</p>
          <p><span class="font-semibold text-stone-950">Entregue em:</span> {{ formatDateTime(payload.service_order.delivered_at) }}</p>
          <p><span class="font-semibold text-stone-950">Saldo no momento da entrega:</span> {{ formatMoney(payload.service_order.outstanding_balance_cents) }}</p>
        </div>
        <div v-if="payload.service_order.delivery_notes" class="mt-3 border-t border-stone-200 pt-3">
          <p class="mb-1 text-[12px] font-semibold uppercase tracking-[0.16em] text-stone-900">Observações da entrega</p>
          <p class="text-[12px] leading-5 text-stone-700">{{ payload.service_order.delivery_notes }}</p>
        </div>
      </section>

      <div class="mt-10 grid gap-10 text-center text-[12px] text-stone-500 md:grid-cols-2">
        <div class="border-t border-stone-300 pt-2">Assinatura do cliente</div>
        <div class="border-t border-stone-300 pt-2">Assinatura do atendente</div>
      </div>
    </section>
  </div>
</template>
