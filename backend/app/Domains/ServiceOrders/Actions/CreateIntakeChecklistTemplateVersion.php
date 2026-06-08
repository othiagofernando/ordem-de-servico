<?php

namespace App\Domains\ServiceOrders\Actions;

use App\Domains\ServiceOrders\Models\IntakeChecklistTemplate;
use Illuminate\Support\Facades\DB;

class CreateIntakeChecklistTemplateVersion
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function handle(string $category, string $title, array $items): IntakeChecklistTemplate
    {
        return DB::transaction(function () use ($category, $title, $items): IntakeChecklistTemplate {
            $currentVersion = IntakeChecklistTemplate::query()
                ->where('category', $category)
                ->max('version');

            return IntakeChecklistTemplate::query()->create([
                'company_id' => currentCompany()?->getKey(),
                'category' => $category,
                'version' => ((int) $currentVersion) + 1,
                'title' => $title,
                'items' => $items,
            ]);
        });
    }
}
