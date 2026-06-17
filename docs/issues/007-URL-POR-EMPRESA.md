# 007 — URL por Empresa (Subdomínio ou Slug na Rota)

## Contexto

Atualmente o frontend é uma SPA sem contexto de tenant na URL. Com múltiplas
empresas, é desejável que cada assistência tenha uma URL própria — tanto para
identidade de marca quanto para facilitar o compartilhamento de links com
clientes (portal de aprovação de orçamento, por exemplo).

## Objetivo

Implementar identificação de tenant via subdomínio (`techfix.seuapp.com.br`)
ou via path (`seuapp.com.br/techfix`), de forma que o frontend saiba a qual
empresa pertence a sessão sem depender apenas do token JWT.

## Opções de Implementação

### Opção A — Subdomínio (recomendado)
- Cada empresa recebe um subdomínio: `{slug}.app.com.br`
- DNS wildcard (`*.app.com.br`) aponta para o mesmo servidor
- O frontend lê `window.location.hostname` para extrair o slug e envia no header
  `X-Tenant-Slug` em todas as requisições
- O backend resolve o tenant pelo header (fallback: token JWT)

### Opção B — Path/Slug na URL
- URL: `app.com.br/{slug}/dashboard`
- Mais simples de configurar (sem wildcard DNS)
- Requer prefixo em todas as rotas do frontend e links do portal

## Critérios de Aceite

- [ ] Definir e implementar uma das opções acima (decisão antes de iniciar)
- [ ] Campo `slug` único gerado automaticamente no onboarding (ex.: "techfix")
  com possibilidade de personalização pelo admin
- [ ] Portal de aprovação de orçamento (`/portal/{token}`) continua funcionando
  independente da URL do tenant
- [ ] Links nos e-mails (convites, boas-vindas, portal) já usam a URL correta
  do tenant
- [ ] Redirect automático de `app.com.br` para a URL da empresa se o usuário
  já estiver autenticado (cookie ou localStorage)
- [ ] Página de "empresa não encontrada" para slugs inválidos

## Notas Técnicas

- O `slug` deve ser validado: apenas letras minúsculas, números e hífens;
  mínimo 3 caracteres; não pode ser palavra reservada (admin, api, www, etc.)
- Para subdomínio: configurar certificado wildcard (Let's Encrypt + Certbot)
- Para path: ajustar `base` no `vite.config.ts` e `createWebHistory` no router

## Dependências

- Issue #002 (onboarding) para geração do slug no cadastro
- Issue #003 (configurações) para permitir personalização do slug pelo admin
