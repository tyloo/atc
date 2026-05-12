<?php

declare(strict_types=1);

namespace Tyloo\Atc\Tests\Fixtures\Kernel;

use Override;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Tyloo\Atc\Tests\Fixtures\Controller\PingController;

/**
 * Minimal kernel without DoctrineBundle, used to exercise the trait
 * default-resolution branches in isolation.
 */
final class BareKernel extends Kernel
{
    use MicroKernelTrait;

    #[Override]
    public function registerBundles(): iterable
    {
        return [new FrameworkBundle()];
    }

    #[Override]
    public function getProjectDir(): string
    {
        return dirname(__DIR__);
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/tyloo-atc-bare/' . $this->environment . '/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/tyloo-atc-bare/' . $this->environment . '/log';
    }

    protected function configureContainer(ContainerConfigurator $c): void
    {
        $c->extension('framework', [
            // @mago-expect lint:no-literal-password
            'secret' => 'test-secret',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'router' => ['utf8' => true],
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
        ]);

        // Tiny inline ping endpoint so the kernel has something to route.
        $services = $c->services();
        $services->defaults()->autowire()->autoconfigure()->public();
        $services->set(PingController::class)->public();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('bare_ping', '/ping')->controller([PingController::class, '__invoke']);
    }
}
