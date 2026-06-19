<?php

namespace App\Domains\Parts\Models;

use App\Domains\Companies\Concerns\BelongsToCompany;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceOrderPart extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'service_order_id',
        'part_id',
        'quantity',
        'unit_cost_price_cents',
        'unit_sale_price_cents',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
        ];
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }
}
