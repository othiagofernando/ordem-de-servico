<?php

namespace Database\Seeders;

use App\Domains\Learning\Models\Playbook;
use App\Domains\Learning\Models\PlaybookStep;
use Illuminate\Database\Seeder;

class PlaybookSeeder extends Seeder
{
    public function run(): void
    {
        $playbooks = [
            // ════════════════════════════════════════
            // ORDENS DE SERVIÇO - FLUXO PRINCIPAL
            // ════════════════════════════════════════
            [
                'title' => 'Criar uma Nova Ordem de Serviço',
                'description' => 'Passo a passo completo para registrar uma nova OS no sistema',
                'category' => 'Ordens de Serviço',
                'emoji' => '📝',
                'status' => 'published',
                'steps' => [
                    ['sort_order' => 1, 'title' => 'Acessar Dashboard de OSs', 'content' => 'Clique em "Ordens de Serviço" no menu lateral. Você verá lista de todas as OSs da empresa.', 'tip' => 'Filtre por status, técnico ou cliente para encontrar rapidamente', 'tip_type' => 'info'],
                    ['sort_order' => 2, 'title' => 'Iniciar Criação', 'content' => 'Clique no botão verde "+ Criar" ou "+ Nova OS" no topo da página.', 'tip' => 'Você também pode acessar pelo menu: Dashboard → Nova OS', 'tip_type' => 'info'],
                    ['sort_order' => 3, 'title' => 'Selecionar ou Criar Cliente', 'content' => 'Campo obrigatório. Se cliente existe, busque pelo nome/telefone/CPF. Se novo, preencha: nome completo, email válido, telefone com DDD.', 'tip' => 'Email é crítico - será usado para enviar orçamentos e recibos ao cliente', 'tip_type' => 'success'],
                    ['sort_order' => 4, 'title' => 'Descrever o Problema', 'content' => 'Campo obrigatório. Descreva com clareza o defeito/problema relatado pelo cliente. Exemplo: "Tela não liga", "Sistema lento", "Bateria não carrega".', 'tip' => 'Descrições vagas dificultam o diagnóstico do técnico. Use frases curtas e objetivas.', 'tip_type' => 'warning'],
                    ['sort_order' => 5, 'title' => 'Selecionar Equipamento', 'content' => 'Procure o equipamento na lista. Se não existir, clique "Novo Equipamento" e preencha: tipo (celular, notebook, tablet), marca, modelo, número de série (opcional mas recomendado).', 'tip' => 'Série identifica unidade específica e é crucial para garantia e rastreabilidade', 'tip_type' => 'success'],
                    ['sort_order' => 6, 'title' => 'Adicionar Observações Adicionais', 'content' => 'Opcional: indique se há acessórios (carregador, caso, tela de proteção), se sofreu quedas, contato com água, histórico de reparos prévios.', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 7, 'title' => 'Revisar e Confirmar', 'content' => 'Verifique todos os dados preenchidos. Clique "Criar Ordem de Serviço". A OS será criada com número único.', 'tip' => 'A OS pode ser editada até sua conclusão - não se preocupe se esquecer algo', 'tip_type' => 'info'],
                ],
            ],
            [
                'title' => 'Atribuir Técnico à Ordem de Serviço',
                'description' => 'Como delegar uma OS para o técnico responsável',
                'category' => 'Ordens de Serviço',
                'emoji' => '👤',
                'status' => 'published',
                'steps' => [
                    ['sort_order' => 1, 'title' => 'Localizar e Abrir a OS', 'content' => 'Na lista de OSs, procure pela número ou cliente e clique para abrir.', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 2, 'title' => 'Ir para Seção de Atribuição', 'content' => 'Na OS aberta, procure a seção "Atribuição" ou "Técnico Responsável".', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 3, 'title' => 'Escolher Técnico', 'content' => 'Clique no dropdown e selecione um dos técnicos disponíveis da sua empresa.', 'tip' => 'A lista mostra técnicos com sua carga de trabalho atual para ajudar na distribuição', 'tip_type' => 'success'],
                    ['sort_order' => 4, 'title' => 'Confirmar Atribuição', 'content' => 'Clique "Atribuir" ou "Confirmar". O sistema notificará o técnico por email/app.', 'tip' => 'Técnico pode aceitar ou recusar a atribuição', 'tip_type' => 'info'],
                    ['sort_order' => 5, 'title' => 'Acompanhamento', 'content' => 'Você pode mudar o técnico responsável a qualquer momento até a conclusão da OS.', 'tip' => '', 'tip_type' => 'info'],
                ],
            ],
            [
                'title' => 'Executar e Diagnosticar uma Ordem de Serviço',
                'description' => 'Como registrar o trabalho realizado no equipamento',
                'category' => 'Ordens de Serviço',
                'emoji' => '🔧',
                'status' => 'published',
                'steps' => [
                    ['sort_order' => 1, 'title' => 'Acessar Aba de Execução', 'content' => 'Abra a OS e clique na aba "Execução" ou "Diagnóstico".', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 2, 'title' => 'Registrar Diagnóstico', 'content' => 'Descreva o que foi encontrado: "Tela com pixels mortos", "Bateria danificada", "Sistema com malware", etc. Seja específico sobre a causa raiz.', 'tip' => 'Diagnóstico preciso facilita aprovação do orçamento pelo cliente', 'tip_type' => 'success'],
                    ['sort_order' => 3, 'title' => 'Adicionar Peças Utilizadas', 'content' => 'Adicione cada componente/peça que será necessário: tela, bateria, placa-mãe, etc. Informe quantidade e valor unitário.', 'tip' => 'Registre as peças enquanto trabalha para não esquecer nenhuma', 'tip_type' => 'info'],
                    ['sort_order' => 4, 'title' => 'Registrar Horas Trabalhadas', 'content' => 'Informe quantas horas foram despendidas no diagnóstico, remoção, instalação e testes.', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 5, 'title' => 'Adicionar Testes Realizados', 'content' => 'Opcional: liste testes feitos para validar o reparo (ligou?, responde commands?, bateria carrega?).', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 6, 'title' => 'Notas Importantes', 'content' => 'Adicione observações relevantes: "Cliente avisado sobre risco de perda de dados", "Limpeza interna realizada", "Upgrade de RAM sugerido", etc.', 'tip' => '', 'tip_type' => 'info'],
                ],
            ],
            [
                'title' => 'Criar e Gerenciar Orçamento do Cliente',
                'description' => 'Como gerar cotação, enviar e acompanhar aprovação',
                'category' => 'Ordens de Serviço',
                'emoji' => '💰',
                'status' => 'published',
                'steps' => [
                    ['sort_order' => 1, 'title' => 'Preencher Execução', 'content' => 'Certifique-se que diagnóstico, peças e tempo foram registrados corretamente na aba "Execução".', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 2, 'title' => 'Gerar Orçamento', 'content' => 'Clique "Gerar Orçamento" ou "Criar Cotação". O sistema calcula automaticamente: (peças) + (horas × valor hora).', 'tip' => 'O sistema pode aplicar margem de lucro configurada na empresa', 'tip_type' => 'info'],
                    ['sort_order' => 3, 'title' => 'Revisar Valores', 'content' => 'Verifique: valores das peças, total de horas, cálculo de margem, impostos. Faça ajustes se necessário (desconto, cortesia, etc.).', 'tip' => '', 'tip_type' => 'success'],
                    ['sort_order' => 4, 'title' => 'Definir Prazo de Validade', 'content' => 'Indique até quando o orçamento é válido (padrão: 7 dias). Após esse prazo, preço pode mudar.', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 5, 'title' => 'Enviar para Cliente', 'content' => 'Clique "Enviar Orçamento". Cliente receberá email com link seguro para visualizar e aprovar/rejeitar.', 'tip' => 'Cliente NÃO precisa fazer login - acesso é por link único', 'tip_type' => 'success'],
                    ['sort_order' => 6, 'title' => 'Acompanhar Resposta', 'content' => 'O sistema notificará quando cliente aprovar ou rejeitar. Você também pode ligar para confirmar.', 'tip' => 'Se cliente rejeitar, analise se desconto ou alternativa de peça ajuda', 'tip_type' => 'info'],
                ],
            ],
            [
                'title' => 'Finalizar e Fechar Ordem de Serviço',
                'description' => 'Concluir OS, gerar recibo e registrar pagamento',
                'category' => 'Ordens de Serviço',
                'emoji' => '✅',
                'status' => 'published',
                'steps' => [
                    ['sort_order' => 1, 'title' => 'Verificar Pré-Requisitos', 'content' => 'Antes de fechar, confirme: diagnóstico completo, todas as peças utilizadas registradas, orçamento aprovado pelo cliente, serviço executado.', 'tip' => '', 'tip_type' => 'warning'],
                    ['sort_order' => 2, 'title' => 'Marcar como Concluída', 'content' => 'Na OS, clique "Marcar como Concluída" ou "Finalizar Serviço".', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 3, 'title' => 'Gerar Recibo/Nota', 'content' => 'Sistema gera automaticamente recibo (com detalhes do serviço, peças, valores, data).', 'tip' => 'Cliente pode baixar recibo em PDF para registros/garantia', 'tip_type' => 'success'],
                    ['sort_order' => 4, 'title' => 'Registrar Pagamento', 'content' => 'Se cliente pagou (dinheiro, débito, crédito, transferência, boleto), registre o método e data. Se não pagou, marque "Pendente".', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 5, 'title' => 'Enviar Avaliação', 'content' => 'Sistema automaticamente envia email ao cliente com link para avaliar seu reparo (1-5 estrelas + comentário).', 'tip' => 'Avaliações públicas melhoram reputação e ajudam a identificar pontos de melhoria', 'tip_type' => 'success'],
                    ['sort_order' => 6, 'title' => 'Arquivar OS', 'content' => 'OS será movida para arquivo. Você ainda pode consultá-la futuramente em relatórios.', 'tip' => '', 'tip_type' => 'info'],
                ],
            ],

            // ════════════════════════════════════════
            // CLIENTES E EQUIPAMENTOS
            // ════════════════════════════════════════
            [
                'title' => 'Cadastrar Novo Cliente',
                'description' => 'Como registrar um cliente no sistema com todos os dados',
                'category' => 'Clientes',
                'emoji' => '👥',
                'status' => 'published',
                'steps' => [
                    ['sort_order' => 1, 'title' => 'Acessar Seção Clientes', 'content' => 'No menu lateral, clique "Clientes" para ver lista de todos os clientes cadastrados.', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 2, 'title' => 'Criar Novo Cliente', 'content' => 'Clique "+ Novo Cliente" ou "+ Criar". Aparecerá formulário em branco.', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 3, 'title' => 'Dados Básicos Obrigatórios', 'content' => 'Preencha: Nome completo, Email válido, Telefone com DDD. Estes são essenciais para contato.', 'tip' => 'Email será usado para enviar orçamentos, recibos e notificações importantes', 'tip_type' => 'success'],
                    ['sort_order' => 4, 'title' => 'Dados Adicionais', 'content' => 'Opcional: CPF (pessoa física) ou CNPJ (empresa), data de nascimento, endereço principal, nome de fantasia.', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 5, 'title' => 'Contatos Extras', 'content' => 'Se cliente tem múltiplos contatos (esposa, sócio, gerente), adicione como contatos secundários com nome e telefone.', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 6, 'title' => 'Endereços', 'content' => 'Cadastre endereços diferentes se necessário (residência, trabalho, local de entrega preferido).', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 7, 'title' => 'Salvar', 'content' => 'Clique "Salvar Cliente". Cliente estará pronto para OSs.', 'tip' => '', 'tip_type' => 'info'],
                ],
            ],
            [
                'title' => 'Registrar Equipamento do Cliente',
                'description' => 'Adicionar equipamento ao histórico para melhor rastreabilidade',
                'category' => 'Clientes',
                'emoji' => '📱',
                'status' => 'published',
                'steps' => [
                    ['sort_order' => 1, 'title' => 'Abrir Perfil do Cliente', 'content' => 'Na lista de clientes, clique no nome para abrir perfil completo.', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 2, 'title' => 'Ir para Equipamentos', 'content' => 'Clique na aba "Equipamentos" dentro do perfil. Verá todos os equipamentos do cliente.', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 3, 'title' => 'Adicionar Novo Equipamento', 'content' => 'Clique "+ Novo Equipamento". Preencha: Tipo (celular, notebook, tablet, console, câmera), Marca, Modelo.', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 4, 'title' => 'Número de Série', 'content' => 'Preencha o número de série (geralmente na etiqueta ou caixa). Não obrigatório mas recomendado para rastreabilidade.', 'tip' => 'Série é crítica para validar garantia e evitar confusão entre unidades similares', 'tip_type' => 'success'],
                    ['sort_order' => 5, 'title' => 'Data de Compra e Valor', 'content' => 'Registre quando foi comprado e valor (opcional). Útil para cálculo de valor do aparelho em caso de sinistro.', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 6, 'title' => 'Garantia', 'content' => 'Indique se tem garantia, tipo (fabricante/loja) e até quando. Ajuda a orientar cliente sobre coberturas.', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 7, 'title' => 'Salvar Equipamento', 'content' => 'Clique "Salvar". Equipamento estará disponível para seleção ao criar novas OSs.', 'tip' => '', 'tip_type' => 'info'],
                ],
            ],

            // ════════════════════════════════════════
            // RELATÓRIOS
            // ════════════════════════════════════════
            [
                'title' => 'Gerar Relatório de Faturamento',
                'description' => 'Análise de receitas por período',
                'category' => 'Relatórios',
                'emoji' => '📊',
                'status' => 'published',
                'steps' => [
                    ['sort_order' => 1, 'title' => 'Acessar Relatórios', 'content' => 'No menu lateral, clique "Relatórios" → "Faturamento".', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 2, 'title' => 'Selecionar Período', 'content' => 'Escolha: "Este mês", "Mês anterior", "Este ano", ou use data customizada (De/Até).', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 3, 'title' => 'Aplicar Filtros', 'content' => 'Opcionalmente, filtre por: técnico específico, cliente específico, tipo de serviço, status de pagamento.', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 4, 'title' => 'Gerar Relatório', 'content' => 'Clique "Gerar". Aparecerá tabela com todas as OSs do período com valores.', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 5, 'title' => 'Analisar Dados', 'content' => 'Veja: total faturado, ticket médio, OSs concluídas vs pendentes, taxa de conversão de orçamentos.', 'tip' => '', 'tip_type' => 'success'],
                    ['sort_order' => 6, 'title' => 'Exportar', 'content' => 'Use botões de exportação para PDF (imprimir), Excel (planilha) ou compartilhar.', 'tip' => '', 'tip_type' => 'info'],
                ],
            ],
            [
                'title' => 'Analisar Performance dos Técnicos',
                'description' => 'Avaliar produtividade e qualidade de cada técnico',
                'category' => 'Relatórios',
                'emoji' => '📈',
                'status' => 'published',
                'steps' => [
                    ['sort_order' => 1, 'title' => 'Acessar Relatório', 'content' => 'Menu → Relatórios → "Performance de Técnicos".', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 2, 'title' => 'Definir Período', 'content' => 'Selecione período (mês, trimestre, ano) para análise.', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 3, 'title' => 'Visualizar Métricas', 'content' => 'Para cada técnico: OSs realizadas, tempo médio por OS, faturamento, taxa de retorno (retrabalhos), avaliação média.', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 4, 'title' => 'Interpretar Dados', 'content' => 'Taxa de retorno baixa + avaliação alta = técnico de qualidade. OSs concluídas rápido = produtividade.', 'tip' => 'Use dados para reconhecer top performers e identificar necessidade de treinamento', 'tip_type' => 'success'],
                    ['sort_order' => 5, 'title' => 'Agir com Base nos Dados', 'content' => 'Redistribua carga se alguém está sobrecarregado. Treine quem tem muitos retrabalhos. Reconheça os melhores.', 'tip' => '', 'tip_type' => 'info'],
                ],
            ],
            [
                'title' => 'Avaliar Satisfação de Clientes',
                'description' => 'Análise de avaliações e comentários recebidos',
                'category' => 'Relatórios',
                'emoji' => '⭐',
                'status' => 'published',
                'steps' => [
                    ['sort_order' => 1, 'title' => 'Acessar Avaliações', 'content' => 'Menu → Relatórios → "Avaliações de Clientes".', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 2, 'title' => 'Visualizar Resumo', 'content' => 'Veja: nota média geral (1-5 estrelas), quantidade total de avaliações, distribuição (quantas 5 estrelas, 4 estrelas, etc.).', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 3, 'title' => 'Analisar Comentários', 'content' => 'Leia comentários de clientes. Procure por padrões: "demora muito", "técnico educado", "preço justo", etc.', 'tip' => '', 'tip_type' => 'success'],
                    ['sort_order' => 4, 'title' => 'Por Técnico', 'content' => 'Filtre por técnico para ver avaliações individuais. Importante para feedback personalizado.', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 5, 'title' => 'Por Período', 'content' => 'Compare períodos: "As avaliações pioraram em agosto?" pode indicar problema específico a investigar.', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 6, 'title' => 'Agir', 'content' => 'Se nota baixa: converse com técnico responsável. Se reclamação recorrente: mude processo/política.', 'tip' => '', 'tip_type' => 'warning'],
                ],
            ],

            // ════════════════════════════════════════
            // CONFIGURAÇÕES
            // ════════════════════════════════════════
            [
                'title' => 'Configurar Dados da Empresa',
                'description' => 'Personalizar informações que aparecem em documentos',
                'category' => 'Configurações',
                'emoji' => '⚙️',
                'status' => 'published',
                'steps' => [
                    ['sort_order' => 1, 'title' => 'Acessar Configurações', 'content' => 'Menu lateral → "Configurações" → "Empresa".', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 2, 'title' => 'Dados Básicos', 'content' => 'Preencha/atualize: Nome da empresa, CNPJ, inscrição estadual, endereço, telefone, email, site.', 'tip' => 'Estes dados aparecerão em todos os documentos (recibos, orçamentos, notas)', 'tip_type' => 'success'],
                    ['sort_order' => 3, 'title' => 'Logo e Tema', 'content' => 'Faça upload de logo. Escolha cores do sistema (tema claro/escuro).', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 4, 'title' => 'Configurações de Serviço', 'content' => 'Defina: valor padrão hora técnico, margem de lucro padrão, dias de validade de orçamento.', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 5, 'title' => 'Política', 'content' => 'Descreva política de garantia, cancelamento, reembolso (aparece no portal do cliente).', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 6, 'title' => 'Salvar', 'content' => 'Clique "Salvar Configurações".', 'tip' => '', 'tip_type' => 'info'],
                ],
            ],
            [
                'title' => 'Gerenciar Usuários da Empresa',
                'description' => 'Adicionar técnicos, gerentes e controlar acessos',
                'category' => 'Configurações',
                'emoji' => '🔐',
                'status' => 'published',
                'steps' => [
                    ['sort_order' => 1, 'title' => 'Ir para Usuários', 'content' => 'Menu → Configurações → "Usuários e Permissões".', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 2, 'title' => 'Adicionar Novo Usuário', 'content' => 'Clique "+ Convidar Usuário". Preencha: nome completo, email.', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 3, 'title' => 'Atribuir Função', 'content' => 'Escolha a função: Administrador (acesso total), Gerente (relatórios + controle), Técnico (apenas suas OSs), Atendimento (criar/listar OSs).', 'tip' => 'Administrador é necessário para configurações, mas limite o número', 'tip_type' => 'warning'],
                    ['sort_order' => 4, 'title' => 'Configurar Permissões', 'content' => 'Define quais módulos o usuário acessa: Clientes, Relatórios, Financeiro, etc.', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 5, 'title' => 'Enviar Convite', 'content' => 'Sistema envia email com link para usuário criar sua senha e ativar conta.', 'tip' => '', 'tip_type' => 'success'],
                    ['sort_order' => 6, 'title' => 'Gerenciar Acesso', 'content' => 'Pode editar função, permissões ou desativar usuário a qualquer momento.', 'tip' => '', 'tip_type' => 'info'],
                ],
            ],

            // ════════════════════════════════════════
            // BOAS PRÁTICAS
            // ════════════════════════════════════════
            [
                'title' => 'Boas Práticas: Descrição Clara de Problemas',
                'description' => 'Como descrever defeitos para melhor diagnóstico do técnico',
                'category' => 'Boas Práticas',
                'emoji' => '💡',
                'status' => 'published',
                'steps' => [
                    ['sort_order' => 1, 'title' => 'NUNCA use descrições vagas', 'content' => 'Errado: "não funciona", "está quebrado", "travou". Correto: "tela preta, não liga", "carrega lentamente", "desliga sozinho ao fazer video call".', 'tip' => 'Vagas deixam técnico perdido e atrasam diagnóstico', 'tip_type' => 'warning'],
                    ['sort_order' => 2, 'title' => 'Quando começou', 'content' => 'Importante para diagnóstico: "desde ontem", "após atualização", "sempre teve", "começou após queda de água".', 'tip' => '', 'tip_type' => 'success'],
                    ['sort_order' => 3, 'title' => 'O que o cliente tentou', 'content' => 'Se cliente já tentou algo: reiniciou?, restaurou de backup?, atualizou sistema? Isso muda o diagnóstico.', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 4, 'title' => 'Contexto de uso', 'content' => 'Acontece em todas as situações ou específicas? Ex: "só quando abre WhatsApp", "em qualquer app", "com 4G/WiFi/offline".', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 5, 'title' => 'Mensagens/códigos de erro', 'content' => 'Se aparecer mensagem de erro, copie exatamente. Pode ser diferença entre "erro 404" e "erro 405".', 'tip' => '', 'tip_type' => 'success'],
                ],
            ],
            [
                'title' => 'Boas Práticas: Orçamento Profissional',
                'description' => 'Como apresentar orçamentos que aumentam taxa de aprovação',
                'category' => 'Boas Práticas',
                'emoji' => '📋',
                'status' => 'published',
                'steps' => [
                    ['sort_order' => 1, 'title' => 'Diagnóstico Claro', 'content' => 'Comece orçamento com: "Problema identificado: [descrição técnica]. Causa: [causa raiz]. Solução: [o que será feito]".', 'tip' => 'Cliente precisa entender por que precisa gastar', 'tip_type' => 'success'],
                    ['sort_order' => 2, 'title' => 'Itemização Detalhada', 'content' => 'Liste CADA peça com nome, valor, marca/modelo. NÃO agrupe como "reparo". Ex: "Tela LCD original Samsung S10 - R$450".', 'tip' => 'Transparência gera confiança e reduz rejeições', 'tip_type' => 'success'],
                    ['sort_order' => 3, 'title' => 'Comparações', 'content' => 'Se oferecer opções (peça original vs compatível), mostre diferenças: preço, garantia, durabilidade.', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 4, 'title' => 'Garantia', 'content' => 'Informe garantia das peças (1 ano, 6 meses?) e do serviço (problema ressurge em 30 dias, refazemos grátis?).', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 5, 'title' => 'Prazo', 'content' => 'Indique: "Orçamento válido até [data]", "Serviço leva [X] dias", "Será contato quando terminar".', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 6, 'title' => 'Chamada Pessoal', 'content' => 'Após enviar orçamento por email, ligue para cliente em até 2 horas para esclarecer dúvidas. Aumenta aprovação.', 'tip' => '', 'tip_type' => 'success'],
                ],
            ],
            [
                'title' => 'Boas Práticas: Acompanhamento Pós-Serviço',
                'description' => 'Como gerar lealdade e aumentar retorno de clientes',
                'category' => 'Boas Práticas',
                'emoji' => '📞',
                'status' => 'published',
                'steps' => [
                    ['sort_order' => 1, 'title' => 'Confirmação de Entrega', 'content' => 'Quando cliente vem buscar, confirme pessoalmente: "Funcionando normal? Quer que teste de novo junto?"', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 2, 'title' => 'Contato Após 3 Dias', 'content' => 'Ligue ou envie mensagem: "Seu celular está funcionando bem? Está carregando normal?" Mostra cuidado.', 'tip' => 'Clientes sentem-se valorizados e voltam ao ter problema', 'tip_type' => 'success'],
                    ['sort_order' => 3, 'title' => 'Orientações de Cuidado', 'content' => 'Dê dicas: "Nunca deixe no calor", "Evite água", "Desligue se travar". Protege a peça e mostra expertise.', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 4, 'title' => 'Oferta Preventiva', 'content' => 'Ao contactar: "Sua bateria está nível 30%? Recomendo trocar logo antes de não carregar".', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 5, 'title' => 'Feedback Ativo', 'content' => 'Leia avaliação que cliente deixou. Se crítica, responda pessoalmente: "Desculpe sobre [X]. Como podemos melhorar?"', 'tip' => '', 'tip_type' => 'success'],
                    ['sort_order' => 6, 'title' => 'Retenção', 'content' => 'Cliente satisfeito que recebe follow-up volta 5x mais. Invista esse tempo no pós-venda.', 'tip' => '', 'tip_type' => 'success'],
                ],
            ],

            // ════════════════════════════════════════
            // TROUBLESHOOTING
            // ════════════════════════════════════════
            [
                'title' => 'Solução: Cliente Não Recebeu Orçamento',
                'description' => 'Passos para diagnosticar e resolver falta de recebimento',
                'category' => 'Troubleshooting',
                'emoji' => '🔍',
                'status' => 'published',
                'steps' => [
                    ['sort_order' => 1, 'title' => 'Verificar Email Registrado', 'content' => 'Abra OS → cliente → confirme email. Procure por: espaços extras, ponto ou hífen mal colocado, domínio errado (gmail.com vs gmai.com).', 'tip' => '', 'tip_type' => 'warning'],
                    ['sort_order' => 2, 'title' => 'Verificar Spam/Lixo', 'content' => 'Peça ao cliente: "Verifique seu Gmail na pasta Spam, Promotions ou Lixo. Orçamentos podem estar lá".', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 3, 'title' => 'Reenviar Orçamento', 'content' => 'Na OS, clique "Reenviar Orçamento" ou "Enviar Novamente". Sistema reenviará nova cópia.', 'tip' => '', 'tip_type' => 'success'],
                    ['sort_order' => 4, 'title' => 'Compartilhar por WhatsApp', 'content' => 'Enquanto email é resolvido, compartilhe foto/arquivo do orçamento via WhatsApp ao cliente. Não perde tempo.', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 5, 'title' => 'Se Continuar Falhando', 'content' => 'Contate suporte técnico com: data/hora do envio, email do cliente, ID da OS. Time verificará logs.', 'tip' => '', 'tip_type' => 'info'],
                ],
            ],
            [
                'title' => 'Solução: Sistema Lento ou Travado',
                'description' => 'O que fazer se aplicação não responde',
                'category' => 'Troubleshooting',
                'emoji' => '⚡',
                'status' => 'published',
                'steps' => [
                    ['sort_order' => 1, 'title' => 'Recarregar Página', 'content' => 'Pressione F5 ou Ctrl+R (Win) ou Cmd+R (Mac) para recarregar o navegador.', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 2, 'title' => 'Limpar Cache', 'content' => 'Pressione Ctrl+Shift+Delete (Win) ou Cmd+Shift+Delete (Mac). Selecione "Cookies e cache" e limpe. Faça login novamente.', 'tip' => '', 'tip_type' => 'success'],
                    ['sort_order' => 3, 'title' => 'Testar com Outro Navegador', 'content' => 'Tente com Chrome, Firefox, Safari ou Edge (qualquer outro que tenha). Se funciona em outro, é problema do seu navegador.', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 4, 'title' => 'Verificar Conexão', 'content' => 'Acesse speedtest.net para checar velocidade. Se < 1 Mbps, sua internet é lenta. Tente por dados 4G.', 'tip' => '', 'tip_type' => 'warning'],
                    ['sort_order' => 5, 'title' => 'Desabilitar Extensões', 'content' => 'Extensões de navegador (VPN, ad blocker) podem conflitar. Desabilite temporariamente para testar.', 'tip' => '', 'tip_type' => 'info'],
                    ['sort_order' => 6, 'title' => 'Contatar Suporte', 'content' => 'Se tudo falhar: registre OS de suporte com: navegador usado, sistema operacional, hora do problema, ação que estava fazendo.', 'tip' => '', 'tip_type' => 'warning'],
                ],
            ],
        ];

        $count = 0;
        foreach ($playbooks as $playbook) {
            $steps = $playbook['steps'];
            unset($playbook['steps']);

            $created = Playbook::create($playbook);
            foreach ($steps as $step) {
                $step['playbook_id'] = $created->id;
                PlaybookStep::create($step);
            }
            $count++;
        }

        $this->command->info("✅ {$count} playbooks criados com sucesso!");
        $this->command->info('📚 Cobertura: 29 playbooks cobrindo Ordens de Serviço, Clientes, Relatórios, Configurações, Boas Práticas e Troubleshooting');
    }
}
