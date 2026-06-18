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
    'document_footer_text',
    'intake_checklists_enabled',
    'currency',
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
    'notify_service_order_opened',
    'notify_service_order_opened_text',
    'notify_service_order_in_progress',
    'notify_service_order_in_progress_text',
    'notify_service_order_completed',
    'notify_service_order_completed_text',
    'notify_service_order_delivered',
    'notify_service_order_delivered_text',
    'notify_service_order_cancelled',
    'notify_service_order_cancelled_text',
    'notify_service_order_no_repair',
    'notify_service_order_no_repair_text',
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
            'document_footer_text' => null,
            'intake_checklists_enabled' => true,
            'currency' => 'BRL',
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
            'notify_service_order_opened' => false,
            'notify_service_order_opened_text' => null,
            'notify_service_order_in_progress' => false,
            'notify_service_order_in_progress_text' => null,
            'notify_service_order_completed' => true,
            'notify_service_order_completed_text' => null,
            'notify_service_order_delivered' => true,
            'notify_service_order_delivered_text' => null,
            'notify_service_order_cancelled' => false,
            'notify_service_order_cancelled_text' => null,
            'notify_service_order_no_repair' => true,
            'notify_service_order_no_repair_text' => null,
            'notification_footer_text' => null,
        ];
    }

    protected function casts(): array
    {
        return [
            'default_quote_validity_days' => 'integer',
            'intake_checklists_enabled' => 'bool',
            'block_delivery_with_outstanding_balance' => 'bool',
            'notify_quote_available' => 'bool',
            'notify_quote_expiring' => 'bool',
            'notify_ready_for_pickup' => 'bool',
            'notify_payment_receipt' => 'bool',
            'notify_service_order_opened' => 'bool',
            'notify_service_order_in_progress' => 'bool',
            'notify_service_order_completed' => 'bool',
            'notify_service_order_delivered' => 'bool',
            'notify_service_order_cancelled' => 'bool',
            'notify_service_order_no_repair' => 'bool',
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
            'service_order_opened' => $this->notify_service_order_opened,
            'service_order_in_progress' => $this->notify_service_order_in_progress,
            'service_order_completed' => $this->notify_service_order_completed,
            'service_order_delivered' => $this->notify_service_order_delivered,
            'service_order_cancelled' => $this->notify_service_order_cancelled,
            'service_order_no_repair' => $this->notify_service_order_no_repair,
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
            'service_order_opened' => $this->notify_service_order_opened_text,
            'service_order_in_progress' => $this->notify_service_order_in_progress_text,
            'service_order_completed' => $this->notify_service_order_completed_text,
            'service_order_delivered' => $this->notify_service_order_delivered_text,
            'service_order_cancelled' => $this->notify_service_order_cancelled_text,
            'service_order_no_repair' => $this->notify_service_order_no_repair_text,
            default => null,
        };
    }
}
