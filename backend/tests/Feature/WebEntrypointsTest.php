<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class WebEntrypointsTest extends TestCase
{
    public function test_root_redirects_to_frontend_url_when_configured(): void
    {
        config()->set('app.frontend_url', 'http://frontend.test');

        $this->get('/')
            ->assertRedirect('http://frontend.test');
    }

    public function test_docs_route_serves_a_configured_generated_view(): void
    {
        $tempViewPath = sys_get_temp_dir().'/ods-scribe-views-'.uniqid();
        File::ensureDirectoryExists($tempViewPath);
        File::put($tempViewPath.'/custom-docs.blade.php', '<h1>Docs prontas</h1>');

        View::addLocation($tempViewPath);
        config()->set('scribe.laravel.docs_view', 'custom-docs');

        try {
            $this->get('/docs')
                ->assertOk()
                ->assertSee('Docs prontas', false);
        } finally {
            File::delete($tempViewPath.'/custom-docs.blade.php');
            File::deleteDirectory($tempViewPath);
        }
    }

    public function test_docs_route_fails_gracefully_when_generated_view_is_missing(): void
    {
        config()->set('scribe.laravel.docs_view', 'missing-scribe-docs-view');

        $this->get('/docs')
            ->assertStatus(503)
            ->assertSee('Documentação da API ainda não foi gerada.', false)
            ->assertSee('php artisan scribe:generate', false);
    }
}
