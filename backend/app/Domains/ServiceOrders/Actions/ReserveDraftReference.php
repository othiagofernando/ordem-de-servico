<?php

namespace App\Domains\ServiceOrders\Actions;

use App\Domains\ServiceOrders\Models\CompanyServiceOrderCounter;
use Illuminate\Support\Facades\DB;

class ReserveDraftReference
{
    public function handle(int $companyId): string
    {
        return DB::transaction(function () use ($companyId): string {
            $counter = $this->lockCounter($companyId);
            $currentNumber = $counter->next_draft_number;

            $counter->forceFill([
                'next_draft_number' => $currentNumber + 1,
            ])->save();

            return sprintf('DRF-%06d', $currentNumber);
        });
    }

    protected function lockCounter(int $companyId): CompanyServiceOrderCounter
    {
        $counter = CompanyServiceOrderCounter::query()
            ->where('company_id', $companyId)
            ->lockForUpdate()
            ->first();

        if ($counter !== null) {
            return $counter;
        }

        return CompanyServiceOrderCounter::query()->create([
            'company_id' => $companyId,
            'next_draft_number' => 1,
            'next_service_order_number' => 1,
        ]);
    }
}
