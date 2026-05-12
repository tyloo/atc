<?php

declare(strict_types=1);

namespace Tyloo\Atc\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Fixture entity used by InteractsWithDatabase tests to exercise
 * assertDatabaseHas/assertDatabaseMissing/assertDatabaseCount against
 * a Doctrine schema.
 */
#[ORM\Entity]
#[ORM\Table(name: 'item')]
class Item
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public ?int $id = null;

    public function __construct(
        #[ORM\Column(type: 'string')]
        public string $name,
    ) {}
}
