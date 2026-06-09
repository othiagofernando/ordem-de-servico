<?php

namespace App\Domains\Companies\Models;

use App\Domains\Companies\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'company_id',
    'brand_name',
    'customer_contact_line',
    'service_order_reference_prefix',
    'portal_intro_text',
    'portal_footer_text',
    'portal_approved_text',
    'portal_rejected_text',
    'default_quote_validity_days',
    'block_delivery_with_outstanding_balance',
    'notify_quote_available',
    'notify_quote_available_text',
    'notify_quote_expiring',
    'notify_quote_expiring_text',
    'notify_ready_for_pickup',
    'notify_ready_for_pickup_text',
    'notify_payment_receipt',
    'notify_payment_receipt_text',
    'notification_footer_text',
])]
class CompanyOperationalSetting extends Model
{
    use BelongsToCompany;

    public const DEFAULT_SERVICE_ORDER_REFERENCE_PREFIX = 'OS';

    public const DEFAULT_QUOTE_VALIDITY_DAYS = 7;

    public const DEFAULT_PORTAL_APPROVED_TEXT = 'Nossa equipe foi notificada e dará início ao serviço em breve.';

    public const DEFAULT_PORTAL_REJECTED_TEXT = 'Entraremos em contato para mais informações.';

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'brand_name' => null,
            'customer_contact_line' => null,
            'service_order_reference_prefix' => self::DEFAULT_SERVICE_ORDER_REFERENCE_PREFIX,
            'portal_intro_text' => 'Acompanhe aqui o status da sua ordem de servico e responda ao orcamento atual.',
            'portal_footer_text' => null,
            'portal_approved_text' => self::DEFAULT_PORTAL_APPROVED_TEXT,
            'portal_rejected_text' => self::DEFAULT_PORTAL_REJECTED_TEXT,
            'default_quote_validity_days' => self::DEFAULT_QUOTE_VALIDITY_DAYS,
            'block_delivery_with_outstanding_balance' => true,
            'notify_quote_available' => true,
            'notify_quote_available_text' => null,
            'notify_quote_expiring' => true,
            'notify_quote_expiring_text' => null,
            'notify_ready_for_pickup' => true,
            'notify_ready_for_pickup_text' => null,
            'notify_payment_receipt' => true,
            'notify_payment_receipt_text' => null,
            'notification_footer_text' => null,
        ];
    }

    protected function casts(): array
    {
        return [
            'default_quote_validity_days' => 'integer',
            'block_delivery_with_outstanding_balance' => 'bool',
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

    public function serviceOrderReferencePrefix(): string
    {
        return $this->service_order_reference_prefix ?: self::DEFAULT_SERVICE_ORDER_REFERENCE_PREFIX;
    }

    public function portalApprovedText(): string
    {
        return $this->portal_approved_text ?: self::DEFAULT_PORTAL_APPROVED_TEXT;
    }

    public function portalRejectedText(): string
    {
        return $this->portal_rejected_text ?: self::DEFAULT_PORTAL_REJECTED_TEXT;
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

    public function notificationIntroText(string $eventType): ?string
    {
        return match ($eventType) {
            'quote_available' => $this->notify_quote_available_text,
            'quote_expiring' => $this->notify_quote_expiring_text,
            'ready_for_pickup' => $this->notify_ready_for_pickup_text,
            'payment_receipt' => $this->notify_payment_receipt_text,
            default => null,
        };
    }
}
