<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ObjectManager;
use Fidry\AliceDataFixtures\LoaderInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private Connection $connection,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire(service: 'hautelook_alice.data_fixtures.append_loader')]
        private LoaderInterface $loader,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $this->resetAutoIncrements();

        $this->loader->load([
            __DIR__.'/../../fixtures/users.yaml',
            __DIR__.'/../../fixtures/groups.yaml',
            __DIR__.'/../../fixtures/user_has_groups.yaml',
        ]);
    }

    // TODO temp solution until i find out why purge doesnt work
    private function resetAutoIncrements(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        // Only reset for PostgreSQL (not needed for SQLite in tests)
        if (!$platform instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform) {
            return;
        }

        $tables = $this->connection->createSchemaManager()->listTableNames();

        foreach ($tables as $table) {
            $cleanTable = trim($table, '"');
            $this->connection->executeStatement(
                "ALTER SEQUENCE IF EXISTS \"{$cleanTable}_id_seq\" RESTART WITH 1"
            );
        }
    }
}
