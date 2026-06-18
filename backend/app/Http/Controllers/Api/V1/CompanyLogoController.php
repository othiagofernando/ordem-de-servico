<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Companies\Support\CurrentCompanyContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CompanyLogoController extends Controller
{
    public function __invoke(Request $request, CurrentCompanyContext $context): JsonResponse
    {
        $company = $context->company();

        abort_if($company === null, Response::HTTP_FORBIDDEN, 'Missing active company context.');

        $payload = $request->validate([
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $file = $payload['logo'];
        $extension = $file->getClientOriginalExtension();
        $path = sprintf(
            'company-assets/%d/logo-%s%s',
            $company->getKey(),
            Str::uuid()->toString(),
            $extension !== '' ? '.'.$extension : '',
        );

        $file->storeAs(dirname($path), basename($path), ['disk' => 'public']);

        if ($company->logo_path) {
            Storage::disk('public')->delete($company->logo_path);
        }

        $company->forceFill([
            'logo_path' => $path,
        ])->save();

        return response()->json([
            'data' => [
                'logo_url' => Storage::disk('public')->url($path),
                'logo_path' => $path,
            ],
        ], Response::HTTP_CREATED);
    }
}
