<?php

namespace App\Domains\Parts\Models;

use App\Domains\Companies\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Part extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'category',
        'sku',
        'unit',
        'stock_quantity',
        'min_stock_quantity',
        'cost_price_cents',
        'sale_price_cents',
        'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'stock_quantity' => 'decimal:3',
            'min_stock_quantity' => 'decimal:3',
        ];
    }

    public function serviceOrderParts(): HasMany
    {
        return $this->hasMany(ServiceOrderPart::class);
    }

    public function isLowStock(): bool
    {
        if ((float) $this->min_stock_quantity <= 0) {
            return false;
        }

        return (float) $this->stock_quantity <= (float) $this->min_stock_quantity;
    }
}
