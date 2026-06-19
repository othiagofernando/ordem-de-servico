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
            [
                'emoji'       => '📋',
                'title'       => 'Recebimento e Abertura de OS',
                'description' => 'Passo a passo para receber um equipamento do cliente, cadastrar corretamente no sistema e abrir a ordem de serviço.',
                'category'    => 'Atendimento',
                'status'      => 'published',
                'steps'       => [
                    [
                        'title'   => 'Receba o cliente e identifique a demanda',
                        'content' => "Cumprimente o cliente com cordialidade e pergunte o motivo da visita.

Ouça atentamente o relato do problema sem interromper. Faça perguntas objetivas para entender melhor:
- Quando o problema começou?
- O equipamento sofreu queda ou contato com líquido?
- Já passou por outro reparo antes?

Anote mentalmente ou em rascunho as informações antes de abrir o sistema.",
                        'tip'      => 'Nunca prometa prazo ou valor de reparo neste momento. Diga ao cliente que o prazo e o orçamento serão informados após o diagnóstico.',
                        'tip_type' => 'warning',
                    ],
                    [
                        'title'   => 'Verifique o estado físico do equipamento na frente do cliente',
                        'content' => "Com o cliente presente, examine o equipamento visualmente e documente:
- Arranhões, amassados ou rachaduras pré-existentes
- Estado da tela (manchas, pixels mortos, vidro trincado)
- Condição das entradas (USB, fone, carregador)
- Se a bateria está estufada ou com sinais de vazamento

Informe ao cliente sobre qualquer avaria estética encontrada antes de assinar a OS. Isso protege a empresa de eventuais reclamações.",
                        'tip'      => 'Se possível, fotografe o equipamento e vincule as imagens à OS como evidências de entrada. Use a aba de anexos da ordem de serviço.',
                        'tip_type' => 'info',
                    ],
                    [
                        'title'   => 'Cadastre ou localize o cliente no sistema',
                        'content' => "Acesse a seção Clientes e busque pelo nome, CPF/CNPJ ou telefone do cliente.

Se o cliente já existe: verifique se os dados de contato (telefone e e-mail) estão atualizados e corrija se necessário.

Se for um cliente novo: cadastre com pelo menos nome completo e um telefone de contato. E-mail é altamente recomendado para comunicação futura.",
                        'tip'      => 'Nunca abra uma OS sem vincular a um cliente cadastrado. Ordens sem cliente dificultam o histórico e o contato para retirada.',
                        'tip_type' => 'warning',
                    ],
                    [
                        'title'   => 'Cadastre o equipamento',
                        'content' => "Em Equipamentos, crie um novo registro com as informações do aparelho:
- Tipo (smartphone, notebook, tablet, etc.)
- Marca e modelo
- Número de série ou IMEI (quando aplicável)
- Cor

Vincule o equipamento ao cliente já cadastrado. Se o mesmo cliente trouxe o equipamento antes, verifique se já existe um registro — nunca duplique.",
                        'tip'      => 'O número de série é fundamental para garantia. Se o cliente não souber, tente localizar na embalagem, nas configurações do aparelho ou na etiqueta física.',
                        'tip_type' => 'info',
                    ],
                    [
                        'title'   => 'Abra a Ordem de Serviço',
                        'content' => "Em Ordens de Serviço, clique em Nova OS e preencha:
- Cliente e equipamento cadastrados
- Descrição detalhada do problema relatado pelo cliente (use as palavras dele)
- Condição de entrada (ex.: \"Tela trincada, sem ligar, sem carregador\")
- Checklist de entrada, se aplicável

Salve a OS e apresente o número ao cliente. Informe que ele receberá o orçamento por mensagem ou ligue assim que o diagnóstico for concluído.",
                        'tip'      => 'A descrição do problema deve refletir o que o cliente disse, não o que você acha que é. O técnico fará o diagnóstico real.',
                        'tip_type' => 'success',
                    ],
                ],
            ],

            [
                'emoji'       => '🔍',
                'title'       => 'Diagnóstico de Smartphone que Não Liga',
                'description' => 'Protocolo técnico para identificar a causa raiz de um smartphone sem resposta de energia, do mais simples ao mais complexo.',
                'category'    => 'Diagnóstico',
                'status'      => 'published',
                'steps'       => [
                    [
                        'title'   => 'Verifique o estado da bateria',
                        'content' => "Antes de qualquer coisa, conecte o aparelho a um carregador original ou compatível e aguarde pelo menos 10 minutos.

Observe:
- O aparelho exibe ícone de carregamento?
- A tela fica completamente apagada mesmo conectado?
- O aparelho esquenta em algum ponto?

Se não houver nenhuma reação, prossiga para o próximo passo.",
                        'tip'      => 'Baterias completamente descarregadas podem levar até 15 minutos para mostrar qualquer sinal de vida. Tenha paciência antes de concluir que o problema é outro.',
                        'tip_type' => 'info',
                    ],
                    [
                        'title'   => 'Teste a entrada de carregamento',
                        'content' => "Use um multímetro ou testador de cabo USB para verificar se a corrente está chegando ao aparelho.

Inspecione visualmente a porta de carregamento com lanterna: verifique se há sujeira, corrosão, pinos tortos ou quebrados.

Se suspeitar de entrada com defeito, limpe cuidadosamente com álcool isopropílico e escova macia antes de concluir que precisa de substituição.",
                        'tip'      => 'Nunca use ar comprimido dentro da entrada de carregamento sem antes verificar se não há líquido no interior — isso pode empurrar a umidade para dentro da placa.',
                        'tip_type' => 'warning',
                    ],
                    [
                        'title'   => 'Verifique sinais de dano por líquido',
                        'content' => "Abra o aparelho seguindo o procedimento de abertura segura para o modelo específico.

Inspecione a placa-mãe, a bateria e os conectores em busca de:
- Manchas brancas ou esverdeadas (oxidação)
- Resíduos de líquido seco
- Componentes com sinais de corrosão

Se encontrar corrosão, o equipamento precisará de limpeza ultrassônica antes de qualquer outro diagnóstico elétrico.",
                        'tip'      => 'Registre com foto qualquer sinal de dano por líquido antes de prosseguir. Isso é essencial para o laudo técnico e para a comunicação com o cliente sobre o diagnóstico.',
                        'tip_type' => 'info',
                    ],
                    [
                        'title'   => 'Teste com fonte de bancada',
                        'content' => "Substitua a bateria por uma fonte de bancada regulada para verificar o consumo de corrente do aparelho:

- Sem bateria e sem ligar: deve consumir 0 mA
- Ao pressionar o botão power: consumo breve de 100–300 mA indica tentativa de boot
- Consumo acima de 500 mA sem ligar: indica curto-circuito na placa

O comportamento do consumo indica se o problema é na bateria, no carregador IC ou na placa principal.",
                        'tip'      => 'Anote os valores de corrente observados em cada etapa. Esses dados devem constar no registro de execução da OS para histórico técnico.',
                        'tip_type' => 'success',
                    ],
                    [
                        'title'   => 'Registre o diagnóstico e crie o orçamento',
                        'content' => "Com base nos testes realizados, acesse a OS no sistema e preencha o diagnóstico técnico com:
- O que foi testado
- O que foi encontrado
- A causa provável do problema
- A solução recomendada

Em seguida, crie o orçamento com as peças e serviços necessários e envie para aprovação do cliente.",
                        'tip'      => 'Seja específico no diagnóstico. \"Placa com defeito\" não é diagnóstico. \"Curto no CI de carregamento identificado via fonte de bancada\" é diagnóstico.',
                        'tip_type' => 'success',
                    ],
                ],
            ],

            [
                'emoji'       => '💬',
                'title'       => 'Como Apresentar o Orçamento ao Cliente',
                'description' => 'Guia para comunicar o diagnóstico e o orçamento de forma clara, transparente e profissional, aumentando a taxa de aprovação.',
                'category'    => 'Atendimento',
                'status'      => 'published',
                'steps'       => [
                    [
                        'title'   => 'Entre em contato pelo canal preferido do cliente',
                        'content' => "Utilize o contato registrado na OS. Prefira WhatsApp ou telefone, na ordem de preferência informada pelo cliente no cadastro.

Identifique-se e mencione o número da OS logo no início:
\"Olá, aqui é da [nome da empresa]. Tenho uma atualização sobre a sua OS número [número], referente ao seu [modelo do equipamento].\"

Isso evita confusão e passa profissionalismo.",
                        'tip'      => 'Nunca ligue para apresentar orçamento sem ter o diagnóstico completo em mãos. O cliente vai perguntar detalhes e você precisa responder com segurança.',
                        'tip_type' => 'warning',
                    ],
                    [
                        'title'   => 'Explique o diagnóstico em linguagem simples',
                        'content' => "Evite jargões técnicos excessivos. Traduza o diagnóstico para o cliente:

Em vez de: \"O CI de carregamento queimou devido a surto de tensão\"
Diga: \"O chip responsável por carregar a bateria queimou, provavelmente por um pico de energia ou carregador incompatível\"

Se o cliente quiser mais detalhes técnicos, forneça. Mas comece sempre pelo resumo simples.",
                        'tip'      => 'Clientes que entendem o problema aprovam o orçamento com mais facilidade e confiam mais no serviço. Transparência gera fidelidade.',
                        'tip_type' => 'success',
                    ],
                    [
                        'title'   => 'Apresente o valor e o prazo com clareza',
                        'content' => "Informe o valor total do reparo, o que está incluído (peça + mão de obra) e o prazo estimado de entrega.

Exemplo: \"O reparo ficará R$ 180,00, incluindo a peça e a mão de obra. O prazo é de 3 dias úteis após a sua aprovação.\"

Se houver mais de uma opção (ex.: peça original vs. compatível), apresente as duas com os prós e contras de cada uma.",
                        'tip'      => 'Nunca dê prazo sem verificar a disponibilidade da peça primeiro. Prazo não cumprido é a principal causa de reclamação de clientes.',
                        'tip_type' => 'warning',
                    ],
                    [
                        'title'   => 'Registre a decisão do cliente no sistema',
                        'content' => "Após a conversa, acesse a OS e atualize o status conforme a decisão:

- Cliente aprovou: Clique em \"Aprovado\" no orçamento para liberar a execução do reparo.
- Cliente recusou: Registre a recusa e combine a devolução do equipamento. Informe o valor da taxa de diagnóstico, se aplicável.
- Cliente pediu prazo para decidir: Registre na OS e defina um lembrete para retornar o contato.",
                        'tip'      => 'Nunca inicie o reparo sem aprovação registrada no sistema. A aprovação é a sua proteção legal em caso de disputas.',
                        'tip_type' => 'warning',
                    ],
                ],
            ],

            [
                'emoji'       => '📦',
                'title'       => 'Entrega do Equipamento ao Cliente',
                'description' => 'Procedimento de entrega para garantir que o equipamento sai funcionando, o cliente está satisfeito e a OS é encerrada corretamente.',
                'category'    => 'Entrega',
                'status'      => 'published',
                'steps'       => [
                    [
                        'title'   => 'Teste o equipamento antes da entrega',
                        'content' => "Antes de chamar o cliente, faça um teste completo do equipamento:
- Ligue e verifique se o boot ocorre normalmente
- Teste todas as funções relacionadas ao reparo realizado
- Verifique funções secundárias: câmera, alto-falante, microfone, Wi-Fi, Bluetooth
- Confirme que o nível de bateria está acima de 50%

Se detectar qualquer problema nesta etapa, não avise o cliente antes de resolver internamente.",
                        'tip'      => 'Nunca entregue um equipamento sem testá-lo. Um problema descoberto pelo cliente na hora da entrega é muito mais difícil de resolver do que antes.',
                        'tip_type' => 'warning',
                    ],
                    [
                        'title'   => 'Demonstre o funcionamento para o cliente',
                        'content' => "Na presença do cliente, mostre que o problema relatado na OS foi resolvido:

\"O senhor trouxe o aparelho sem ligar. Veja, agora está ligando normalmente, a bateria está carregando e as funções principais estão ok.\"

Incentive o cliente a testar ele mesmo antes de assinar qualquer documento. Isso demonstra confiança no serviço e reduz reclamações pós-entrega.",
                        'tip'      => 'Se o cliente trouxe o aparelho com mais de um problema, demonstre a solução de cada um individualmente.',
                        'tip_type' => 'info',
                    ],
                    [
                        'title'   => 'Informe a garantia do serviço',
                        'content' => "Explique verbalmente e por escrito os termos da garantia:
- Prazo de garantia (ex.: 90 dias para o serviço, 30 dias para a peça)
- O que está coberto (o defeito reparado)
- O que não está coberto (danos físicos novos, dano por líquido após a entrega)

Entregue o termo de garantia impresso ou envie por mensagem. O sistema permite gerar o documento de entrega diretamente pela OS.",
                        'tip'      => 'A garantia se aplica ao serviço executado, não ao equipamento como um todo. Deixe isso claro para evitar expectativas erradas.',
                        'tip_type' => 'info',
                    ],
                    [
                        'title'   => 'Finalize o pagamento e encerre a OS no sistema',
                        'content' => "Confirme a forma de pagamento com o cliente e registre o pagamento na OS pelo módulo financeiro.

Após o pagamento confirmado:
1. Acesse a OS no sistema
2. Registre a entrega do equipamento
3. Altere o status para \"Entregue\"

Imprima ou envie o recibo de pagamento ao cliente se ele solicitar.",
                        'tip'      => 'Nunca entregue o equipamento antes de confirmar o pagamento ou combinar formalmente uma condição de parcelamento registrada na OS.',
                        'tip_type' => 'warning',
                    ],
                ],
            ],

            [
                'emoji'       => '🔄',
                'title'       => 'Atendimento de Retorno em Garantia',
                'description' => 'Como tratar corretamente um equipamento que retornou com o mesmo problema dentro do prazo de garantia do serviço anterior.',
                'category'    => 'Garantia',
                'status'      => 'published',
                'steps'       => [
                    [
                        'title'   => 'Localize a OS original e verifique a garantia',
                        'content' => "Ao atender o cliente, busque no sistema pelo nome, CPF ou número de OS anterior.

Verifique:
- Data de entrega da OS original
- Prazo de garantia registrado
- Serviço executado e peça trocada

Confirme se o retorno está dentro do prazo de garantia antes de qualquer outra ação.",
                        'tip'      => 'Em caso de dúvida sobre o prazo, consulte o gestor antes de comunicar ao cliente. Negar uma garantia indevida é pior do que honrá-la.',
                        'tip_type' => 'warning',
                    ],
                    [
                        'title'   => 'Ouça o relato do cliente sem confrontar',
                        'content' => "Permita que o cliente descreva o problema sem interrupção. Evite expressões que pareçam defensivas como:
- \"Isso não é culpa nossa\"
- \"O cliente deve ter deixado cair\"
- \"Isso não é o mesmo problema\"

Diga apenas: \"Vou verificar o equipamento e te retorno em breve com o diagnóstico.\". Isso acalma o cliente e dá tempo para avaliação técnica.",
                        'tip'      => 'Clientes em retorno de garantia já estão frustrados. O tom da conversa nos primeiros minutos define se o caso vai se resolver bem ou escalar para reclamação.',
                        'tip_type' => 'warning',
                    ],
                    [
                        'title'   => 'Avalie se o problema é coberto pela garantia',
                        'content' => "O técnico deve verificar se o problema apresentado é:

a) O mesmo problema original (coberto): execute o reparo sem custo.
b) Problema diferente (não coberto): abra uma nova OS normalmente com novo orçamento.
c) Dano causado pelo cliente pós-entrega (ex.: nova queda): mostre as evidências ao cliente com clareza e abra nova OS.

Documente tecnicamente a conclusão na OS de garantia.",
                        'tip'      => 'Nunca tome a decisão de negar garantia sem evidência técnica documentada. A negativa sem prova gera conflito e pode resultar em problemas no Procon.',
                        'tip_type' => 'warning',
                    ],
                    [
                        'title'   => 'Abra a OS de retorno em garantia no sistema',
                        'content' => "Crie uma nova OS vinculando-a à OS original de garantia.

Na descrição, registre:
- Número da OS de origem
- Problema relatado no retorno
- Conclusão do diagnóstico técnico
- Se o atendimento é em garantia ou não, e o motivo

Isso mantém o histórico completo do equipamento e protege a empresa.",
                        'tip'      => 'Use o campo de observações para registrar tudo. Um histórico bem documentado protege a empresa em caso de questionamentos futuros.',
                        'tip_type' => 'success',
                    ],
                ],
            ],

            [
                'emoji'       => '💰',
                'title'       => 'Registro de Pagamento e Fechamento Financeiro',
                'description' => 'Como registrar corretamente os pagamentos, cobranças e encerrar o financeiro de uma OS sem deixar pendências.',
                'category'    => 'Financeiro',
                'status'      => 'published',
                'steps'       => [
                    [
                        'title'   => 'Acesse o módulo financeiro da OS',
                        'content' => "Com a OS aberta no sistema, acesse a aba ou seção de Financeiro/Cobranças.

Verifique se o valor cobrado corresponde ao orçamento aprovado pelo cliente. Se houver diferença (ex.: peça com custo diferente do previsto), ajuste o valor com uma cobrança de ajuste e registre o motivo.",
                        'tip'      => 'Nunca altere o valor de um orçamento já aprovado sem comunicar o cliente e registrar a justificativa. Surpresas no valor final na hora da entrega geram conflito.',
                        'tip_type' => 'warning',
                    ],
                    [
                        'title'   => 'Registre a forma e o valor do pagamento',
                        'content' => "No módulo financeiro, adicione o pagamento informando:
- Valor pago
- Forma de pagamento (dinheiro, pix, cartão de débito, cartão de crédito, etc.)
- Data do pagamento

Se o cliente pagou em partes, registre cada pagamento separadamente com sua respectiva data e forma.",
                        'tip'      => 'Pagamentos em dinheiro devem ser conferidos na presença do cliente e registrados imediatamente. Nunca deixe para registrar \"depois\".',
                        'tip_type' => 'warning',
                    ],
                    [
                        'title'   => 'Confirme o saldo e verifique se está zerado',
                        'content' => "Após registrar o(s) pagamento(s), o sistema exibirá o saldo devedor da OS.

Confirme que o saldo está em R$ 0,00 antes de liberar o equipamento para entrega. Se houver saldo pendente, defina com o cliente como e quando será quitado antes de entregar o aparelho.",
                        'tip'      => 'Equipamento entregue com saldo em aberto sem combinação formal raramente é pago. Defina a condição de pagamento antes da entrega e registre na OS.',
                        'tip_type' => 'warning',
                    ],
                    [
                        'title'   => 'Emita o recibo e encerre a OS',
                        'content' => "Com o pagamento quitado, emita o recibo pelo sistema e ofereça ao cliente (impresso ou via WhatsApp).

Em seguida, registre a entrega do equipamento na OS e altere o status para \"Entregue\" ou \"Concluído\", conforme o fluxo da empresa.

A OS encerrada corretamente aparecerá nos relatórios financeiros e de desempenho do mês.",
                        'tip'      => 'OS encerradas incorretamente distorcem os relatórios do dashboard. Sempre finalize com o status correto.',
                        'tip_type' => 'info',
                    ],
                ],
            ],

            [
                'emoji'       => '😤',
                'title'       => 'Como Lidar com Cliente Insatisfeito',
                'description' => 'Protocolo de atendimento para situações de conflito, reclamação ou cliente agitado, preservando a relação e a reputação da empresa.',
                'category'    => 'Atendimento',
                'status'      => 'published',
                'steps'       => [
                    [
                        'title'   => 'Escute sem interromper e sem se defender imediatamente',
                        'content' => "Quando um cliente estiver insatisfeito, permita que ele termine de falar antes de qualquer resposta.

Não interrompa, não cruze os braços e não desvie o olhar. Mostre que está ouvindo com expressões como \"Entendo\" e \"Pode continuar\".

A maioria dos clientes quer ser ouvida antes de qualquer solução. Isso por si só já reduz a tensão.",
                        'tip'      => 'Nunca diga \"se acalme\" ou \"não precisa falar assim\". Essas frases irritam ainda mais. Mantenha a calma e deixe o cliente se expressar.',
                        'tip_type' => 'warning',
                    ],
                    [
                        'title'   => 'Reconheça o problema sem assumir culpa prematuramente',
                        'content' => "Após ouvir, reconheça a insatisfação do cliente com empatia:

\"Entendo a sua frustração e me desculpe pelo transtorno que isso causou.\"

Isso não é admissão de culpa — é empatia. Você pode reconhecer que a situação é ruim para o cliente sem confirmar que a empresa errou, antes de investigar o que realmente aconteceu.",
                        'tip'      => 'Empatia e responsabilidade são coisas diferentes. Você pode ter uma sem a outra. Seja empático sempre; assuma responsabilidade apenas após verificar os fatos.',
                        'tip_type' => 'info',
                    ],
                    [
                        'title'   => 'Investigue os fatos antes de oferecer solução',
                        'content' => "Antes de prometer qualquer coisa, verifique no sistema:
- O histórico da OS
- O diagnóstico registrado
- As notas do técnico
- A data de entrega e o prazo de garantia

Com as informações em mãos, você pode oferecer uma solução baseada em fatos, não em suposições.",
                        'tip'      => 'Se precisar de tempo para investigar, diga ao cliente: \"Vou verificar tudo agora e te retorno em [tempo]. Posso te ligar em [hora]?\". Não deixe o cliente esperando sem previsão.',
                        'tip_type' => 'info',
                    ],
                    [
                        'title'   => 'Ofereça uma solução clara e cumpra o que prometer',
                        'content' => "Com base na investigação, ofereça uma solução concreta:
- Se for erro da empresa: resolva sem custo e com agilidade.
- Se for mal entendido: explique com clareza e documentação.
- Se for algo fora da responsabilidade da empresa: explique os motivos com evidências técnicas.

Seja honesto. Se não puder resolver de imediato, dê um prazo real e cumpra.

Registre a solução combinada na OS para histórico.",
                        'tip'      => 'A forma como você resolve um problema é o que os clientes lembram e contam para outras pessoas. Um problema resolvido bem pode gerar mais fidelidade do que se o problema nunca tivesse ocorrido.',
                        'tip_type' => 'success',
                    ],
                ],
            ],
        ];

        foreach ($playbooks as $data) {
            $playbook = Playbook::create([
                'title'       => $data['title'],
                'description' => $data['description'],
                'category'    => $data['category'],
                'emoji'       => $data['emoji'],
                'status'      => $data['status'],
            ]);

            foreach ($data['steps'] as $i => $step) {
                PlaybookStep::create([
                    'playbook_id' => $playbook->getKey(),
                    'sort_order'  => $i,
                    'title'       => $step['title'],
                    'content'     => $step['content'],
                    'tip'         => $step['tip'] ?? null,
                    'tip_type'    => $step['tip_type'] ?? 'info',
                ]);
            }
        }
    }
}
