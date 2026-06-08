<?php

namespace App\Domains\ServiceOrders\Models;

use App\Domains\Companies\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'company_id',
    'category',
    'version',
    'title',
    'items',
])]
class IntakeChecklistTemplate extends Model
{
    use BelongsToCompany;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'items' => AsArrayObject::class,
        ];
    }
}
