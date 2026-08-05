<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Livewire\Mechanisms\HandleRequests\EndpointResolver;
use Tests\TestCase;

final class LivewireEndpointTest extends TestCase
{
    public function test_application_uses_a_stable_livewire_update_endpoint(): void
    {
        $this->assertSame('/livewire/update', app('livewire')->getUpdateUri());
    }

    public function test_hashed_endpoint_remains_as_a_compatible_alias_for_open_tabs(): void
    {
        $legacyPath = ltrim(EndpointResolver::updatePath(), '/');
        $legacyRoute = collect(Route::getRoutes()->getRoutes())
            ->first(static fn (RoutingRoute $route): bool => $route->uri() === $legacyPath
                && in_array('POST', $route->methods(), true));

        $this->assertNotNull($legacyRoute);
        $this->assertSame('legacy-livewire-endpoint', $legacyRoute->getName());
    }

    public function test_malformed_livewire_request_returns_json_instead_of_the_html_404_page(): void
    {
        $this->withHeader('X-Livewire', '1')
            ->postJson('/livewire/update', ['components' => []])
            ->assertNotFound()
            ->assertJson([
                'message' => 'No fue posible sincronizar la pantalla. Recarga la página.',
                'code' => 'livewire_endpoint_not_found',
            ])
            ->assertDontSee('Página en construcción');
    }
}
