<?php

declare(strict_types=1);

namespace Tyloo\Atc\Tests\Fixtures\Kernel;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Default test kernel used across the functional suite. Bundles
 * FrameworkBundle + DoctrineBundle, and wires the fixture controllers
 * and services so the InteractsWith* traits can be exercised end-to-end.
 */
class TestKernel extends Kernel
{
    use MicroKernelTrait;

    #[Override]
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
        ];
    }

    #[Override]
    public function getProjectDir(): string
    {
        // Fixtures live one level above the kernels (../).
        return dirname(__DIR__);
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/tyloo-atc/' . $this->environment . '/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/tyloo-atc/' . $this->environment . '/log';
    }

    public function createSchema(): void
    {
        $em = $this->getContainer()->get('doctrine.orm.entity_manager');
        assert($em instanceof EntityManagerInterface, 'Doctrine EntityManager must be available in the test container.');

        $tool = new SchemaTool($em);
        $tool->dropDatabase();
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());
    }

    protected function configureContainer(ContainerConfigurator $c): void
    {
        $fixturesDir = dirname(__DIR__);
        $c->import($fixturesDir . '/config/packages/*.yaml');

        $services = $c->services();
        $services->defaults()
            ->autowire()
            ->autoconfigure()
            ->public();

        // Symfony 6.4 only auto-tags controllers extending AbstractController;
        // tag them explicitly so per-action argument autowiring works on 6.4 + 7 + 8.
        $services->load('Tyloo\\Atc\\Tests\\Fixtures\\Controller\\', $fixturesDir . '/Controller')
            ->tag('controller.service_arguments');
        $services->load('Tyloo\\Atc\\Tests\\Fixtures\\Service\\', $fixturesDir . '/Service');

        // Symfony 6.4 doesn't auto-alias all type-hints to service ids the way 7+ does.
        // Register the aliases the fixture controllers autowire on.
        $services->alias(CacheInterface::class, 'cache.app')->public();
        $services->alias(HttpClientInterface::class, 'http_client')->public();
        $services->alias(MailerInterface::class, 'mailer.mailer')->public();
        $services->alias(NotifierInterface::class, 'notifier')->public();
        $services->alias(MessageBusInterface::class, 'messenger.bus.default')->public();
        $services->alias(EntityManagerInterface::class, 'doctrine.orm.entity_manager')->public();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(dirname(__DIR__) . '/Controller/', 'attribute');
    }
}
