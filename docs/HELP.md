# 📘 MANUAL DE UTILIZAÇÃO - SISTEMA DE ORDEM DE SERVIÇO

**Versão:** 1.0  
**Data:** Junho 2026  
**Público-alvo:** Técnicos e Funcionários de Assistência Técnica

---

## 📑 ÍNDICE

1. [Bem-vindo ao Sistema](#bem-vindo)
2. [Primeiro Acesso](#primeiro-acesso)
3. [Dashboard - Visão Geral](#dashboard)
4. [Gerenciamento de Clientes](#clientes)
5. [Gerenciamento de Equipamentos](#equipamentos)
6. [Criação de Ordens de Serviço](#ordens-servico)
7. [Fluxo de Execução (Trabalho)](#execucao)
8. [Diagnóstico e Orçamento](#diagnostico-orcamento)
9. [Testes Finais](#testes-finais)
10. [Pagamento e Entrega](#pagamento-entrega)
11. [Gerenciamento de Usuários](#usuarios)
12. [Auditoria](#auditoria)
13. [Relatórios](#relatorios)
14. [Base de Conhecimento (Playbooks)](#playbooks)
15. [Dicas e Troubleshooting](#troubleshooting)

---

## 🎯 BEM-VINDO AO SISTEMA {#bem-vindo}

O **Sistema de Ordem de Serviço** foi desenvolvido para gerenciar todo o ciclo de vida de um equipamento em manutenção, desde a entrada até a saída.

### Principais Funcionalidades:
- ✅ Cadastro de clientes e equipamentos
- ✅ Criação e acompanhamento de ordens de serviço
- ✅ Registro de trabalho realizado
- ✅ Diagnóstico e orçamento
- ✅ Gerenciamento de pagamentos
- ✅ Auditoria completa de operações
- ✅ Base de conhecimento com guias práticos

---

## 🔐 PRIMEIRO ACESSO {#primeiro-acesso}

### 1. Login

1. Abra o navegador e acesse: `http://localhost:5173` (ou seu domínio)
2. Você será direcionado para a tela de **Login**
3. Preencha seus dados:
   - **Email:** seu email de acesso
   - **Senha:** sua senha

### 2. Seleção da Empresa

Após o login, se você trabalha em múltiplas assistências técnicas:
1. Selecione a **empresa/assistência técnica** onde você deseja trabalhar
2. Clique em **Confirmar**

### 3. Navegação Inicial

Você será redirecionado para o **Dashboard**, que é sua área de trabalho principal.

---

## 📊 DASHBOARD - VISÃO GERAL {#dashboard}

O Dashboard exibe um resumo das operações da sua assistência técnica.

### Informações Exibidas:
- **Ordens de Serviço em Aberto** - Quantas OSs estão aguardando ação
- **Ordens Prontas para Retirada** - Equipamentos que já foram reparados
- **Orçamentos Pendentes** - Quantos orçamentos aguardam aprovação
- **Faturamento do Mês** - Receita acumulada
- **Atividades Recentes** - Últimas ações registradas no sistema

### Menu Lateral

No lado esquerdo, você encontrará os seguintes menus:
- 📊 **Dashboard** - Visão geral (você está aqui)
- 🔧 **Ordens de Serviço** - Listar e gerenciar OSs
- 👥 **Clientes** - Cadastro de clientes
- 📱 **Equipamentos** - Cadastro de equipamentos
- 📈 **Relatórios** - Análises e gráficos
- ⚙️ **Configurações** - Dados da empresa e membros
- 📚 **Ajuda** - Base de conhecimento (Playbooks)

---

## 👥 GERENCIAMENTO DE CLIENTES {#clientes}

### Acessar Clientes

1. Clique em **👥 Clientes** no menu lateral
2. Você verá a **lista de todos os clientes** cadastrados

### Cadastrar Novo Cliente

1. Clique no botão **+ Novo Cliente** (canto superior direito)
2. Selecione o **tipo de pessoa:**
   - **Pessoa Física** - Indivíduo
   - **Pessoa Jurídica** - Empresa

3. Preencha os dados obrigatórios:
   - **Nome Completo** (PF) ou **Razão Social** (PJ) ⭐
   - **Documento** - CPF ou CNPJ (opcional)
   - **Email** (opcional)
   - **Telefone** (opcional)

4. **Dados Adicionais** (seção "Contatos"):
   - Adicione contatos para o cliente (nome, telefone, email)
   - Clique em **+ Adicionar Contato**

5. **Endereços** (seção "Endereços"):
   - Adicione um ou mais endereços
   - Clique em **+ Adicionar Endereço**
   - Você pode preencher **CEP** e o sistema auto-completa a cidade/estado

6. Clique em **Adicionar Cliente**

### Editar Cliente

1. Na lista de clientes, clique no cliente que deseja editar
2. Clique em **Editar** (canto superior)
3. Faça as alterações necessárias
4. Clique em **Salvar**

### Visualizar Detalhes

Clique em um cliente para ver:
- Histórico de todas as ordens de serviço
- Contatos vinculados
- Endereços cadastrados
- Equipamentos do cliente

---

## 📱 GERENCIAMENTO DE EQUIPAMENTOS {#equipamentos}

### Acessar Equipamentos

1. Clique em **📱 Equipamentos** no menu lateral
2. Você verá a **lista de todos os equipamentos** cadastrados

### Cadastrar Novo Equipamento

1. Clique em **+ Novo Equipamento** (canto superior direito)
2. Preencha os dados:
   - **Cliente** ⭐ - Selecione um cliente existente
   - **Marca** ⭐ - Ex: Dell, HP, Samsung
   - **Modelo** ⭐ - Ex: Inspiron 15, LaserJet Pro
   - **Categoria** ⭐ - Computador, Impressora, Telefone, etc.
   - **Número de Série** - Identificador único (opcional)

3. Clique em **Adicionar Equipamento**

### Dica Importante ⚡

Você pode cadastrar um equipamento **diretamente durante a criação de uma Ordem de Serviço**, sem precisar sair da tela.

---

## 🔧 CRIAÇÃO DE ORDENS DE SERVIÇO {#ordens-servico}

### Acessar Ordens de Serviço

1. Clique em **🔧 Ordens de Serviço** no menu lateral
2. Você verá a **lista de todas as OSs**
3. Pode filtrar por:
   - Status (Aberta, Em Execução, Pronta para Retirada, Entregue)
   - Período

### Criar Nova Ordem de Serviço

1. Clique em **+ Criar OS** (canto superior direito)
2. **Etapa 1: Seleção de Cliente**
   - Selecione um cliente da lista
   - Ou clique em **+ Cliente** para criar um cliente direto

3. **Etapa 2: Seleção de Equipamento**
   - Selecione um equipamento do cliente
   - Ou clique em **+ Equipamento** para cadastrar um novo
   - OU preencha **Descrição do Equipamento** (se não souber o modelo exato)

4. **Etapa 3: Descrever o Problema**
   - Preencha **Problema Relatado** ⭐
   - Detalhe o máximo possível o que o cliente relata
   - Ex: "Tela não acende, mas a máquina liga normalmente"

5. **Etapa 4: Checklist de Recebimento** (opcional)
   - Você pode adicionar itens para verificar no ato do recebimento
   - Ex: "Teclado funciona?", "Bateria carrega?"
   - Clique em **+ Adicionar Item** para cada verificação
   - Você pode usar um **template pré-existente** se disponível

6. Clique em **Criar Rascunho**

### 📌 Após Criar a OS

- A OS é criada com status **Rascunho**
- Você será redirecionado para a página de detalhes
- Agora pode começar a registrar o trabalho

---

## ⚙️ FLUXO DE EXECUÇÃO (TRABALHO) {#execucao}

### Iniciar Execução

Quando estiver pronto para começar a trabalhar no equipamento:

1. Abra a **Ordem de Serviço** desejada
2. Clique em **Iniciar Execução** (botão verde)
3. O sistema pedirá:
   - **Técnico Responsável** ⭐ - Quem vai executar o trabalho
   - O status muda para **Em Execução**

### Registrar Horas de Trabalho

Durante a execução, você vai registrando as horas trabalhadas:

1. Na seção **Registro de Execução**, clique em **+ Adicionar Entrada de Trabalho**
2. Preencha:
   - **Data** - Quando foi feito o trabalho
   - **Técnico** ⭐ - Quem fez
   - **Duração** ⭐ - Quantas horas levou (ex: 2.5 para 2h30min)
   - **Descrição** - O que foi feito (ex: "Limpeza de hardware, diagnosticado HD com bad sectors")
   - **Tipo de Trabalho** - Se foi diagnóstico, reparo, etc.

3. Clique em **Adicionar**

### Registrar Equipamentos Utilizados

Se precisar registrar peças ou componentes usados:

1. Na seção **Peças Utilizadas**, clique em **+ Adicionar Peça**
2. Selecione a peça do seu catálogo
3. Informe a quantidade
4. O valor será calculado automaticamente

---

## 🔍 DIAGNÓSTICO E ORÇAMENTO {#diagnostico-orcamento}

### Registrar Diagnóstico

Depois que você identificou o problema:

1. Na seção **Diagnóstico**, clique em **Registrar Diagnóstico**
2. Preencha (opcional):
   - **Notas Internas** - Informações técnicas
   - **Resumo para o Cliente** - Como explicar para o cliente
3. Clique em **Salvar**

### Criar Orçamento

Um orçamento é a estimativa de quanto custará o reparo:

1. Clique em **+ Orçamento** (próximo ao problema)
2. Preencha os **Itens de Linha**:
   - **Descrição** ⭐ - Ex: "Troca de HD", "Reparo de tela"
   - **Quantidade** ⭐ - Quantos (geralmente 1)
   - **Preço Unitário** ⭐ - O valor de cada unidade
   - Clique em **+ Item** para adicionar mais linhas

3. O **Total** é calculado automaticamente
4. Defina **Validade** do orçamento (quantos dias o cliente tem para aprovar)
5. Clique em **Criar Orçamento**

### Acompanhar Orçamento

- O orçamento aparecerá na seção **Orçamentos**
- Status inicial: **Pendente** (aguardando aprovação do cliente)
- Quando o cliente aprova, muda para **Aprovado**

### ⚠️ Importante

- Você **pode trabalhar sem orçamento** - não é bloqueante
- Mas é recomendado criar orçamento **antes de gastar peças**
- Se o cliente rejeitar, você terá registrado o custo

---

## ✅ TESTES FINAIS {#testes-finais}

### Quando o Trabalho Está Concluído

Quando acabar o reparo, você precisa testar se está funcionando:

1. Clique em **Concluir Execução — Testes Finais**
2. Na seção **Testes Finais**, adicione os testes que fez:
   - Clique em **+ Adicionar Item**
   - **Descrição** - Ex: "Tela acende?"
   - **Status** - ✅ Passou ou ❌ Falhou
   - Clique em **+ Adicionar Item** para mais testes

3. Quando todos os testes estiverem preenchidos, clique em **Concluir**
4. O status da OS muda para **Pronto para Entrega**

### 📌 Checklist de Testes Sugeridos

- Sistema operacional inicia normalmente?
- Bateria carrega (se aplicável)?
- Conectividade (WiFi, Bluetooth) funciona?
- Todos os periféricos funcionam?
- Não há superaquecimento?

---

## 💰 PAGAMENTO E ENTREGA {#pagamento-entrega}

### Registrar Pagamento

Quando o cliente paga:

1. Na seção **Pagamentos**, clique em **+ Pagamento**
2. Preencha:
   - **Valor (R$)** ⭐ - Quanto foi recebido
   - **Forma de Pagamento** ⭐ - Dinheiro, PIX, Cartão, etc.
   - **Observações** - Anotações extras (opcional)
3. Clique em **Confirmar**

### Registrar Entrega

Quando o cliente retira o equipamento:

1. Clique em **Registrar Entrega**
2. Preencha:
   - **Nome de quem está retirando** ⭐ - Identificação do cliente
   - **Observações** - Anotações (opcional)
3. Clique em **Confirmar Entrega**

### 📊 Após Entrega

- O status muda para **Entregue**
- A OS é considerada fechada
- Aparecerá nos relatórios como concluída

---

## 👤 GERENCIAMENTO DE USUÁRIOS {#usuarios}

**Local:** ⚙️ Configurações → Usuários

### Visualizar Usuários

1. Clique em **⚙️ Configurações**
2. Clique na aba **Usuários**
3. Você verá todos os membros da sua assistência técnica

### Convidar Novo Usuário

1. Clique em **+ Convidar Membro**
2. Preencha:
   - **Email** ⭐ - Email do novo técnico
   - **Função** ⭐ - Operador (técnico padrão), Admin (gerenciar sistema)
3. Clique em **Convidar**
4. A pessoa receberá um email com link de acesso

### Editar Usuário

1. Clique no usuário na lista
2. Modifique a função se necessário
3. Clique em **Salvar**

### Remover Usuário

1. Na lista de usuários, encontre o usuário
2. Clique em **Remover**
3. Confirme a exclusão

---

## 📋 AUDITORIA {#auditoria}

**Local:** ⚙️ Configurações → Auditoria

A auditoria registra **todas as operações** feitas no sistema para controle e conformidade.

### O Que É Registrado

- Quem criou/editou/deletou cada registro
- Quando foi feito
- Qual mudança foi realizada
- Qual era o valor anterior e novo

### Visualizar Auditoria

1. Clique em **⚙️ Configurações**
2. Clique em **Auditoria**
3. Você verá um log completo de todas as ações
4. Pode filtrar por:
   - **Data** - Intervalo de datas
   - **Usuário** - Quem fez a ação
   - **Tipo de Ação** - criado, editado, deletado
   - **Recurso** - Ordem de Serviço, Cliente, etc.

### Casos de Uso

- Rastrear quem alterou um valor
- Ver o histórico completo de uma OS
- Conformidade e conformidade legal
- Investigar possíveis erros

---

## 📊 RELATÓRIOS {#relatorios}

**Local:** 📈 Relatórios (menu lateral)

### Tipos de Relatórios Disponíveis

#### 1. **Ordens de Serviço**
- Quantas OSs foram criadas/concluídas no período
- Tempo médio de resolução
- Distribuição por status

#### 2. **Faturamento**
- Receita total do período
- Receita por técnico
- Receita por cliente
- Formas de pagamento

#### 3. **Performance**
- Horas trabalhadas por técnico
- Taxa de conclusão
- Orçamentos aprovados vs. rejeitados

#### 4. **Satisfação**
- Feedback do cliente (se aplicável)

### Como Usar

1. Selecione o **tipo de relatório**
2. Escolha a **data inicial e final**
3. Clique em **Gerar**
4. Os dados aparecem em gráficos e tabelas
5. Clique em **PDF** para baixar (se disponível)

---

## 📚 BASE DE CONHECIMENTO (PLAYBOOKS) {#playbooks}

**Local:** 📚 Ajuda (menu lateral)

### O Que São Playbooks

Guias passo-a-passo criados pela sua empresa para ajudar na resolução de problemas comuns.

### Como Usar

1. Clique em **📚 Ajuda**
2. Você verá uma lista de playbooks por categoria
3. Clique em um playbook para ler o guia completo
4. Siga os passos descritos

### Exemplos de Playbooks

- "Como identificar problemas de HD"
- "Passo a passo para backup de dados"
- "Como resetar BIOS"
- "Diagnóstico de fonte de alimentação"

---

## 🆘 DICAS E TROUBLESHOOTING {#troubleshooting}

### Problema: Não consigo encontrar um cliente

**Solução:**
1. Verifique a grafia do nome
2. Use a busca na página de clientes
3. Se ainda não existir, crie um novo cliente

### Problema: Esqueci de registrar horas de trabalho

**Solução:**
1. Abra a OS
2. Clique em **+ Adicionar Entrada de Trabalho**
3. Preecha a data retroativa (data do trabalho realizado)
4. O sistema aceita datas passadas

### Problema: Não consigo criar orçamento

**Solução:**
1. Verifique se a OS está em status **Em Execução**
2. Se não estiver, clique em **Iniciar Execução** primeiro
3. Garanta que tem um técnico atribuído

### Problema: Não consigo entregar a OS

**Solução:**
1. Verifique se completou os **Testes Finais**
2. Depois que concluir os testes, o status muda para **Pronto para Entrega**
3. Só então pode registrar entrega

### Problema: A página está carregando devagar

**Solução:**
1. Verifique sua conexão de internet
2. Tente atualizar a página (F5 ou Ctrl+Shift+R)
3. Tente em outro navegador

### Problema: Esqueci minha senha

**Solução:**
1. Na tela de login, clique em **Esqueceu a senha?**
2. Informe seu email
3. Verifique seu email para instruções
4. Siga o link para resetar

---

## 📞 SUPORTE

Caso tenha dúvidas não cobertas por este manual:

1. Consulte os **Playbooks** na seção **Ajuda**
2. Entre em contato com o administrador do sistema
3. Verifique a auditoria para entender ações anteriores

---

## 📋 CHECKLIST: FLUXO COMPLETO DE UMA OS

Use este checklist para garantir que todas as etapas foram concluídas:

- [ ] **Cliente criado** - Dados corretos
- [ ] **Equipamento registrado** - Marca, modelo, serial
- [ ] **Ordem de Serviço criada** - Problema bem descrito
- [ ] **Execução iniciada** - Técnico atribuído
- [ ] **Horas registradas** - Todas as entradas de trabalho
- [ ] **Diagnóstico documentado** - Anotações técnicas
- [ ] **Orçamento criado** - Cliente ciente do valor
- [ ] **Orçamento aprovado** - Cliente concordou
- [ ] **Trabalho concluído** - Testes passaram
- [ ] **Pagamento registrado** - Valor recebido
- [ ] **Entrega realizada** - Cliente retirou o equipamento

---

**Versão 1.0 - Junho 2026**  
Manual de Utilização do Sistema de Ordem de Serviço
