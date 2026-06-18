<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    $frontendUrl = rtrim((string) config('app.frontend_url', ''), '/');

    if ($frontendUrl !== '') {
        return redirect()->away($frontendUrl);
    }

    return redirect('/docs');
});

Route::middleware(config('scribe.laravel.middleware', []))
    ->group(function () {
        $docsUrl = config('scribe.laravel.docs_url', '/docs');

        Route::get($docsUrl, function () {
            $docsView = (string) config('scribe.laravel.docs_view', 'scribe.index');

            if (view()->exists($docsView)) {
                return response()->view($docsView);
            }

            $staticDocsPath = public_path('docs/index.html');

            if (is_file($staticDocsPath)) {
                return response()->file($staticDocsPath);
            }

            $frontendUrl = rtrim((string) config('app.frontend_url', ''), '/');
            $appLink = $frontendUrl !== ''
                ? sprintf('<p><a href="%s">Abrir aplicação</a></p>', e($frontendUrl))
                : '';

            return response(
                <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
  <head>
    <meta charset="utf-8">
    <title>Documentação indisponível</title>
  </head>
  <body>
    <h1>Documentação da API ainda não foi gerada.</h1>
    <p>Execute <code>php artisan scribe:generate</code> no backend para publicar a documentação em <code>/docs</code>.</p>
    {$appLink}
  </body>
</html>
HTML,
                503,
                ['Content-Type' => 'text/html; charset=UTF-8'],
            );
        })->name('scribe');

        Route::get("{$docsUrl}.postman", function () {
            abort_unless(Storage::disk('local')->exists('scribe/collection.json'), 404);

            return new JsonResponse(Storage::disk('local')->get('scribe/collection.json'), json: true);
        })->name('scribe.postman');

        Route::get("{$docsUrl}.openapi", function () {
            abort_unless(Storage::disk('local')->exists('scribe/openapi.yaml'), 404);

            return response()->file(Storage::disk('local')->path('scribe/openapi.yaml'));
        })->name('scribe.openapi');
    });
