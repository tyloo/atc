<?php

declare(strict_types=1);

namespace Tyloo\Atc\Trait;

use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use PHPUnit\Framework\Assert;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Doctrine ORM assertions for verifying the persistence side of API behaviour.
 *
 * Helpers operate against the test container's
 * {@see EntityManagerInterface}; consumers must include `doctrine/orm` in
 * their test dependencies. All criteria are passed straight through to
 * {@see \Doctrine\ORM\EntityRepository::findOneBy()}.
 *
 * @phpstan-require-extends WebTestCase
 */
trait InteractsWithDatabase
{
    /**
     * Assert that at least one entity matching the given criteria exists.
     *
     * @param class-string         $entityClass Doctrine entity class name.
     * @param array<string, mixed> $criteria    Field => value criteria forwarded to findOneBy().
     *
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     *
     * @example
     * ```
     * $this->post('/api/users', ['email' => 'alice@example.com']);
     * $this->assertDatabaseHas(User::class, ['email' => 'alice@example.com']);
     * ```
     */
    public function assertDatabaseHas(string $entityClass, array $criteria): void
    {
        $entity = $this->doctrineEntityManager()->getRepository($entityClass)->findOneBy($criteria);
        Assert::assertNotNull($entity, sprintf('Expected to find %s matching %s.', $entityClass, json_encode($criteria)));
    }

    /**
     * Assert that no entity matching the given criteria exists.
     *
     * @param class-string         $entityClass Doctrine entity class name.
     * @param array<string, mixed> $criteria    Field => value criteria forwarded to findOneBy().
     *
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     *
     * @example
     * ```
     * $this->delete('/api/users/42');
     * $this->assertDatabaseMissing(User::class, ['id' => 42]);
     * ```
     */
    public function assertDatabaseMissing(string $entityClass, array $criteria): void
    {
        $entity = $this->doctrineEntityManager()->getRepository($entityClass)->findOneBy($criteria);
        Assert::assertNull($entity, sprintf('Expected NOT to find %s matching %s.', $entityClass, json_encode($criteria)));
    }

    /**
     * Assert the total row count for the given entity matches `$count`.
     *
     * Runs a `SELECT COUNT(e) FROM <Entity> e` query against the active EntityManager.
     *
     * @param class-string $entityClass Doctrine entity class name.
     * @param int          $count       Expected number of rows.
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\NoResultException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     *
     * @example
     * ```
     * $this->post('/api/users', ['email' => 'a@example.com']);
     * $this->post('/api/users', ['email' => 'b@example.com']);
     * $this->assertDatabaseCount(User::class, 2);
     * ```
     */
    public function assertDatabaseCount(string $entityClass, int $count): void
    {
        $em = $this->doctrineEntityManager();
        $qb = $em->createQueryBuilder()->select('COUNT(e)')->from($entityClass, 'e');
        $actual = (int) $qb->getQuery()->getSingleScalarResult();

        Assert::assertSame($count, $actual, sprintf('Expected %d rows of %s, got %d.', $count, $entityClass, $actual));
    }

    /**
     * Resolve the active Doctrine {@see EntityManagerInterface} from the test container.
     *
     * @throws LogicException If `doctrine/orm` is not installed/registered in the test kernel.
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     */
    private function doctrineEntityManager(): EntityManagerInterface
    {
        $container = static::getContainer();

        // @codeCoverageIgnoreStart
        // Defensive: doctrine/orm is a dev dependency for this package's tests
        // and a hard requirement when consumers use this trait.
        if (!$container->has(EntityManagerInterface::class)) {
            throw new LogicException(sprintf('%s requires doctrine/orm.', __TRAIT__));
        }
        // @codeCoverageIgnoreEnd

        $em = $container->get(EntityManagerInterface::class);
        assert($em instanceof EntityManagerInterface, 'EntityManagerInterface service must be of expected type.');

        return $em;
    }
}
