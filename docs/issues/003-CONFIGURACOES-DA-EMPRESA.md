# 003 — Configurações da Empresa

## Contexto

A tela de Configurações existe mas está limitada a dados básicos. Para que
múltiplas empresas usem o sistema com identidade própria — especialmente nos
documentos impressos (OS, orçamento, recibo, termo de entrega) — é necessário
que cada empresa configure seus dados cadastrais e preferências operacionais.

## Objetivo

Permitir que o administrador da empresa configure os dados que aparecem em
documentos, defina preferências do sistema e personalize a experiência para
sua equipe e clientes.

## Critérios de Aceite

### Dados Cadastrais (aparecem nos PDFs)

- [ ] Razão social / nome fantasia
- [ ] CNPJ ou CPF (pessoa jurídica ou física)
- [ ] Endereço completo (logradouro, número, bairro, cidade, UF, CEP)
- [ ] Telefone de contato e e-mail comercial
- [ ] Site (opcional)
- [ ] Logo da empresa (upload de imagem, usada no cabeçalho dos PDFs)

### Preferências Operacionais

- [ ] Prazo padrão de validade de orçamento (dias) — pré-preenche o campo na criação
- [ ] Texto de rodapé nos documentos (ex.: "Garantia de 90 dias nos serviços")
- [ ] Habilitar/desabilitar módulo de checklists de recebimento
- [ ] Moeda (BRL por padrão; preparar para eventual suporte a outras moedas)

### Backend

- [ ] Endpoint `GET /api/v1/settings` retorna configurações da empresa autenticada
- [ ] Endpoint `PUT /api/v1/settings` atualiza configurações
- [ ] Endpoint `POST /api/v1/settings/logo` para upload da logo (validar tipo e tamanho)
- [ ] Campos de configuração armazenados em coluna JSON na tabela `companies`
  ou em tabela separada `company_settings`

### Frontend

- [ ] Expandir `SettingsView` com as seções acima
- [ ] Preview da logo após upload
- [ ] Feedback de salvo com sucesso / erro por campo

## Notas Técnicas

- A logo deve ser armazenada no disco configurado (local ou S3)
- Os PDFs (blade views) devem ler os dados de configuração via `$company->settings`
  em vez de strings hardcoded
- Validar CNPJ/CPF no backend com regra customizada

## Dependências

- Issue #002 (onboarding) para que novas empresas tenham acesso à tela
