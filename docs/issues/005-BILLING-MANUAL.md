# 005 — Billing Manual (Planos e Controle de Acesso por Assinatura)

## Contexto

Para operar com múltiplos clientes pagantes, o sistema precisa de algum
controle de assinatura. Antes de integrar um gateway de pagamento, o modelo
manual — onde o admin do sistema cadastra o plano e data de vencimento de cada
empresa — é suficiente para os primeiros clientes e valida o modelo de negócio.

## Objetivo

Permitir que o super-admin defina o plano contratado por cada empresa e que o
sistema bloqueie ou restrinja o acesso quando a assinatura vencer ou o limite
do plano for atingido.

## Critérios de Aceite

### Modelo de Planos (configurável, não hardcoded)

- [ ] Tabela `plans` com: nome, limites (max_users, max_service_orders_per_month,
  max_attachments_storage_mb), preço de referência (apenas informativo no MVP)
- [ ] Planos sugeridos para MVP: `starter` (2 usuários, 100 OS/mês),
  `profissional` (10 usuários, ilimitado), `enterprise` (ilimitado)
- [ ] Tabela `subscriptions` ligando `Company` a `Plan` com: `started_at`,
  `expires_at`, `status` (active / suspended / cancelled)

### Controle de Acesso

- [ ] Middleware `EnsureActiveSubscription` verificado em todas as rotas
  autenticadas de tenant
- [ ] Quando `expires_at < now()`: retorna erro 402 com mensagem clara
- [ ] Quando limite de OS/mês atingido: bloqueia criação de novas OS
- [ ] Frontend exibe banner de aviso quando faltam ≤ 7 dias para vencer
- [ ] Página de "Assinatura suspensa" com instrução de contato

### Super-Admin

- [ ] No painel admin, tela para associar plano e definir `expires_at` por empresa
- [ ] Ação de renovar (extend) assinatura por N dias/meses
- [ ] Listagem de empresas com assinatura vencendo nos próximos 15 dias

### Empresa (auto-consulta)

- [ ] Endpoint `GET /api/v1/subscription` retorna plano atual, limites e
  `expires_at` da empresa autenticada
- [ ] Exibir no `SettingsView` ou em um card no dashboard: plano ativo e validade

## Notas Técnicas

- Grace period de 3 dias após vencimento antes de bloquear (evita problemas
  com fuso horário e atraso de pagamento manual)
- Não implementar cobrança automática nesta issue — isso é a #006
- Registrar em log toda mudança de plano/status para auditoria

## Dependências

- Issue #004 (painel super-admin) para que o admin possa gerenciar assinaturas
