export function useServiceOrderFormatting() {
  const statusLabels: Record<string, { label: string; cls: string }> = {
    draft:            { label: 'Rascunho', cls: 'bg-stone-500/20 text-stone-400' },
    open:             { label: 'Aberta', cls: 'bg-sky-500/20 text-sky-300' },
    in_progress:      { label: 'Em Execução', cls: 'bg-amber-500/20 text-amber-300' },
    completed:        { label: 'Concluída', cls: 'bg-emerald-500/20 text-emerald-300' },
    ready_for_pickup: { label: 'Aguard. Retirada', cls: 'bg-violet-500/20 text-violet-300' },
    delivered:        { label: 'Entregue', cls: 'bg-emerald-600/20 text-emerald-400' },
    cancelled:        { label: 'Cancelada', cls: 'bg-red-500/20 text-red-400' },
    no_repair:        { label: 'Sem Reparo', cls: 'bg-orange-500/20 text-orange-300' },
  }

  const quoteStatusLabels: Record<string, { label: string; cls: string }> = {
    pending: { label: 'Aguardando', cls: 'bg-amber-500/20 text-amber-300' },
    approved: { label: 'Aprovado', cls: 'bg-emerald-500/20 text-emerald-300' },
    rejected: { label: 'Recusado', cls: 'bg-red-500/20 text-red-400' },
    expired: { label: 'Expirado', cls: 'bg-stone-500/20 text-stone-400' },
  }

  const paymentMethodLabels: Record<string, string> = {
    pix: 'PIX',
    cash: 'Dinheiro',
    credit_card: 'Cartão de Crédito',
    debit_card: 'Cartão de Débito',
    bank_transfer: 'Transferência',
    other: 'Outro',
  }

  const attachmentTypeLabels: Record<string, string> = {
    intake_photo: 'Foto de Entrada',
    diagnostic_image: 'Imagem Diagnóstico',
    receipt: 'Recibo',
    term: 'Termo',
    signed_service_order_copy: 'Via Assinada da O.S.',
    other: 'Outro',
  }

  function fmt(dt: string | null) {
    if (!dt) return '—'
    return new Date(dt).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })
  }

  function fmtMoney(cents: number) {
    return (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
  }

  function fmtBytes(bytes: number) {
    if (bytes < 1024) return `${bytes} B`
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`
  }

  function attachmentTypeLabel(type: string) {
    return attachmentTypeLabels[type] ?? type
  }

  return {
    statusLabels,
    quoteStatusLabels,
    paymentMethodLabels,
    attachmentTypeLabels,
    fmt,
    fmtMoney,
    fmtBytes,
    attachmentTypeLabel,
  }
}
