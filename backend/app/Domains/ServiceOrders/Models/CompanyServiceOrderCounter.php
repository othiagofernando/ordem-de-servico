<?php

namespace App\Domains\ServiceOrders\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['company_id', 'next_draft_number', 'next_service_order_number'])]
class CompanyServiceOrderCounter extends Model
{
    public $timestamps = false;
}
