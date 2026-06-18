<?php

namespace Database\Seeders;

use App\Domains\Auth\Models\ApiToken;
use App\Domains\Auth\Models\Membership;
use App\Domains\Billing\Models\Plan;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Companies\Models\Company;
use App\Domains\Companies\Models\CompanyOperationalSetting;
use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Models\CustomerAddress;
use App\Domains\Customers\Models\CustomerContact;
use App\Domains\Equipments\Models\Equipment;
use App\Domains\ServiceOrders\Models\CompanyServiceOrderCounter;
use App\Domains\ServiceOrders\Models\QuoteRevision;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Domains\ServiceOrders\Models\ServiceOrderCharge;
use App\Domains\ServiceOrders\Models\ServiceOrderExecutionEntry;
use App\Domains\ServiceOrders\Models\ServiceOrderPayment;
use App\Domains\ServiceOrders\Models\ServiceOrderTimelineEvent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DevSeeder extends Seeder
{
    private string $adminToken = 'dev-admin-token-aaaabbbbccccddddeeeeffffgggghhhhiiiijjjjkkkkllll';

    private string $techToken = 'dev-tech-token-aaaabbbbccccddddeeeeffffgggghhhhiiiijjjjkkkkllll0';

    public function run(): void
    {
        DB::transaction(function (): void {
            $company = $this->createCompany();
            [$adminMembership, $techMembership] = $this->createUsersAndMemberships($company);
            $customers = $this->createCustomers($company);
            $equipments = $this->createEquipments($company, $customers);
            $this->createServiceOrders($company, $adminMembership, $techMembership, $customers, $equipments);
            $this->createAdminPanelDemoCompanies();
        });

        $this->command->info('');
        $this->command->info('=== DEV SEED CONCLUÍDO ===');
        $this->command->info('');
        $this->command->info('Empresa: TechFix Assistência Técnica');
        $this->command->info('');
        $this->command->info('--- ADMIN ---');
        $this->command->info('Email: admin@techfix.dev');
        $this->command->info('Senha: Admin@1234');
        $this->command->info('Token: ' . $this->adminToken);
        $this->command->info('');
        $this->command->info('--- TÉCNICO ---');
        $this->command->info('Email: tecnico@techfix.dev');
        $this->command->info('Senha: Tecnico@1234');
        $this->command->info('Token: ' . $this->techToken);
        $this->command->info('');
        $this->command->info('Exemplo de uso:');
        $this->command->info('  Authorization: Bearer ' . $this->adminToken);
        $this->command->info('');
        $this->command->info('Empresas extras do painel admin:');
        $this->command->info('  - Oficina Centro SP (ativa)');
        $this->command->info('  - Laboratório Norte (suspensa)');
        $this->command->info('  - Base Litoral (cancelada)');
        $this->command->info('');
    }

    private function createCompany(): Company
    {
        $company = Company::query()->create([
            'uuid'       => Str::uuid()->toString(),
            'name'       => 'TechFix Assistência Técnica',
            'document'   => '12.345.678/0001-90',
            'email'      => 'contato@techfix.dev',
            'phone'      => '(11) 99999-0001',
            'is_active'  => true,
        ]);

        CompanyOperationalSetting::query()->create([
            'company_id'                  => $company->id,
            'brand_name'                  => 'TechFix',
            'portal_intro_text'           => 'Olá! Seu orçamento está disponível para aprovação. Por favor, analise os itens abaixo e nos informe sua decisão.',
            'default_quote_validity_days' => 7,
            'notify_quote_available'      => true,
            'notify_quote_expiring'       => true,
            'notify_ready_for_pickup'     => true,
            'notify_payment_receipt'      => true,
        ]);

        CompanyServiceOrderCounter::query()->create([
            'company_id'                 => $company->id,
            'next_draft_number'          => 6,
            'next_service_order_number'  => 6,
        ]);

        $company->subscription?->forceFill([
            'plan_id' => Plan::query()->where('slug', 'profissional')->value('id') ?? $company->subscription?->plan_id,
            'started_at' => now()->subDays(20),
            'expires_at' => now()->addDays(12),
            'status' => Subscription::STATUS_ACTIVE,
        ])->save();

        return $company;
    }

    private function createUsersAndMemberships(Company $company): array
    {
        $adminUser = User::query()->create([
            'name'        => 'Thiago (Admin)',
            'email'       => 'admin@techfix.dev',
            'password'    => bcrypt('Admin@1234'),
            'super_admin' => true,
        ]);

        $techUser = User::query()->create([
            'name'     => 'Carlos (Técnico)',
            'email'    => 'tecnico@techfix.dev',
            'password' => bcrypt('Tecnico@1234'),
        ]);

        $adminMembership = Membership::query()->create([
            'company_id' => $company->id,
            'user_id'    => $adminUser->id,
            'role'       => 'administrator',
            'status'     => 'active',
        ]);

        $techMembership = Membership::query()->create([
            'company_id' => $company->id,
            'user_id'    => $techUser->id,
            'role'       => 'technician',
            'status'     => 'active',
        ]);

        ApiToken::query()->create([
            'company_id'  => $company->id,
            'user_id'     => $adminUser->id,
            'name'        => 'Dev Token Admin',
            'token_hash'  => hash('sha256', $this->adminToken),
            'expires_at'  => now()->addYear(),
        ]);

        ApiToken::query()->create([
            'company_id'  => $company->id,
            'user_id'     => $techUser->id,
            'name'        => 'Dev Token Técnico',
            'token_hash'  => hash('sha256', $this->techToken),
            'expires_at'  => now()->addYear(),
        ]);

        return [$adminMembership, $techMembership];
    }

    private function createAdminPanelDemoCompanies(): void
    {
        $this->createGenericCompany(
            name: 'Oficina Centro SP',
            document: '21.111.111/0001-10',
            email: 'contato@oficinacentro.test',
            phone: '(11) 4000-1001',
            status: Company::STATUS_ACTIVE,
            planSlug: 'starter',
            subscriptionStatus: Subscription::STATUS_ACTIVE,
            expiresAt: now()->addDays(5),
            members: [
                ['name' => 'Marina Costa', 'email' => 'marina@oficinacentro.test', 'role' => 'administrator'],
                ['name' => 'Rafael Lima', 'email' => 'rafael@oficinacentro.test', 'role' => 'technician'],
                ['name' => 'Paula Nunes', 'email' => 'paula@oficinacentro.test', 'role' => 'attendant'],
            ],
        );

        $this->createGenericCompany(
            name: 'Laboratório Norte',
            document: '21.111.111/0001-11',
            email: 'contato@laboratorionorte.test',
            phone: '(11) 4000-1002',
            status: Company::STATUS_SUSPENDED,
            planSlug: 'starter',
            subscriptionStatus: Subscription::STATUS_SUSPENDED,
            expiresAt: now()->subDays(8),
            members: [
                ['name' => 'Bruno Martins', 'email' => 'bruno@laboratorionorte.test', 'role' => 'administrator'],
                ['name' => 'Celia Rocha', 'email' => 'celia@laboratorionorte.test', 'role' => 'technician'],
            ],
        );

        $this->createGenericCompany(
            name: 'Base Litoral',
            document: '21.111.111/0001-12',
            email: 'contato@baselitoral.test',
            phone: '(13) 4000-1003',
            status: Company::STATUS_CANCELLED,
            planSlug: 'enterprise',
            subscriptionStatus: Subscription::STATUS_CANCELLED,
            expiresAt: now()->subDays(40),
            members: [
                ['name' => 'Igor Teixeira', 'email' => 'igor@baselitoral.test', 'role' => 'administrator'],
            ],
        );
    }

    /**
     * @param  array<int, array{name: string, email: string, role: string}>  $members
     */
    private function createGenericCompany(
        string $name,
        string $document,
        string $email,
        string $phone,
        string $status,
        string $planSlug,
        string $subscriptionStatus,
        \Illuminate\Support\Carbon $expiresAt,
        array $members,
    ): Company {
        $company = Company::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => $name,
            'document' => $document,
            'email' => $email,
            'phone' => $phone,
            'is_active' => $status === Company::STATUS_ACTIVE,
            'status' => $status,
        ]);

        CompanyOperationalSetting::query()->create([
            'company_id' => $company->id,
            ...CompanyOperationalSetting::defaults(),
        ]);

        CompanyServiceOrderCounter::query()->create([
            'company_id' => $company->id,
            'next_draft_number' => 1,
            'next_service_order_number' => 1,
        ]);

        $company->subscription?->forceFill([
            'plan_id' => Plan::query()->where('slug', $planSlug)->value('id') ?? $company->subscription?->plan_id,
            'started_at' => now()->subDays(15),
            'expires_at' => $expiresAt,
            'status' => $subscriptionStatus,
        ])->save();

        foreach ($members as $member) {
            $user = User::query()->create([
                'name' => $member['name'],
                'email' => $member['email'],
                'password' => bcrypt('Senha@1234'),
            ]);

            Membership::query()->create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'role' => $member['role'],
                'status' => Membership::STATUS_ACTIVE,
            ]);
        }

        if ($status === Company::STATUS_CANCELLED) {
            $company->delete();
        }

        return $company;
    }

    private function createCustomers(Company $company): array
    {
        // Cliente PF 1
        $joao = Customer::query()->create([
            'company_id'    => $company->id,
            'customer_type' => 'PF',
            'name'          => 'João Silva',
            'document'      => '123.456.789-00',
            'notes'         => 'Cliente frequente. Prefere contato por WhatsApp.',
        ]);
        CustomerContact::query()->create([
            'company_id'       => $company->id,
            'customer_id'      => $joao->id,
            'label'            => 'Pessoal',
            'name'             => 'João Silva',
            'phone'            => '(11) 98888-1111',
            'email'            => 'joao.silva@email.com',
            'normalized_email' => 'joao.silva@email.com',
            'normalized_phone' => '11988881111',
        ]);
        CustomerAddress::query()->create([
            'company_id'  => $company->id,
            'customer_id' => $joao->id,
            'label'       => 'Residencial',
            'recipient'   => 'João Silva',
            'line_1'      => 'Rua das Flores, 123',
            'line_2'      => 'Apto 42',
            'district'    => 'Jardim Primavera',
            'city'        => 'São Paulo',
            'state'       => 'SP',
            'postal_code' => '01310-100',
        ]);

        // Cliente PF 2
        $maria = Customer::query()->create([
            'company_id'    => $company->id,
            'customer_type' => 'PF',
            'name'          => 'Maria Oliveira',
            'document'      => '987.654.321-00',
        ]);
        CustomerContact::query()->create([
            'company_id'       => $company->id,
            'customer_id'      => $maria->id,
            'label'            => 'Principal',
            'name'             => 'Maria Oliveira',
            'phone'            => '(11) 97777-2222',
            'email'            => 'maria.oliveira@email.com',
            'normalized_email' => 'maria.oliveira@email.com',
            'normalized_phone' => '11977772222',
        ]);

        // Cliente PJ
        $empresa = Customer::query()->create([
            'company_id'    => $company->id,
            'customer_type' => 'PJ',
            'name'          => 'Grupo Digital Comércio LTDA',
            'trade_name'    => 'GD Digital',
            'document'      => '98.765.432/0001-10',
            'notes'         => 'Contrato de manutenção mensal. Faturar no CNPJ.',
        ]);
        CustomerContact::query()->create([
            'company_id'       => $company->id,
            'customer_id'      => $empresa->id,
            'label'            => 'TI',
            'name'             => 'Lucas Mendes',
            'phone'            => '(11) 3333-4444',
            'email'            => 'ti@gddigital.com.br',
            'normalized_email' => 'ti@gddigital.com.br',
            'normalized_phone' => '1133334444',
        ]);
        CustomerContact::query()->create([
            'company_id'       => $company->id,
            'customer_id'      => $empresa->id,
            'label'            => 'Financeiro',
            'name'             => 'Ana Souza',
            'phone'            => '(11) 3333-5555',
            'email'            => 'financeiro@gddigital.com.br',
            'normalized_email' => 'financeiro@gddigital.com.br',
            'normalized_phone' => '1133335555',
        ]);
        CustomerAddress::query()->create([
            'company_id'  => $company->id,
            'customer_id' => $empresa->id,
            'label'       => 'Sede',
            'recipient'   => 'Grupo Digital Comércio LTDA',
            'line_1'      => 'Av. Paulista, 1000',
            'line_2'      => 'Sala 510',
            'district'    => 'Bela Vista',
            'city'        => 'São Paulo',
            'state'       => 'SP',
            'postal_code' => '01310-000',
        ]);

        return [$joao, $maria, $empresa];
    }

    private function createEquipments(Company $company, array $customers): array
    {
        [$joao, $maria, $empresa] = $customers;

        $notebook = Equipment::query()->create([
            'company_id'    => $company->id,
            'customer_id'   => $joao->id,
            'category'      => 'notebook',
            'brand'         => 'Dell',
            'model'         => 'Inspiron 15 3520',
            'serial_number' => 'DL-2024-XY1234',
            'accessories'   => 'Carregador original, mochila',
            'notes'         => 'Tela com pequeno risco no canto superior direito (pré-existente)',
        ]);

        $celular = Equipment::query()->create([
            'company_id'    => $company->id,
            'customer_id'   => $maria->id,
            'category'      => 'smartphone',
            'brand'         => 'Samsung',
            'model'         => 'Galaxy S23',
            'serial_number' => 'SM-S23-MARIA0001',
            'accessories'   => 'Cabo USB-C',
        ]);

        $pc1 = Equipment::query()->create([
            'company_id'    => $company->id,
            'customer_id'   => $empresa->id,
            'category'      => 'desktop',
            'brand'         => 'Lenovo',
            'model'         => 'ThinkCentre M720',
            'serial_number' => 'LNV-TC-M720-001',
            'notes'         => 'Patrimônio GD-001',
        ]);

        $pc2 = Equipment::query()->create([
            'company_id'    => $company->id,
            'customer_id'   => $empresa->id,
            'category'      => 'desktop',
            'brand'         => 'Lenovo',
            'model'         => 'ThinkCentre M720',
            'serial_number' => 'LNV-TC-M720-002',
            'notes'         => 'Patrimônio GD-002',
        ]);

        $impressora = Equipment::query()->create([
            'company_id'    => $company->id,
            'customer_id'   => $empresa->id,
            'category'      => 'impressora',
            'brand'         => 'HP',
            'model'         => 'LaserJet Pro M404n',
            'serial_number' => 'HP-LJ-M404-GD001',
            'notes'         => 'Patrimônio GD-010',
        ]);

        return [$notebook, $celular, $pc1, $pc2, $impressora];
    }

    private function createServiceOrders(
        Company $company,
        Membership $adminMembership,
        Membership $techMembership,
        array $customers,
        array $equipments,
    ): void {
        [$joao, $maria, $empresa] = $customers;
        [$joaoContact] = $joao->contacts->all();
        [$mariaContact] = $maria->contacts->all();
        [$empresaContact] = $empresa->contacts->all();
        [$notebook, $celular, $pc1, $pc2, $impressora] = $equipments;

        // OS 1 — Rascunho (draft)
        ServiceOrder::query()->create([
            'company_id'         => $company->id,
            'reference'          => 'OS-2026-00001',
            'draft_reference'    => 'RASC-0001',
            'title'              => 'Notebook não liga',
            'status'             => ServiceOrder::STATUS_DRAFT,
            'customer_id'        => $joao->id,
            'customer_contact_id' => $joaoContact->id,
            'equipment_id'       => $notebook->id,
            'reported_problem'   => 'Cliente relata que o notebook não liga mais após queda.',
            'equipment_description' => 'Dell Inspiron 15 3520 / SN: DL-2024-XY1234',
            'intake_checklist'   => [
                ['item' => 'Carregador incluído', 'ok' => true],
                ['item' => 'Tela integra', 'ok' => false, 'note' => 'Risco no canto superior direito (pré-existente)'],
                ['item' => 'Teclado completo', 'ok' => true],
            ],
        ]);

        // OS 2 — Aberta (open), aguardando diagnóstico
        $os2 = ServiceOrder::query()->create([
            'company_id'          => $company->id,
            'reference'           => 'OS-2026-00002',
            'order_number'        => 'OS-2026-00002',
            'title'               => 'Celular com tela trincada',
            'status'              => ServiceOrder::STATUS_OPEN,
            'customer_id'         => $maria->id,
            'customer_contact_id' => $mariaContact->id,
            'equipment_id'        => $celular->id,
            'reported_problem'    => 'Cliente diz que derrubou o celular e a tela trincou. Ainda funciona mas está difícil de usar.',
            'equipment_description' => 'Samsung Galaxy S23 / SN: SM-S23-MARIA0001',
            'equipment_snapshot'  => [
                'brand'  => 'Samsung',
                'model'  => 'Galaxy S23',
                'serial' => 'SM-S23-MARIA0001',
            ],
            'assigned_membership_id' => $techMembership->id,
            'opened_at'           => now()->subDays(2),
            'intake_checklist'    => [
                ['item' => 'Cabo USB-C incluído', 'ok' => true],
                ['item' => 'Tela sem danos adicionais', 'ok' => false, 'note' => 'Tela trincada conforme relatado'],
                ['item' => 'Botões funcionando', 'ok' => true],
            ],
        ]);
        ServiceOrderTimelineEvent::query()->create([
            'company_id'          => $company->id,
            'service_order_id'    => $os2->id,
            'actor_membership_id' => $adminMembership->id,
            'type'                => 'order_opened',
            'visibility'          => 'internal',
            'title'               => 'Ordem de serviço aberta',
            'occurred_at'         => now()->subDays(2),
        ]);

        // OS 3 — Em diagnóstico → orçamento pendente
        $os3 = ServiceOrder::query()->create([
            'company_id'             => $company->id,
            'reference'              => 'OS-2026-00003',
            'order_number'           => 'OS-2026-00003',
            'title'                  => 'Desktop não inicializa (GD-001)',
            'status'                 => ServiceOrder::STATUS_OPEN,
            'customer_id'            => $empresa->id,
            'customer_contact_id'    => $empresaContact->id,
            'equipment_id'           => $pc1->id,
            'reported_problem'       => 'Máquina não inicializa, apresenta tela preta após POST.',
            'equipment_description'  => 'Lenovo ThinkCentre M720 / SN: LNV-TC-M720-001 / Patrimônio GD-001',
            'equipment_snapshot'     => [
                'brand'  => 'Lenovo',
                'model'  => 'ThinkCentre M720',
                'serial' => 'LNV-TC-M720-001',
            ],
            'assigned_membership_id'      => $techMembership->id,
            'diagnosis_internal_notes'    => 'HD com setores defeituosos. Sistema operacional corrompido. Recomenda troca do HD e reinstalação do Windows.',
            'diagnosis_customer_summary'  => 'Identificamos falha no disco rígido. Recomendamos substituição por SSD 240GB e reinstalação do sistema operacional.',
            'diagnosis_completed_at'      => now()->subDay(),
            'opened_at'                   => now()->subDays(4),
            'intake_checklist'            => [
                ['item' => 'Mouse incluso', 'ok' => false],
                ['item' => 'Teclado incluso', 'ok' => false],
                ['item' => 'Cabos de alimentação', 'ok' => true],
            ],
        ]);
        QuoteRevision::query()->create([
            'company_id'       => $company->id,
            'service_order_id' => $os3->id,
            'revision_number'  => 1,
            'status'           => QuoteRevision::STATUS_PENDING,
            'currency'         => 'BRL',
            'line_items'       => [
                ['description' => 'SSD 240GB Kingston', 'quantity' => 1, 'unit_price_cents' => 18000],
                ['description' => 'Mão de obra — reinstalação Windows 11', 'quantity' => 1, 'unit_price_cents' => 8000],
                ['description' => 'Mão de obra — migração de dados', 'quantity' => 1, 'unit_price_cents' => 5000],
            ],
            'total_cents' => 31000,
            'notes'       => 'Garantia de 90 dias na peça instalada.',
            'valid_until' => now()->addDays(7),
            'submitted_at' => now()->subHours(2),
        ]);
        ServiceOrderTimelineEvent::query()->create([
            'company_id'          => $company->id,
            'service_order_id'    => $os3->id,
            'actor_membership_id' => $techMembership->id,
            'type'                => 'diagnosis_completed',
            'visibility'          => 'internal',
            'title'               => 'Diagnóstico concluído',
            'occurred_at'         => now()->subDay(),
        ]);
        ServiceOrderTimelineEvent::query()->create([
            'company_id'          => $company->id,
            'service_order_id'    => $os3->id,
            'actor_membership_id' => $adminMembership->id,
            'type'                => 'quote_submitted',
            'visibility'          => 'internal',
            'title'               => 'Orçamento Rev. 1 enviado para aprovação',
            'occurred_at'         => now()->subHours(2),
        ]);

        // OS 4 — Em execução (in_progress), orçamento aprovado
        $os4 = ServiceOrder::query()->create([
            'company_id'             => $company->id,
            'reference'              => 'OS-2026-00004',
            'order_number'           => 'OS-2026-00004',
            'title'                  => 'Impressora sem comunicação na rede',
            'status'                 => ServiceOrder::STATUS_IN_PROGRESS,
            'customer_id'            => $empresa->id,
            'customer_contact_id'    => $empresaContact->id,
            'equipment_id'           => $impressora->id,
            'reported_problem'       => 'Impressora parou de aparecer na rede após troca de switch. Impressão direta via USB funciona.',
            'equipment_description'  => 'HP LaserJet Pro M404n / SN: HP-LJ-M404-GD001 / Patrimônio GD-010',
            'equipment_snapshot'     => [
                'brand'  => 'HP',
                'model'  => 'LaserJet Pro M404n',
                'serial' => 'HP-LJ-M404-GD001',
            ],
            'assigned_membership_id'      => $techMembership->id,
            'diagnosis_internal_notes'    => 'IP fixo configurado fora do range da nova sub-rede. Necessário reconfigurar IP e verificar drivers na estação.',
            'diagnosis_customer_summary'  => 'Problema de configuração de rede identificado. Será reconfigurado o endereço IP da impressora e atualizados os drivers nas estações.',
            'diagnosis_completed_at'      => now()->subDays(2),
            'execution_started_at'        => now()->subDay(),
            'execution_started_by_membership_id' => $techMembership->id,
            'opened_at'                   => now()->subDays(5),
        ]);
        QuoteRevision::query()->create([
            'company_id'       => $company->id,
            'service_order_id' => $os4->id,
            'revision_number'  => 1,
            'status'           => QuoteRevision::STATUS_APPROVED,
            'currency'         => 'BRL',
            'line_items'       => [
                ['description' => 'Mão de obra — reconfiguração de rede', 'quantity' => 1, 'unit_price_cents' => 12000],
                ['description' => 'Mão de obra — atualização de drivers (5 estações)', 'quantity' => 5, 'unit_price_cents' => 2000],
            ],
            'total_cents'   => 22000,
            'valid_until'   => now()->addDays(5),
            'submitted_at'  => now()->subDays(3),
            'decisioned_at' => now()->subDays(2),
        ]);
        ServiceOrderExecutionEntry::query()->create([
            'company_id'       => $company->id,
            'service_order_id' => $os4->id,
            'membership_id'    => $techMembership->id,
            'entry_type'       => 'configuration',
            'description'      => 'Reconfigurado IP fixo da impressora para 192.168.1.50 (faixa do novo switch)',
            'quantity'         => 1,
        ]);
        ServiceOrderExecutionEntry::query()->create([
            'company_id'       => $company->id,
            'service_order_id' => $os4->id,
            'membership_id'    => $techMembership->id,
            'entry_type'       => 'configuration',
            'description'      => 'Atualizado driver HP Universal nas estações GD-PC01 a GD-PC03. Restam GD-PC04 e GD-PC05.',
            'quantity'         => 3,
        ]);

        // OS 5 — Concluída e aguardando retirada (ready_for_pickup), com pagamento registrado
        $os5 = ServiceOrder::query()->create([
            'company_id'             => $company->id,
            'reference'              => 'OS-2026-00005',
            'order_number'           => 'OS-2026-00005',
            'title'                  => 'Desktop formatação e limpeza (GD-002)',
            'status'                 => ServiceOrder::STATUS_READY_FOR_PICKUP,
            'customer_id'            => $empresa->id,
            'customer_contact_id'    => $empresaContact->id,
            'equipment_id'           => $pc2->id,
            'reported_problem'       => 'Máquina muito lenta e cheia de vírus. Solicitar formatação completa e limpeza interna.',
            'equipment_description'  => 'Lenovo ThinkCentre M720 / SN: LNV-TC-M720-002 / Patrimônio GD-002',
            'equipment_snapshot'     => [
                'brand'  => 'Lenovo',
                'model'  => 'ThinkCentre M720',
                'serial' => 'LNV-TC-M720-002',
            ],
            'assigned_membership_id'             => $techMembership->id,
            'diagnosis_internal_notes'           => 'Malware diverso encontrado. HD com pouco espaço livre (92% ocupado). Limpeza física necessária — muito pó acumulado.',
            'diagnosis_customer_summary'         => 'Encontramos malware e excesso de pó interno. Realizaremos formatação, reinstalação do sistema e limpeza completa.',
            'diagnosis_completed_at'             => now()->subDays(6),
            'execution_started_at'               => now()->subDays(5),
            'execution_started_by_membership_id' => $techMembership->id,
            'technical_completed_at'             => now()->subDays(1),
            'final_test_results'                 => [
                'boot_time_seconds' => 18,
                'malware_found'     => false,
                'stress_test'       => 'passed',
            ],
            'opened_at' => now()->subDays(7),
        ]);
        $quote5 = QuoteRevision::query()->create([
            'company_id'       => $company->id,
            'service_order_id' => $os5->id,
            'revision_number'  => 1,
            'status'           => QuoteRevision::STATUS_APPROVED,
            'currency'         => 'BRL',
            'line_items'       => [
                ['description' => 'Mão de obra — formatação e reinstalação Windows 11', 'quantity' => 1, 'unit_price_cents' => 9000],
                ['description' => 'Mão de obra — limpeza interna', 'quantity' => 1, 'unit_price_cents' => 4000],
                ['description' => 'Licença Windows 11 Home', 'quantity' => 1, 'unit_price_cents' => 0, 'note' => 'OEM existente reativada'],
            ],
            'total_cents'   => 13000,
            'valid_until'   => now()->addDays(3),
            'submitted_at'  => now()->subDays(6),
            'decisioned_at' => now()->subDays(5),
        ]);
        ServiceOrderCharge::query()->create([
            'company_id'               => $company->id,
            'service_order_id'         => $os5->id,
            'source_quote_revision_id' => $quote5->id,
            'created_by_membership_id' => $adminMembership->id,
            'charge_type'              => 'quote_derived',
            'description'              => 'Serviços conforme Orçamento Rev. 1 (aprovado)',
            'amount_cents'             => 13000,
            'currency'                 => 'BRL',
        ]);
        ServiceOrderPayment::query()->create([
            'company_id'               => $company->id,
            'service_order_id'         => $os5->id,
            'received_by_membership_id' => $adminMembership->id,
            'amount_cents'             => 13000,
            'currency'                 => 'BRL',
            'payment_method'           => 'pix',
            'notes'                    => 'Pagamento integral via PIX. Chave CNPJ.',
            'received_at'              => now()->subHours(3),
            'receipt_snapshot'         => [
                'amount_cents'    => 13000,
                'currency'        => 'BRL',
                'payment_method'  => 'pix',
                'received_at'     => now()->subHours(3)->toISOString(),
                'service_order'   => 'OS-2026-00005',
                'customer'        => 'Grupo Digital Comércio LTDA',
            ],
        ]);
        ServiceOrderTimelineEvent::query()->create([
            'company_id'          => $company->id,
            'service_order_id'    => $os5->id,
            'actor_membership_id' => $adminMembership->id,
            'type'                => 'payment_recorded',
            'visibility'          => 'internal',
            'title'               => 'Pagamento de R$ 130,00 registrado (PIX)',
            'occurred_at'         => now()->subHours(3),
        ]);
        ServiceOrderTimelineEvent::query()->create([
            'company_id'          => $company->id,
            'service_order_id'    => $os5->id,
            'actor_membership_id' => $adminMembership->id,
            'type'                => 'ready_for_pickup',
            'visibility'          => 'public',
            'title'               => 'Equipamento pronto para retirada',
            'payload'             => ['notify_customer' => true],
            'occurred_at'         => now()->subHours(2),
        ]);
    }
}
