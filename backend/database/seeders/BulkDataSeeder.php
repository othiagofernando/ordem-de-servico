<?php

namespace Database\Seeders;

use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Models\Company;
use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Models\CustomerContact;
use App\Domains\Equipments\Models\Equipment;
use App\Domains\ServiceOrders\Models\CompanyServiceOrderCounter;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BulkDataSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('name', 'TechFix Assistência Técnica')->firstOrFail();
        $admin   = Membership::query()->where('company_id', $company->id)->where('role', 'administrator')->firstOrFail();
        $tech    = Membership::query()->where('company_id', $company->id)->where('role', 'technician')->firstOrFail();

        DB::transaction(function () use ($company, $admin, $tech): void {
            $customers  = $this->seedCustomers($company);
            $equipments = $this->seedEquipments($company, $customers);
            $this->seedServiceOrders($company, $admin, $tech, $customers, $equipments);
        });

        $this->command->info('✓ BulkDataSeeder concluído.');
    }

    private function seedCustomers(Company $company): array
    {
        $pf = [
            ['Ana Lima',          '111.222.333-01', '(11) 91111-0001', 'ana.lima@email.com'],
            ['Bruno Costa',       '111.222.333-02', '(11) 91111-0002', 'bruno.costa@email.com'],
            ['Carla Santos',      '111.222.333-03', '(11) 91111-0003', 'carla.santos@email.com'],
            ['Daniel Ferreira',   '111.222.333-04', '(11) 91111-0004', 'daniel.ferreira@email.com'],
            ['Eduardo Alves',     '111.222.333-05', '(11) 91111-0005', 'eduardo.alves@email.com'],
            ['Fernanda Rodrigues','111.222.333-06', '(11) 91111-0006', 'fernanda.rodrigues@email.com'],
            ['Gabriel Martins',   '111.222.333-07', '(11) 91111-0007', 'gabriel.martins@email.com'],
            ['Helena Sousa',      '111.222.333-08', '(11) 91111-0008', 'helena.sousa@email.com'],
            ['Igor Ribeiro',      '111.222.333-09', '(11) 91111-0009', 'igor.ribeiro@email.com'],
            ['Juliana Mendes',    '111.222.333-10', '(11) 91111-0010', 'juliana.mendes@email.com'],
            ['Kleber Nunes',      '111.222.333-11', '(11) 91111-0011', 'kleber.nunes@email.com'],
            ['Larissa Pereira',   '111.222.333-12', '(11) 91111-0012', 'larissa.pereira@email.com'],
            ['Marcos Gomes',      '111.222.333-13', '(11) 91111-0013', 'marcos.gomes@email.com'],
            ['Natalia Araújo',    '111.222.333-14', '(11) 91111-0014', 'natalia.araujo@email.com'],
            ['Otávio Carvalho',   '111.222.333-15', '(11) 91111-0015', 'otavio.carvalho@email.com'],
        ];

        $pj = [
            ['Tech Solutions LTDA',        'TS Digital',         '21.111.222/0001-01', '(21) 3111-0001', 'ti@techsolutions.com.br'],
            ['Mega Store Informática ME',   'Mega Store',         '21.111.222/0001-02', '(21) 3111-0002', 'suporte@megastore.com.br'],
            ['Alfa Contabilidade SS LTDA',  'Alfa Contabilidade', '21.111.222/0001-03', '(21) 3111-0003', 'ti@alfacontabil.com.br'],
            ['Beta Sistemas LTDA',          'Beta Sistemas',      '21.111.222/0001-04', '(21) 3111-0004', 'helpdesk@betasistemas.com.br'],
            ['Delta Consultoria LTDA',      'Delta Consult',      '21.111.222/0001-05', '(21) 3111-0005', 'suporte@deltaconsult.com.br'],
        ];

        $result = [];

        foreach ($pf as [$name, $doc, $phone, $email]) {
            $c = Customer::query()->create([
                'company_id'    => $company->id,
                'customer_type' => 'PF',
                'name'          => $name,
                'document'      => $doc,
            ]);
            CustomerContact::query()->create([
                'company_id'       => $company->id,
                'customer_id'      => $c->id,
                'label'            => 'Principal',
                'name'             => $name,
                'phone'            => $phone,
                'email'            => $email,
                'normalized_email' => $email,
                'normalized_phone' => preg_replace('/\D+/', '', $phone),
            ]);
            $result[] = $c;
        }

        foreach ($pj as [$name, $trade, $doc, $phone, $email]) {
            $c = Customer::query()->create([
                'company_id'    => $company->id,
                'customer_type' => 'PJ',
                'name'          => $name,
                'trade_name'    => $trade,
                'document'      => $doc,
            ]);
            CustomerContact::query()->create([
                'company_id'       => $company->id,
                'customer_id'      => $c->id,
                'label'            => 'TI',
                'name'             => 'Responsável TI',
                'phone'            => $phone,
                'email'            => $email,
                'normalized_email' => $email,
                'normalized_phone' => preg_replace('/\D+/', '', $phone),
            ]);
            $result[] = $c;
        }

        return $result;
    }

    private function seedEquipments(Company $company, array $customers): array
    {
        $specs = [
            ['notebook',    'Dell',     'Inspiron 15 3520'],
            ['notebook',    'Lenovo',   'IdeaPad 3'],
            ['notebook',    'HP',       'Pavilion 14'],
            ['notebook',    'Asus',     'VivoBook 15'],
            ['notebook',    'Acer',     'Aspire 5'],
            ['smartphone',  'Samsung',  'Galaxy A54'],
            ['smartphone',  'Apple',    'iPhone 14'],
            ['smartphone',  'Motorola', 'Moto G84'],
            ['desktop',     'Dell',     'OptiPlex 3090'],
            ['desktop',     'HP',       'ProDesk 400 G7'],
            ['desktop',     'Lenovo',   'ThinkCentre Neo 50'],
            ['tablet',      'Samsung',  'Galaxy Tab A8'],
            ['tablet',      'Apple',    'iPad 10ª geração'],
            ['impressora',  'HP',       'LaserJet Pro M404dn'],
            ['impressora',  'Epson',    'EcoTank L3250'],
            ['monitor',     'LG',       '24MK430H'],
            ['monitor',     'Dell',     'P2422H'],
        ];

        $result = [];
        $sn = 1000;

        foreach ($customers as $idx => $customer) {
            $count = random_int(2, 4);
            for ($i = 0; $i < $count; $i++) {
                $spec = $specs[($idx * $count + $i) % count($specs)];
                $e = Equipment::query()->create([
                    'company_id'    => $company->id,
                    'customer_id'   => $customer->id,
                    'category'      => $spec[0],
                    'brand'         => $spec[1],
                    'model'         => $spec[2],
                    'serial_number' => strtoupper(substr($spec[1], 0, 3)) . '-BULK-' . $sn++,
                ]);
                $result[] = $e;
            }
        }

        return $result;
    }

    private function seedServiceOrders(
        Company $company,
        Membership $admin,
        Membership $tech,
        array $customers,
        array $equipments,
    ): void {
        $counter  = CompanyServiceOrderCounter::query()->where('company_id', $company->id)->firstOrFail();
        $osNum    = $counter->next_service_order_number;
        $draftNum = $counter->next_draft_number;
        $year     = now()->year;

        $statuses = [
            ...array_fill(0, 5,  ServiceOrder::STATUS_DRAFT),
            ...array_fill(0, 18, ServiceOrder::STATUS_OPEN),
            ...array_fill(0, 14, ServiceOrder::STATUS_IN_PROGRESS),
            ...array_fill(0, 12, ServiceOrder::STATUS_READY_FOR_PICKUP),
            ...array_fill(0, 12, ServiceOrder::STATUS_DELIVERED),
            ...array_fill(0, 4,  ServiceOrder::STATUS_CANCELLED),
        ];

        $problems = [
            'Não liga após queda', 'Tela quebrada', 'Bateria não carrega', 'Lentidão extrema',
            'Superaquecimento', 'Teclado com teclas travadas', 'Vírus/malware detectado',
            'HD com ruídos suspeitos', 'Sem sinal de vídeo', 'Sem acesso à rede',
            'Impressora não imprime', 'Papel enroscando na impressora', 'Touchpad não responde',
            'USB não reconhece dispositivos', 'Falha na inicialização do sistema',
            'Tela piscando', 'Alto ruído do cooler', 'Botão liga/desliga com defeito',
            'Câmera não funciona', 'Microfone sem sinal',
        ];

        $i = 0;
        foreach ($statuses as $status) {
            $customer  = $customers[$i % count($customers)];
            $equipment = $equipments[$i % count($equipments)];
            $contact   = $customer->contacts->first();
            $days      = $i + 1;

            if ($status === ServiceOrder::STATUS_DRAFT) {
                $reference      = sprintf('RASC-%04d', $draftNum++);
                $orderNumber    = null;
            } else {
                $reference      = sprintf('OS-%d-%05d', $year, $osNum);
                $orderNumber    = $reference;
                $osNum++;
            }

            $data = [
                'company_id'         => $company->id,
                'reference'          => $reference,
                'draft_reference'    => $status === ServiceOrder::STATUS_DRAFT ? $reference : null,
                'order_number'       => $orderNumber,
                'title'              => $problems[$i % count($problems)],
                'status'             => $status,
                'customer_id'        => $customer->id,
                'customer_contact_id' => $contact?->id,
                'equipment_id'       => $equipment->id,
                'reported_problem'   => $problems[$i % count($problems)] . '. Aguardando diagnóstico.',
                'equipment_description' => "{$equipment->brand} {$equipment->model}",
                'assigned_membership_id' => $status !== ServiceOrder::STATUS_DRAFT ? $tech->id : null,
                'opened_at'          => $status !== ServiceOrder::STATUS_DRAFT
                    ? now()->subDays($days)->toDateTimeString()
                    : null,
            ];

            if (in_array($status, [ServiceOrder::STATUS_IN_PROGRESS, ServiceOrder::STATUS_READY_FOR_PICKUP, ServiceOrder::STATUS_DELIVERED])) {
                $data['diagnosis_completed_at'] = now()->subDays(max(1, $days - 2))->toDateTimeString();
                $data['execution_started_at']   = now()->subDays(max(1, $days - 3))->toDateTimeString();
                $data['execution_started_by_membership_id'] = $tech->id;
            }

            if (in_array($status, [ServiceOrder::STATUS_READY_FOR_PICKUP, ServiceOrder::STATUS_DELIVERED])) {
                $data['technical_completed_at'] = now()->subDays(max(0, $days - 4))->toDateTimeString();
            }

            if ($status === ServiceOrder::STATUS_DELIVERED) {
                $data['delivered_at'] = now()->subDays(max(0, $days - 5))->toDateTimeString();
            }

            ServiceOrder::query()->create($data);
            $i++;
        }

        $counter->update([
            'next_service_order_number' => $osNum,
            'next_draft_number'         => $draftNum,
        ]);
    }
}
