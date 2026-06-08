<?php

namespace App\Console\Commands;

use App\Domains\Companies\Actions\BootstrapCompany;
use Illuminate\Console\Command;

class BootstrapCompanyCommand extends Command
{
    protected $signature = 'company:bootstrap
        {company_name : Display name for the company}
        {admin_name : Display name for the first administrator}
        {admin_email : Email address for the first administrator}
        {--password= : Password for the first administrator}
        {--company-document= : Company document/registration number}
        {--company-email= : Company contact email}
        {--company-phone= : Company contact phone}
        {--token-name=bootstrap : Label for the generated API token}';

    protected $description = 'Bootstrap a company, its first administrator, and a company-bound API token.';

    public function handle(BootstrapCompany $bootstrapCompany): int
    {
        $result = $bootstrapCompany->handle(
            companyName: $this->argument('company_name'),
            adminName: $this->argument('admin_name'),
            adminEmail: $this->argument('admin_email'),
            password: $this->option('password') ?: null,
            companyDocument: $this->option('company-document') ?: null,
            companyEmail: $this->option('company-email') ?: null,
            companyPhone: $this->option('company-phone') ?: null,
            tokenName: $this->option('token-name'),
        );

        $this->info('Company bootstrap completed.');
        $this->newLine();
        $this->line('Company UUID: '.$result->company->uuid);
        $this->line('Administrator email: '.$result->user->email);
        $this->line('Membership role: '.$result->membership->role);
        $this->line('Generated password: '.$result->plainTextPassword);
        $this->line('API token: '.$result->plainTextToken);

        return self::SUCCESS;
    }
}
