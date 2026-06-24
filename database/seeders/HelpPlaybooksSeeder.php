<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HelpPlaybooksSeeder extends Seeder
{
    /**
     * Run the database seeds for the help/documentation playbooks.
     * Creates playbooks based on docs/HELP.md content.
     */
    public function run(): void
    {
        $playbooks = [
            // 1. Bem-vindo
            [
                'title' => 'Bem-vindo ao Sistema',
                'category' => 'Introdução',
                'description' => 'Conheça as principais funcionalidades e features do Sistema de Ordem de Serviço',
                'steps' => [
                    ['order' => 1, 'title' => 'Entender o propósito do sistema', 'description' => 'O Sistema de Ordem de Serviço foi desenvolvido para gerenciar todo o ciclo de vida de um equipamento em manutenção, desde a entrada até a saída. É essencial entender que você está trabalhando em um fluxo integrado que conecta clientes, equipamentos, diagnósticos, orçamentos, trabalho e entregas.', 'tips' => 'Pense no sistema como um assistente que ajuda você a não esquecer de nada durante o processo de reparo.'],
                    ['order' => 2, 'title' => 'Explorar funcionalidades principais', 'description' => 'As principais funcionalidades incluem: Cadastro de clientes (PF e PJ), Cadastro de equipamentos, Criação e acompanhamento de ordens de serviço, Registro de trabalho realizado, Diagnóstico e orçamento, Gerenciamento de pagamentos, Auditoria completa de operações, e Base de conhecimento com guias práticos (playbooks).', 'tips' => 'Não é necessário dominar tudo de uma vez. Comece pelos fluxos que você mais usa.'],
                    ['order' => 3, 'title' => 'Reconhecer os papéis no sistema', 'description' => 'Existem dois papéis principais: Operador (técnico padrão) e Admin (gerencia sistema, usuários, configurações). Cada papel tem diferentes permissões no sistema. Como técnico, você é provavelmente um Operador.', 'tips' => 'Se precisar fazer algo que está bloqueado, peça ao Admin da sua assistência técnica.'],
                    ['order' => 4, 'title' => 'Conhecer a importância da auditoria', 'description' => 'O sistema registra todas as operações (criação, edição, deleção) para controle e conformidade. Isso significa que tudo que você faz fica registrado. Isso é bom porque: 1) Você pode investigar quando algo mudou, 2) Há responsabilidade clara, 3) É fácil recuperar informações perdidas.', 'tips' => 'Não tenha medo de cometer erros - o sistema ajuda a rastreá-los e corrigi-los.'],
                    ['order' => 5, 'title' => 'Explorar a base de conhecimento', 'description' => 'Você tem acesso a playbooks (guias passo-a-passo) que ajudam na resolução de problemas comuns. Sempre que tiver dúvida sobre como fazer algo, acesse a seção "Ajuda" (📚) para consultar um playbook relevante.', 'tips' => 'Playbooks são seus aliados! Use-os sempre que ficar em dúvida.'],
                ],
            ],

            // 2. Primeiro Acesso
            [
                'title' => 'Realizar Primeiro Acesso',
                'category' => 'Onboarding',
                'description' => 'Guia passo-a-passo para realizar seu primeiro login e navegar pelo sistema',
                'steps' => [
                    ['order' => 1, 'title' => 'Acessar a plataforma', 'description' => 'Abra seu navegador de internet (Chrome, Firefox, Safari) e digite o endereço da plataforma fornecido pelo administrador. Você será redirecionado automaticamente para a tela de login.', 'tips' => 'Use um navegador atualizado para melhor compatibilidade. Recomendamos Chrome ou Firefox.'],
                    ['order' => 2, 'title' => 'Preencher credenciais', 'description' => 'Na tela de login, insira seu email e senha. Verifique se está digitando corretamente, pois as senhas são sensíveis a maiúsculas/minúsculas.', 'tips' => 'Se esqueceu a senha, clique em "Esqueceu a senha?" para receber um email de recuperação.'],
                    ['order' => 3, 'title' => 'Selecionar empresa', 'description' => 'Se você trabalha em múltiplas assistências técnicas, o sistema pedirá para selecionar qual empresa você deseja acessar. Escolha a empresa correta e clique em "Confirmar".', 'tips' => 'Você pode trocar de empresa a qualquer momento pelo menu do usuário (canto superior direito).'],
                    ['order' => 4, 'title' => 'Conhecer a interface', 'description' => 'Você será redirecionado para o Dashboard. No lado esquerdo, você verá o menu de navegação com as principais funcionalidades: Ordens de Serviço, Clientes, Equipamentos, Relatórios, Configurações e Ajuda.', 'tips' => 'O menu pode ser colapsado clicando no ícone do menu para ganhar mais espaço.'],
                    ['order' => 5, 'title' => 'Revisar notificações', 'description' => 'No canto superior direito, você verá um ícone de sino para notificações e um ícone de usuário para seu perfil. Clique no sino para ver atividades recentes.', 'tips' => 'Notificações importantes aparecem aqui, como quando um cliente aprova um orçamento.'],
                    ['order' => 6, 'title' => 'Explorar o Dashboard', 'description' => 'O Dashboard mostra um resumo das operações: OSs em aberto, ordens prontas para retirada, orçamentos pendentes e faturamento do mês. Use isso como seu ponto de partida diário.', 'tips' => 'Atualize a página se os números não estiverem atualizados em tempo real.'],
                ],
            ],

            // 3. Dashboard
            [
                'title' => 'Entender o Dashboard',
                'category' => 'Introdução',
                'description' => 'Aprenda o que cada informação do Dashboard significa e como usá-lo',
                'steps' => [
                    ['order' => 1, 'title' => 'Visualizar OSs em aberto', 'description' => 'A seção "Ordens de Serviço em Aberto" mostra quantas OSs estão aguardando ação. Essas são ordens que ainda não foram entregues ou finalizadas.', 'tips' => 'Este número deve ser seu foco principal. Quanto maior, mais trabalho pendente você tem.'],
                    ['order' => 2, 'title' => 'Verificar ordens prontas', 'description' => 'A seção "Ordens Prontas para Retirada" mostra equipamentos que já foram reparados e estão aguardando o cliente vir buscar.', 'tips' => 'Contate o cliente se uma ordem está pronta há muito tempo sem retirada.'],
                    ['order' => 3, 'title' => 'Acompanhar orçamentos pendentes', 'description' => 'Mostra quantos orçamentos ainda estão aguardando aprovação do cliente. Estes são potenciais receitas em espera.', 'tips' => 'Acompanhe orçamentos antigos e faça follow-up com o cliente.'],
                    ['order' => 4, 'title' => 'Revisar faturamento do mês', 'description' => 'Exibe a receita acumulada do mês. Isso ajuda a entender o desempenho financeiro da empresa.', 'tips' => 'Use para planejar se o mês está indo bem ou se precisa de mais trabalho.'],
                    ['order' => 5, 'title' => 'Verificar atividades recentes', 'description' => 'Mostra as últimas ações registradas no sistema. Útil para saber o que está acontecendo e quem está fazendo o quê.', 'tips' => 'Veja atividades recentes para entender o fluxo de trabalho da equipe.'],
                    ['order' => 6, 'title' => 'Usar o menu lateral', 'description' => 'No lado esquerdo está o menu com: Dashboard (você está aqui), Ordens de Serviço, Clientes, Equipamentos, Relatórios, Configurações e Ajuda.', 'tips' => 'Familiarize-se com o menu. Ele é seu principal ponto de navegação.'],
                ],
            ],

            // 4. Cadastrar Cliente
            [
                'title' => 'Cadastrar um Novo Cliente',
                'category' => 'Gerenciamento de Clientes',
                'description' => 'Aprenda como registrar clientes no sistema (Pessoa Física ou Jurídica)',
                'steps' => [
                    ['order' => 1, 'title' => 'Acessar Clientes', 'description' => 'No menu lateral, clique em "👥 Clientes" para acessar a lista de clientes cadastrados.', 'tips' => 'Você pode visualizar todos os clientes e suas ordens de serviço aqui.'],
                    ['order' => 2, 'title' => 'Iniciar novo cadastro', 'description' => 'Clique no botão "+ Novo Cliente" no canto superior direito. Uma tela de seleção aparecerá pedindo o tipo de pessoa.', 'tips' => 'Escolha "Pessoa Física" para clientes individuais (CPF) ou "Pessoa Jurídica" para empresas (CNPJ).'],
                    ['order' => 3, 'title' => 'Preencher dados básicos', 'description' => 'Preencha o nome (ou razão social para PJ) e o documento (CPF ou CNPJ). O documento é opcional, mas recomendado para rastreamento. Email e telefone também são opcionais.', 'tips' => 'O sistema aceita CPF/CNPJ com ou sem formatação (123.456.789-10 ou 12345678910).'],
                    ['order' => 4, 'title' => 'Adicionar contatos', 'description' => 'Na seção "Contatos", clique em "+ Adicionar Contato". Preencha nome, telefone e/ou email do responsável. Você pode adicionar múltiplos contatos.', 'tips' => 'Isso facilita quando você precisa entrar em contato rapidamente sobre uma ordem de serviço.'],
                    ['order' => 5, 'title' => 'Adicionar endereços', 'description' => 'Na seção "Endereços", clique em "+ Adicionar Endereço". Preencha CEP (e o sistema auto-completa cidade/estado), rua, número e complemento.', 'tips' => 'Use "Endereço Principal" para marcar o endereço onde o cliente está baseado. Você pode adicionar múltiplos endereços.'],
                    ['order' => 6, 'title' => 'Confirmar cadastro', 'description' => 'Revise todos os dados preenchidos e clique em "Adicionar Cliente". O cliente será criado e você será redirecionado para sua página de perfil.', 'tips' => 'Você pode editar os dados do cliente a qualquer momento clicando em "Editar" em seu perfil.'],
                    ['order' => 7, 'title' => 'Verificar histórico', 'description' => 'Na página do cliente, você verá um histórico de todas as ordens de serviço, contatos e endereços vinculados.', 'tips' => 'Isso é útil para rastrear a história de serviço de um cliente específico.'],
                ],
            ],

            // 5. Cadastrar Equipamento
            [
                'title' => 'Registrar um Novo Equipamento',
                'category' => 'Gerenciamento de Equipamentos',
                'description' => 'Guia para cadastrar equipamentos no sistema',
                'steps' => [
                    ['order' => 1, 'title' => 'Acessar Equipamentos', 'description' => 'Clique em "📱 Equipamentos" no menu lateral. Você verá a lista de todos os equipamentos cadastrados.', 'tips' => 'Você pode filtrar e buscar equipamentos por marca, modelo ou cliente.'],
                    ['order' => 2, 'title' => 'Iniciar novo cadastro', 'description' => 'Clique em "+ Novo Equipamento" no canto superior direito.', 'tips' => 'Você também pode criar um equipamento diretamente durante a criação de uma Ordem de Serviço (mais eficiente).'],
                    ['order' => 3, 'title' => 'Selecionar cliente', 'description' => 'Escolha qual cliente o equipamento pertence. Se o cliente não existir, crie-o primeiro em "👥 Clientes".', 'tips' => 'Um equipamento sempre deve estar vinculado a um cliente.'],
                    ['order' => 4, 'title' => 'Preencher especificações', 'description' => 'Preencha: Marca (ex: Dell), Modelo (ex: Inspiron 15), Categoria (Computador, Impressora, Telefone, etc.) e opcionalmente Número de Série.', 'tips' => 'O número de série ajuda na identificação do equipamento, mas é opcional.'],
                    ['order' => 5, 'title' => 'Confirmar cadastro', 'description' => 'Clique em "Adicionar Equipamento". O equipamento será criado e poderá ser selecionado ao criar uma Ordem de Serviço.', 'tips' => 'Equipamentos aparecem na lista de seleção durante a criação de OS, facilitando o fluxo de trabalho.'],
                ],
            ],

            // 6. Criar Ordem de Serviço
            [
                'title' => 'Criar uma Ordem de Serviço',
                'category' => 'Ordens de Serviço',
                'description' => 'Aprenda o passo-a-passo completo para criar uma Ordem de Serviço',
                'steps' => [
                    ['order' => 1, 'title' => 'Acessar Ordens de Serviço', 'description' => 'Clique em "🔧 Ordens de Serviço" no menu lateral. Você verá a lista de todas as OSs.', 'tips' => 'Você pode filtrar por status (Aberta, Em Execução, Pronta, Entregue) ou período.'],
                    ['order' => 2, 'title' => 'Iniciar nova OS', 'description' => 'Clique em "+ Criar OS" no canto superior direito. Você iniciará um fluxo de criação.', 'tips' => 'O processo de criação é dividido em etapas para facilitar o preenchimento.'],
                    ['order' => 3, 'title' => 'Selecionar cliente', 'description' => 'Escolha um cliente da lista. Se o cliente não existe, clique em "+ Cliente" para criar um novo cliente sem sair da tela.', 'tips' => 'Criar o cliente inline economiza tempo e mantém o foco no fluxo de criação da OS.'],
                    ['order' => 4, 'title' => 'Selecionar ou criar equipamento', 'description' => 'Escolha um equipamento do cliente ou clique em "+ Equipamento" para registrar um novo. Se não souber os detalhes exatos, preencha "Descrição do Equipamento" em texto livre.', 'tips' => 'Você não é obrigado a selecionar um equipamento existente; pode descrever textualmente.'],
                    ['order' => 5, 'title' => 'Descrever o problema', 'description' => 'Na seção "Descrição do Problema", detalhe ao máximo o relato do cliente. Ex: "Tela não acende, mas a máquina liga normalmente e faz bip".', 'tips' => 'Quanto mais detalhado, melhor para o técnico que fará o diagnóstico posterior.'],
                    ['order' => 6, 'title' => 'Configurar checklist de recebimento (opcional)', 'description' => 'Você pode adicionar itens de verificação que devem ser checados no ato do recebimento do equipamento. Clique em "+ Adicionar Item" para cada verificação.', 'tips' => 'Exemplos: "Acessórios inclusos?", "Equipamento funciona?", "Cabo de força presente?". Você também pode usar um template se disponível.'],
                    ['order' => 7, 'title' => 'Criar rascunho', 'description' => 'Clique em "Criar Rascunho". A OS será criada com status "Rascunho" e você será redirecionado para a página de detalhes.', 'tips' => 'A partir daqui, você pode iniciar a execução, adicionar diagnósticos, orçamentos e acompanhar todo o fluxo.'],
                ],
            ],

            // 7. Registrar Horas
            [
                'title' => 'Registrar Horas de Trabalho',
                'category' => 'Execução',
                'description' => 'Aprenda como registrar o tempo gasto no reparo de um equipamento',
                'steps' => [
                    ['order' => 1, 'title' => 'Abrir a Ordem de Serviço', 'description' => 'Na lista de Ordens de Serviço, clique na OS que deseja atualizar.', 'tips' => 'Você só pode registrar horas em OSs que estão em status "Em Execução".'],
                    ['order' => 2, 'title' => 'Iniciar execução (se não iniciada)', 'description' => 'Se a OS estiver em "Rascunho", clique em "Iniciar Execução". Selecione o técnico responsável e confirme.', 'tips' => 'Uma vez iniciada, o status muda para "Em Execução" e você pode registrar trabalho.'],
                    ['order' => 3, 'title' => 'Adicionar entrada de trabalho', 'description' => 'Na seção "Registro de Execução", clique em "+ Adicionar Entrada de Trabalho".', 'tips' => 'Você pode registrar trabalho retroativamente (de dias anteriores) se necessário.'],
                    ['order' => 4, 'title' => 'Preencher detalhes do trabalho', 'description' => 'Preencha: Data (quando foi feito), Técnico (quem fez), Duração (em horas, ex: 2.5 para 2h30min), Descrição (o que foi feito) e Tipo de Trabalho (diagnóstico, reparo, limpeza, etc.).', 'tips' => 'Use descrições detalhadas como "Limpeza de hardware, substituição de pasta térmica, testado funcionamento normal".'],
                    ['order' => 5, 'title' => 'Registrar peças utilizadas', 'description' => 'Se usou peças/componentes, clique em "+ Adicionar Peça" e selecione do catálogo. O custo será calculado automaticamente.', 'tips' => 'Manter registro de peças ajuda a controlar estoque e custos.'],
                    ['order' => 6, 'title' => 'Confirmar entrada', 'description' => 'Clique em "Adicionar" para confirmar a entrada de trabalho. Ela aparecerá no timeline da OS.', 'tips' => 'Você pode adicionar múltiplas entradas de trabalho se o técnico trabalhou em diferentes dias ou em diferentes tarefas.'],
                ],
            ],

            // 8. Criar Orçamento
            [
                'title' => 'Criar e Gerenciar Orçamentos',
                'category' => 'Diagnóstico e Orçamento',
                'description' => 'Guia completo para criar orçamentos de reparo',
                'steps' => [
                    ['order' => 1, 'title' => 'Abrir a Ordem de Serviço', 'description' => 'Acesse a OS que precisa de orçamento.', 'tips' => 'A OS deve estar em status "Em Execução" para permitir criação de orçamento.'],
                    ['order' => 2, 'title' => 'Registrar diagnóstico', 'description' => 'Antes de criar o orçamento, é recomendável registrar o diagnóstico. Na seção "Diagnóstico", preencha notas técnicas e um resumo para o cliente.', 'tips' => 'Exemplo de diagnóstico: "HD com bad sectors detectados. Backup recomendado antes da recuperação".'],
                    ['order' => 3, 'title' => 'Iniciar novo orçamento', 'description' => 'Clique em "+ Orçamento" na seção de orçamentos.', 'tips' => 'Você pode criar múltiplos orçamentos se quiser oferecer diferentes opções ao cliente.'],
                    ['order' => 4, 'title' => 'Adicionar itens ao orçamento', 'description' => 'Para cada item de serviço/peça: Descrição (ex: "Troca de HD SSD 256GB"), Quantidade (geralmente 1), e Preço Unitário. O total é calculado automaticamente.', 'tips' => 'Exemplos de itens: "Diagnóstico completo" (R$ 50), "Troca de HD" (R$ 150), "Backup de dados" (R$ 80). Adicione múltiplos itens clicando "+ Item".'],
                    ['order' => 5, 'title' => 'Definir validade', 'description' => 'Especifique por quantos dias o orçamento é válido (ex: 7 dias, 30 dias). O cliente deve responder dentro desse prazo.', 'tips' => 'Orçamentos com prazos curtos incentivam o cliente a tomar uma decisão rápida.'],
                    ['order' => 6, 'title' => 'Enviar para aprovação', 'description' => 'Clique em "Criar Orçamento". O orçamento será criado com status "Pendente" e o cliente será notificado.', 'tips' => 'O cliente pode aprovar, rejeitar ou solicitar alteração no orçamento.'],
                    ['order' => 7, 'title' => 'Acompanhar resposta', 'description' => 'Monitore o status do orçamento no timeline da OS. Quando aprovado, você pode proceder com o reparo.', 'tips' => 'Notificações informam quando o cliente responde ao orçamento.'],
                ],
            ],

            // 9. Testes Finais
            [
                'title' => 'Registrar Testes Finais',
                'category' => 'Execução',
                'description' => 'Aprenda como documentar os testes finais e marcar um equipamento como concluído',
                'steps' => [
                    ['order' => 1, 'title' => 'Validar se o trabalho está completo', 'description' => 'Antes de registrar os testes, garanta que todo o trabalho foi concluído: diagnóstico, reparos, limpeza, etc.', 'tips' => 'Revise o histórico de trabalho para ter certeza que nada foi esquecido.'],
                    ['order' => 2, 'title' => 'Acessar testes finais', 'description' => 'Na página da OS, clique em "Concluir Execução — Testes Finais".', 'tips' => 'Esta opção aparece quando a OS está em status "Em Execução".'],
                    ['order' => 3, 'title' => 'Adicionar testes', 'description' => 'Para cada teste realizado, clique em "+ Adicionar Item". Preencha a descrição do teste (ex: "Tela acende normalmente") e o resultado (✓ Passou ou ✕ Falhou).', 'tips' => 'Exemplos de testes: "Sistema operacional inicia", "Bateria carrega", "WiFi conecta", "Periféricos funcionam", "Sem superaquecimento".'],
                    ['order' => 4, 'title' => 'Ser minucioso', 'description' => 'Registre TODOS os testes realizados. Isso documenta que o equipamento está funcionando corretamente.', 'tips' => 'Quanto mais testes documentados, melhor a garantia que você oferece ao cliente.'],
                    ['order' => 5, 'title' => 'Corrigir falhas se necessário', 'description' => 'Se algum teste falhou, você pode: a) Tentar corrigir e refazer o teste, ou b) Notificar o cliente sobre o problema.', 'tips' => 'Não avance para "Concluído" se houver testes com falhas, a menos que o cliente tenha sido informado.'],
                    ['order' => 6, 'title' => 'Marcar como concluído', 'description' => 'Quando todos os testes estiverem preenchidos e aprovados, clique em "Concluir". O status da OS muda para "Pronto para Entrega".', 'tips' => 'Após este passo, o equipamento está pronto para ser entregue ao cliente.'],
                ],
            ],

            // 10. Registrar Pagamento
            [
                'title' => 'Registrar Pagamento',
                'category' => 'Pagamento e Entrega',
                'description' => 'Aprenda como registrar o recebimento de pagamento de um cliente',
                'steps' => [
                    ['order' => 1, 'title' => 'Abrir a Ordem de Serviço', 'description' => 'Localize a OS da qual está recebendo pagamento.', 'tips' => 'A OS deve estar ao menos em status "Em Execução" para permitir registro de pagamento.'],
                    ['order' => 2, 'title' => 'Verificar saldo pendente', 'description' => 'Na seção "Financeiro" ou "Pagamentos", você verá o total cobrado e o saldo pendente.', 'tips' => 'Se houver um orçamento aprovado, o valor deve corresponder ao orçamento.'],
                    ['order' => 3, 'title' => 'Iniciar novo pagamento', 'description' => 'Clique em "+ Pagamento" na seção de pagamentos.', 'tips' => 'Você pode registrar múltiplos pagamentos se o cliente pagar em parcelas.'],
                    ['order' => 4, 'title' => 'Preencher detalhes do pagamento', 'description' => 'Preencha: Valor (em R$), Forma de Pagamento (Dinheiro, PIX, Cartão, etc.) e observações opcionais.', 'tips' => 'O valor não precisa ser o total; você pode registrar pagamentos parciais.'],
                    ['order' => 5, 'title' => 'Confirmar pagamento', 'description' => 'Clique em "Confirmar". O pagamento será registrado e o saldo pendente será atualizado automaticamente.', 'tips' => 'Cada pagamento é registrado com data, hora e hora de quem o recebeu para auditoria.'],
                    ['order' => 6, 'title' => 'Acompanhar saldo', 'description' => 'Você verá no timeline que o pagamento foi registrado. O saldo pendente diminui automaticamente.', 'tips' => 'Se o cliente tem saldo pendente após entrega, é registrado para acompanhamento posterior.'],
                ],
            ],

            // 11. Entregar Equipamento
            [
                'title' => 'Entregar um Equipamento',
                'category' => 'Pagamento e Entrega',
                'description' => 'Guia para registrar a entrega final de um equipamento ao cliente',
                'steps' => [
                    ['order' => 1, 'title' => 'Verificar status', 'description' => 'A OS deve estar em status "Pronto para Entrega". Isso significa que os testes finais foram concluídos.', 'tips' => 'Se não estiver "Pronto para Entrega", complete os testes finais primeiro.'],
                    ['order' => 2, 'title' => 'Abrir tela de entrega', 'description' => 'Clique em "Registrar Entrega" ou "Entregar Equipamento".', 'tips' => 'Esta opção aparece quando a OS está em "Pronto para Entrega".'],
                    ['order' => 3, 'title' => 'Registrar quem está recebendo', 'description' => 'Preencha o nome da pessoa que está retirando o equipamento (nome completo ou apelido).', 'tips' => 'Isso ajuda no rastreamento em caso de dúvidas posteriores sobre quem recebeu.'],
                    ['order' => 4, 'title' => 'Adicionar observações (opcional)', 'description' => 'Você pode adicionar notas sobre a entrega: "Cliente satisfeito", "Levou sem acessórios", etc.', 'tips' => 'Use para documentar qualquer detalhe importante.'],
                    ['order' => 5, 'title' => 'Confirmar entrega', 'description' => 'Clique em "Confirmar Entrega". A OS será marcada como "Entregue" com data e hora.', 'tips' => 'Uma vez entregue, a OS não pode ser mais editada (status terminal).'],
                    ['order' => 6, 'title' => 'Acompanhar no histórico', 'description' => 'A OS aparecerá no seu histórico com status "Entregue" e será contabilizada nos relatórios.', 'tips' => 'Entregas são rastreadas para cálculo de receita e performance.'],
                ],
            ],

            // 12. Gerenciar Usuários
            [
                'title' => 'Gerenciar Usuários da Empresa',
                'category' => 'Configurações',
                'description' => 'Aprenda como convidar, editar e remover usuários (membros da equipe)',
                'steps' => [
                    ['order' => 1, 'title' => 'Acessar Configurações', 'description' => 'Clique em "⚙️ Configurações" no menu lateral.', 'tips' => 'Apenas administradores podem gerenciar usuários.'],
                    ['order' => 2, 'title' => 'Acessar Usuários', 'description' => 'Clique na aba "Usuários" para ver todos os membros da sua assistência técnica.', 'tips' => 'Você verá nome, email, função e status de cada membro.'],
                    ['order' => 3, 'title' => 'Convidar novo membro', 'description' => 'Clique em "+ Convidar Membro". Preencha email e selecione a função.', 'tips' => 'Funções disponíveis: Operador (técnico padrão) e Admin (pode gerenciar sistema).'],
                    ['order' => 4, 'title' => 'Confirmar convite', 'description' => 'Clique em "Convidar". A pessoa receberá um email com link para aceitar o convite.', 'tips' => 'O link é válido por um período. Se expirar, você pode reenviar.'],
                    ['order' => 5, 'title' => 'Editar usuário', 'description' => 'Clique no usuário na lista para editar sua função. Altere se necessário e clique em "Salvar".', 'tips' => 'Você pode mudar um operador para admin ou vice-versa a qualquer momento.'],
                    ['order' => 6, 'title' => 'Remover usuário', 'description' => 'Encontre o usuário e clique em "Remover". Confirme a exclusão.', 'tips' => 'Usuários removidos perdem acesso ao sistema. Certifique-se antes de remover.'],
                ],
            ],

            // 13. Auditoria
            [
                'title' => 'Revisar Auditoria de Operações',
                'category' => 'Auditoria',
                'description' => 'Aprenda como rastrear e revisar todas as ações realizadas no sistema',
                'steps' => [
                    ['order' => 1, 'title' => 'Acessar Auditoria', 'description' => 'Clique em "⚙️ Configurações" e depois em "Auditoria".', 'tips' => 'Apenas administradores podem acessar auditoria.'],
                    ['order' => 2, 'title' => 'Visualizar log completo', 'description' => 'Você verá uma lista de todas as operações: criação, edição, deleção de registros. Cada entrada mostra quem fez, quando e o quê.', 'tips' => 'O log é imutável (não pode ser alterado), garantindo integridade.'],
                    ['order' => 3, 'title' => 'Filtrar por data', 'description' => 'Use o filtro de data para ver operações em um período específico. Ex: últimos 30 dias.', 'tips' => 'Útil para investigar quando algo mudou.'],
                    ['order' => 4, 'title' => 'Filtrar por usuário', 'description' => 'Selecione um usuário específico para ver apenas as ações que ele realizou.', 'tips' => 'Isso ajuda a rastrear atividades de um membro da equipe.'],
                    ['order' => 5, 'title' => 'Filtrar por tipo de ação', 'description' => 'Escolha o tipo: Criado, Editado, Deletado para ver apenas operações desse tipo.', 'tips' => 'Ex: filtrar por "Deletado" para ver tudo que foi removido.'],
                    ['order' => 6, 'title' => 'Filtrar por recurso', 'description' => 'Selecione qual tipo de recurso: Ordem de Serviço, Cliente, Equipamento, etc.', 'tips' => 'Combine múltiplos filtros para investigações precisas.'],
                    ['order' => 7, 'title' => 'Revisar detalhes', 'description' => 'Clique em uma entrada para ver o histórico completo: valor anterior, novo valor, quem mudou.', 'tips' => 'Isso é essencial para conformidade e análise de erros.'],
                ],
            ],

            // 14. Relatórios
            [
                'title' => 'Gerar Relatórios de Desempenho',
                'category' => 'Relatórios',
                'description' => 'Aprenda como gerar e interpretar relatórios de desempenho',
                'steps' => [
                    ['order' => 1, 'title' => 'Acessar Relatórios', 'description' => 'Clique em "📈 Relatórios" no menu lateral.', 'tips' => 'Relatórios oferecem insights sobre o desempenho da assistência técnica.'],
                    ['order' => 2, 'title' => 'Escolher tipo de relatório', 'description' => 'Selecione entre: Ordens de Serviço, Faturamento, Performance, ou Satisfação.', 'tips' => 'Cada relatório oferece uma perspectiva diferente do negócio.'],
                    ['order' => 3, 'title' => 'Definir período', 'description' => 'Escolha a data inicial e final para o período que deseja analisar.', 'tips' => 'Você pode usar períodos pré-definidos (Este mês, Últimos 30 dias) ou custom.'],
                    ['order' => 4, 'title' => 'Gerar relatório', 'description' => 'Clique em "Gerar". O sistema processará os dados e exibirá gráficos e tabelas.', 'tips' => 'Relatórios grandes podem levar alguns segundos.'],
                    ['order' => 5, 'title' => 'Interpretar dados', 'description' => 'Analise os gráficos: Ordens criadas/concluídas, receita total, taxa de conclusão, horas por técnico.', 'tips' => 'Procure por tendências: aumentos de volume, quedas de receita, técnicos com desempenho consistente.'],
                    ['order' => 6, 'title' => 'Exportar (se disponível)', 'description' => 'Clique em "PDF" ou "Excel" para baixar o relatório em formato de arquivo.', 'tips' => 'Use para apresentações, arquivos ou análises offline.'],
                ],
            ],

            // 15. Base de Conhecimento
            [
                'title' => 'Usar a Base de Conhecimento (Playbooks)',
                'category' => 'Ajuda',
                'description' => 'Aprenda como usar os playbooks para resolver problemas comuns',
                'steps' => [
                    ['order' => 1, 'title' => 'Acessar Ajuda', 'description' => 'Clique em "📚 Ajuda" no menu lateral.', 'tips' => 'Playbooks são guias passo-a-passo criados pela sua empresa.'],
                    ['order' => 2, 'title' => 'Explorar categorias', 'description' => 'Você verá playbooks organizados por categoria: Onboarding, Diagnóstico, Reparos, etc.', 'tips' => 'Procure pela categoria relevante ao seu problema.'],
                    ['order' => 3, 'title' => 'Selecionar um playbook', 'description' => 'Clique em um playbook para abrir o guia completo com passos detalhados.', 'tips' => 'Cada playbook tem instruções passo-a-passo, dicas e exemplos.'],
                    ['order' => 4, 'title' => 'Seguir os passos', 'description' => 'Leia cada passo cuidadosamente e siga as instruções. Não pule etapas.', 'tips' => 'As dicas ao lado de cada passo oferecem conselhos adicionais.'],
                    ['order' => 5, 'title' => 'Aplicar ao seu caso', 'description' => 'Adapte o playbook à situação específica. Os playbooks são guias, não regras rígidas.', 'tips' => 'Se encontrou uma solução melhor, comunique ao administrador para melhorar o playbook.'],
                    ['order' => 6, 'title' => 'Retornar se tiver dúvidas', 'description' => 'Qualquer momento que ficar em dúvida, retorne à seção Ajuda para consultar um playbook relevante.', 'tips' => 'Playbooks estão sempre disponíveis como referência rápida.'],
                ],
            ],

            // 16. Troubleshooting
            [
                'title' => 'Resolver Problemas Comuns do Sistema',
                'category' => 'Troubleshooting',
                'description' => 'Solução para problemas frequentes no uso do sistema',
                'steps' => [
                    ['order' => 1, 'title' => 'Página carregando lentamente', 'description' => 'Se o sistema está lento: 1) Verifique sua conexão de internet, 2) Tente atualizar a página (Ctrl+Shift+R), 3) Tente outro navegador.', 'tips' => 'Conexões lentas são o problema mais comum. Use WiFi se possível.'],
                    ['order' => 2, 'title' => 'Esqueceu a senha', 'description' => 'Na tela de login, clique em "Esqueceu a senha?". Insira seu email e você receberá um link de recuperação.', 'tips' => 'Verifique a pasta de spam se não receber o email em alguns minutos.'],
                    ['order' => 3, 'title' => 'Não consegue encontrar um cliente', 'description' => 'Use a busca na página de Clientes. Verifique a grafia. Se ainda não encontrar, o cliente pode não estar cadastrado; crie um novo.', 'tips' => 'Alguns clientes podem estar com nome levemente diferente no sistema.'],
                    ['order' => 4, 'title' => 'Página em branco ou erro', 'description' => 'Se vir uma página em branco ou erro: 1) Tente recarregar (F5), 2) Limpe o cache do navegador, 3) Tente em modo anônimo.', 'tips' => 'Problemas com cache do navegador são comuns. Limpá-lo geralmente resolve.'],
                    ['order' => 5, 'title' => 'Não consegue criar uma Ordem de Serviço', 'description' => 'Certifique-se de: 1) Ter um cliente selecionado, 2) Ter descrito o problema, 3) Ter permissão (ser Operador ou Admin).', 'tips' => 'Se tem permissão e ainda não consegue, contate o administrador.'],
                    ['order' => 6, 'title' => 'Notificações não aparecem', 'description' => 'Notificações podem estar desabilitadas no seu navegador. Verifique as configurações de notificações do seu navegador.', 'tips' => 'Alguns navegadores pedem permissão para enviar notificações; confirme.'],
                ],
            ],

            // 17. Fluxo Completo
            [
                'title' => 'Fluxo Completo: Uma Ordem do Início ao Fim',
                'category' => 'Fluxo Completo',
                'description' => 'Guia passo-a-passo do ciclo de vida completo de uma Ordem de Serviço',
                'steps' => [
                    ['order' => 1, 'title' => 'Etapa 1: Recebimento', 'description' => 'Cliente chega com equipamento com defeito. Crie uma nova OS: selecione/crie cliente, selecione/crie equipamento, descreva o problema, configure checklist de recebimento.', 'tips' => 'Complete todos os dados logo no início para não esquecer detalhes.'],
                    ['order' => 2, 'title' => 'Etapa 2: Diagnóstico', 'description' => 'Técnico inicia execução. Registra horas de trabalho enquanto faz diagnóstico. Documenta achados e cria orçamento com as soluções possíveis.', 'tips' => 'Seja detalhado no diagnóstico e nas opções de reparo.'],
                    ['order' => 3, 'title' => 'Etapa 3: Aprovação', 'description' => 'Cliente recebe orçamento e aprova. O orçamento muda para "Aprovado". Você pode agora prosseguir com o reparo.', 'tips' => 'Acompanhe a resposta do cliente. Envie lembrete se demorar muito.'],
                    ['order' => 4, 'title' => 'Etapa 4: Execução', 'description' => 'Técnico realiza o reparo. Continua registrando horas de trabalho. Registra peças utilizadas. Documenta cada etapa.', 'tips' => 'Mantenha anotações detalhadas do que foi feito.'],
                    ['order' => 5, 'title' => 'Etapa 5: Testes', 'description' => 'Após conclusão, técnico registra testes finais. Valida que tudo funciona: sistema liga, periféricos, conectividade, etc.', 'tips' => 'Não marque como concluído se houver algum teste com falha.'],
                    ['order' => 6, 'title' => 'Etapa 6: Entrega', 'description' => 'Cliente vem retirar. Registra pagamento (total ou parcial). Registra entrega com nome de quem retirou.', 'tips' => 'Guarde comprovante de entrega em caso de dúvidas posteriores.'],
                    ['order' => 7, 'title' => 'Etapa 7: Fechamento', 'description' => 'OS é marcada como "Entregue". Aparece nos relatórios. Histórico completo fica disponível para consultas futuras.', 'tips' => 'Use o histórico para referência em futuras OSs do mesmo cliente.'],
                ],
            ],
        ];

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        try {
            foreach ($playbooks as $playbook) {
                $steps = $playbook['steps'];
                unset($playbook['steps']);

                $playbookId = DB::table('playbooks')->insertGetId([
                    'title' => $playbook['title'],
                    'category' => $playbook['category'],
                    'description' => $playbook['description'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($steps as $step) {
                    DB::table('playbook_steps')->insert([
                        'playbook_id' => $playbookId,
                        'order' => $step['order'],
                        'title' => $step['title'],
                        'description' => $step['description'],
                        'tips' => $step['tips'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $this->command->info('✅ Playbooks baseados no arquivo docs/HELP.md foram inseridos com sucesso!');
            $this->command->info('📊 Total: 17 playbooks com ~110 passos');
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }
}
