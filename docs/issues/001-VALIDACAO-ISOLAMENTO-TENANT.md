# 001 — Validação de Isolamento entre Tenants

## Contexto

O sistema já escopа todos os recursos (OS, clientes, equipamentos, checklists)
à empresa do usuário autenticado. Porém, esse isolamento nunca foi testado
formalmente com dois tenants simultâneos. Antes de abrir o cadastro para novas
empresas, é obrigatório garantir que nenhum dado vaze entre tenants.

## Objetivo

Garantir, por meio de testes automatizados e revisão manual, que um usuário
autenticado em uma empresa **nunca** consiga ler, escrever ou referenciar dados
de outra empresa — mesmo manipulando IDs diretamente na URL ou no payload.

## Critérios de Aceite

- [ ] Criar empresa B no seeder/factory de testes separada da empresa A (TechFix)
- [ ] Testes de feature cobrindo os endpoints críticos:
  - `GET /api/v1/service-orders/{id}` — deve retornar 404 para OS de outra empresa
  - `GET /api/v1/customers/{id}` — idem
  - `GET /api/v1/equipments/{id}` — idem
  - `POST /api/v1/service-orders` com `customer_id` ou `equipment_id` de outra empresa — deve retornar 422
  - `GET /api/v1/service-orders` (listagem) — não deve incluir OS de outra empresa
  - Relatórios — não devem cruzar dados
- [ ] Revisar todos os `resolveXxx()` e `authorize()` nos controllers para confirmar
  que usam `$tenant` / `company_id` do usuário autenticado, nunca o ID direto da URL
- [ ] Endpoints de portal de cliente (token-based) — confirmar que o token não
  permite acesso a outros recursos além do orçamento vinculado
- [ ] Nenhum teste novo pode usar `->withoutExceptionHandling()` para mascarar 403/404

## Notas Técnicas

- Os testes devem herdar de `TestCase` e usar `RefreshDatabase`
- Criar trait `InteractsWithTenant` para facilitar setup de dois tenants nos testes
- Priorizar revisão dos controllers que recebem IDs de recursos relacionados
  (ex.: `equipment_id` na criação de OS pode ser um vetor de enumeração)

## Dependências

Nenhuma — esta issue deve ser concluída antes de qualquer onboarding externo.
