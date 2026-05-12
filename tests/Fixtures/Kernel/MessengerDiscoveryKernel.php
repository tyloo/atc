<?php

declare(strict_types=1);

namespace Tyloo\Atc\Tests\Fixtures\Kernel;

use Override;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Standalone kernel exercising {@see InteractsWithMessenger::resolveMessengerTransports()}'s
 * auto-discovery branch — walks getServiceIds()/getRemovedIds() to find transport IDs.
 */
final class MessengerDiscoveryKernel extends Kernel
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
        return sys_get_temp_dir() . '/tyloo-atc-messenger-discovery/' . $this->environment . '/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/tyloo-atc-messenger-discovery/' . $this->environment . '/log';
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
            'messenger' => [
                'transports' => [
                    'async' => 'in-memory://',
                ],
            ],
        ]);
    }

    protected function configureRoutes(RoutingConfigurator $_routes): void
    {
        // No routes needed.
    }
}
