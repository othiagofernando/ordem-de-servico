<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domains\Billing\Models\Plan;
use App\Domains\Companies\Models\Company;
use App\Domains\Learning\Models\Playbook;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['data' => []]);
        }

        $limit   = 5;
        $results = [];

        // Empresas
        Company::query()
            ->where(function ($query) use ($q): void {
                $query->where('name', 'like', "%{$q}%")->orWhere('slug', 'like', "%{$q}%");
            })
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->each(function (Company $company) use (&$results): void {
                $results[] = [
                    'type'     => 'company',
                    'id'       => $company->getKey(),
                    'label'    => $company->name,
                    'sublabel' => $company->slug,
                    'meta'     => null,
                ];
            });

        // Planos
        Plan::query()
            ->where('name', 'like', "%{$q}%")
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->each(function (Plan $plan) use (&$results): void {
                $results[] = [
                    'type'     => 'plan',
                    'id'       => $plan->getKey(),
                    'label'    => $plan->name,
                    'sublabel' => 'Plano',
                    'meta'     => null,
                ];
            });

        // Playbooks
        Playbook::query()
            ->where(function ($query) use ($q): void {
                $query->where('title', 'like', "%{$q}%")->orWhere('description', 'like', "%{$q}%");
            })
            ->orderBy('title')
            ->limit($limit)
            ->get()
            ->each(function (Playbook $playbook) use (&$results): void {
                $results[] = [
                    'type'     => 'playbook',
                    'id'       => $playbook->getKey(),
                    'label'    => $playbook->title,
                    'sublabel' => $playbook->status === 'published' ? 'Publicado' : 'Rascunho',
                    'meta'     => $playbook->status,
                ];
            });

        // Usuários admin
        User::query()
            ->where(function ($query) use ($q): void {
                $query->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%");
            })
            ->where(function ($query): void {
                $query->where('super_admin', true)->orWhereNotNull('admin_role')->orWhereNotNull('admin_role_id');
            })
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->each(function (User $user) use (&$results): void {
                $results[] = [
                    'type'     => 'admin_user',
                    'id'       => $user->getKey(),
                    'label'    => $user->name,
                    'sublabel' => $user->email,
                    'meta'     => $user->super_admin ? 'super_admin' : ($user->admin_role ?? 'custom'),
                ];
            });

        return response()->json(['data' => $results]);
    }
}
