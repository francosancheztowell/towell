<?php

declare(strict_types=1);

namespace Tests\Unit\Crudo;

use App\Services\Crudo\CrudoAccess;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class CrudoAccessTest extends TestCase
{
    /**
     * Sin CRUDO_PERMISSION_MODULE en config, authorize() debe seguir exigiendo el
     * permiso 'acceso' sobre el módulo de fallback ('Andon'), no abrir el tablero a
     * cualquier autenticado. Antes del fix, un config vacío hacía que authorize()
     * retornara sin validar nada.
     */
    public function test_authorize_still_requires_the_permission_when_config_is_empty(): void
    {
        config()->set('crudo.permission_module', '');

        try {
            (new CrudoAccess)->authorize();
            $this->fail('authorize() debió abortar con 403 sin usuario autenticado.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_can_access_is_false_without_an_authenticated_user(): void
    {
        config()->set('crudo.permission_module', '');

        $this->assertFalse((new CrudoAccess)->canAccess());
    }
}
