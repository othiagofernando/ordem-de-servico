<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Models\Company;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PasswordlessCompanyLookupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            return response()->json(['companies' => []]);
        }

        $memberships = Membership::query()
            ->withoutGlobalScopes()
            ->with('company')
            ->where('user_id', $user->getKey())
            ->where('status', Membership::STATUS_ACTIVE)
            ->whereHas('company', fn ($query) => $query
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->where('status', Company::STATUS_ACTIVE))
            ->get();

        $companies = $memberships->map(fn (Membership $m) => [
            'uuid'                      => $m->company->uuid,
            'name'                      => $m->company->name,
            'passwordless_code_enabled' => (bool) $m->company->passwordless_code_enabled,
            'magic_link_enabled'        => (bool) $m->company->magic_link_enabled,
        ]);

        return response()->json(['companies' => $companies]);
    }
}
