# 006 — Billing Automatizado (Integração com Gateway de Pagamento)

## Contexto

Com o modelo manual de billing validado (issue #005), o próximo passo é
automatizar o ciclo de cobrança para que empresas possam contratar, renovar
e cancelar o plano sem intervenção do super-admin.

## Objetivo

Integrar um gateway de pagamento brasileiro para que empresas realizem o
checkout, tenham seus planos ativados automaticamente e recebam notificações
de cobrança e vencimento.

## Critérios de Aceite

### Gateway

- [ ] Integração com **Asaas** ou **Stripe** (decidir antes de implementar;
  Asaas é recomendado para PIX/boleto no Brasil)
- [ ] Criação automática de cliente no gateway ao finalizar onboarding (#002)
- [ ] Checkout de plano: empresa escolhe plano → redireciona para checkout
  → webhook confirma pagamento → assinatura ativada automaticamente

### Ciclo de Vida

- [ ] Webhook de pagamento confirmado → renova `expires_at` + registra pagamento
- [ ] Webhook de pagamento recusado → envia e-mail de aviso
- [ ] Webhook de assinatura cancelada → status `cancelled` na tabela `subscriptions`
- [ ] Job agendado (diário) para sincronizar status de assinaturas com o gateway

### Frontend

- [ ] Página `/configuracoes/plano` com:
  - Plano atual e data de renovação
  - Histórico de faturas com link para download do PDF
  - Botão de upgrade / downgrade de plano
  - Botão de cancelar assinatura (com confirmação e aviso de consequências)
- [ ] Fluxo de upgrade dentro do sistema sem sair para link externo

### E-mails Automáticos

- [ ] 15 dias antes do vencimento: lembrete de renovação
- [ ] 3 dias antes: aviso urgente
- [ ] No vencimento: notificação de suspensão (com grace period de 3 dias)
- [ ] Após pagamento confirmado: recibo e confirmação de renovação

## Notas Técnicas

- Usar Laravel Cashier (Stripe) ou SDK oficial do Asaas
- Todos os webhooks devem verificar assinatura do payload antes de processar
- Armazenar `gateway_customer_id` e `gateway_subscription_id` na tabela
  `subscriptions` para reconciliação
- Não armazenar dados de cartão no sistema — tudo via tokenização do gateway

## Dependências

- Issue #005 (billing manual) — modelo de dados de planos e assinaturas já criado
- Issue #003 (configurações da empresa) — CNPJ/dados fiscais para emissão de NF
