<?php

namespace App\Domains\Companies\Models;

use App\Domains\Companies\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'company_id',
    'brand_name',
    'portal_intro_text',
    'default_quote_validity_days',
    'notify_quote_available',
    'notify_quote_expiring',
    'notify_ready_for_pickup',
    'notify_payment_receipt',
])]
class CompanyOperationalSetting extends Model
{
    use BelongsToCompany;

    public const DEFAULT_QUOTE_VALIDITY_DAYS = 7;

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'brand_name' => null,
            'portal_intro_text' => 'Acompanhe aqui o status da sua ordem de servico e responda ao orcamento atual.',
            'default_quote_validity_days' => self::DEFAULT_QUOTE_VALIDITY_DAYS,
            'notify_quote_available' => true,
            'notify_quote_expiring' => true,
            'notify_ready_for_pickup' => true,
            'notify_payment_receipt' => true,
        ];
    }

    protected function casts(): array
    {
        return [
            'default_quote_validity_days' => 'integer',
            'notify_quote_available' => 'bool',
            'notify_quote_expiring' => 'bool',
            'notify_ready_for_pickup' => 'bool',
            'notify_payment_receipt' => 'bool',
        ];
    }

    public function brandName(Company $company): string
    {
        return $this->brand_name ?: $company->name;
    }

    public function notificationEnabled(string $eventType): bool
    {
        return match ($eventType) {
            'quote_available' => $this->notify_quote_available,
            'quote_expiring' => $this->notify_quote_expiring,
            'ready_for_pickup' => $this->notify_ready_for_pickup,
            'payment_receipt' => $this->notify_payment_receipt,
            default => true,
        };
    }
}
