# 002 — Onboarding de Novas Empresas

## Contexto

Hoje só existe a empresa "TechFix", criada via seeder. Não há nenhum fluxo
para que uma nova assistência se cadastre de forma autônoma. Esta issue
implementa o caminho completo: da tela de registro até o primeiro login.

## Objetivo

Permitir que o dono de uma nova assistência acesse uma URL pública, preencha
um formulário de cadastro e, ao final, já esteja logado com sua empresa criada
e seu usuário como administrador.

## Critérios de Aceite

### Backend

- [ ] Endpoint público `POST /api/v1/register` recebendo:
  - `company_name` (nome da assistência)
  - `user_name`
  - `user_email`
  - `password` / `password_confirmation`
- [ ] Cria `Company`, `User` e `Membership` (role `administrator`) em transação
- [ ] Inicializa `CompanyServiceOrderCounter` para a nova empresa
- [ ] Retorna token de acesso (mesmo formato do login)
- [ ] Validação de unicidade de e-mail globalmente
- [ ] Rate limiting no endpoint (máx. 5 tentativas por IP por hora)

### Frontend

- [ ] Rota pública `/cadastro` com formulário de registro
- [ ] Campos: nome da empresa, seu nome, e-mail, senha, confirmação de senha
- [ ] Após sucesso: salva token e redireciona para `/dashboard`
- [ ] Link "Criar conta" na tela de login (`/login`)
- [ ] Validação inline dos campos antes do submit

### E-mail

- [ ] E-mail de boas-vindas enviado após registro com nome da empresa e link de acesso
- [ ] Usar a fila existente (Laravel queue) para envio assíncrono

## Notas Técnicas

- O controller de registro não deve exigir autenticação (middleware `guest`)
- Considerar adicionar `email_verified_at` no futuro, mas não bloquear o acesso
  imediatamente no MVP (verificação opcional)
- O `company_name` deve gerar um `slug` único para uso futuro em URLs por empresa

## Dependências

- Issue #001 (isolamento validado) deve estar concluída antes do deploy externo
