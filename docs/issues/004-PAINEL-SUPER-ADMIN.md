# 004 — Painel de Super-Admin

## Contexto

Com múltiplas empresas no sistema, é necessária uma visão operacional "acima
dos tenants" para gerenciar o ciclo de vida das empresas, monitorar uso e
intervir quando necessário (suporte, suspensão, auditoria).

## Objetivo

Criar uma área restrita a usuários com role `super_admin` onde seja possível
visualizar e gerenciar todas as empresas cadastradas no sistema.

## Critérios de Aceite

### Backend

- [ ] Role `super_admin` no modelo `User` (diferente de `administrator` de tenant)
- [ ] Middleware `EnsureSuperAdmin` protegendo as rotas de admin
- [ ] Endpoints sob `/api/v1/admin/`:
  - `GET /companies` — lista todas as empresas com contadores (nº de membros,
    nº de OS, data de cadastro, status)
  - `GET /companies/{id}` — detalhe da empresa
  - `PATCH /companies/{id}/suspend` — suspende acesso (retorna 403 para usuários
    da empresa ao autenticar)
  - `PATCH /companies/{id}/activate` — reativa
  - `DELETE /companies/{id}` — exclusão lógica (soft delete), requer confirmação

### Frontend

- [ ] Rota `/admin` visível apenas para `super_admin` no menu ou URL direta
- [ ] Tabela de empresas com busca por nome/CNPJ/e-mail
- [ ] Badge de status (Ativa / Suspensa / Cancelada)
- [ ] Ação de suspender/ativar com modal de confirmação
- [ ] Painel de resumo: total de empresas ativas, OS criadas no mês, novos
  cadastros nos últimos 7 dias

### Autenticação

- [ ] Super admin faz login pela mesma tela `/login` — a role é identificada
  após autenticação e redireciona para `/admin`
- [ ] Super admin não pertence a nenhuma empresa específica; suas requisições
  à API devem ter escopo separado

## Notas Técnicas

- O campo `super_admin` pode ser um boolean na tabela `users` ou uma role
  separada — preferir boolean para simplicidade no MVP
- Logs de todas as ações do super admin (suspensão, reativação, exclusão)
  devem ser registrados em tabela de auditoria ou `activity_log`
- Considerar autenticação de dois fatores para o super admin futuramente

## Dependências

- Issue #002 (onboarding) para que haja empresas a gerenciar
