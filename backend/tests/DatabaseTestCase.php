<?php

declare(strict_types=1);

namespace App\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Input\StringInput;

abstract class DatabaseTestCase extends WebTestCase
{
    protected static bool $isDbSetUp = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (!self::$isDbSetUp) {
            self::setUpDatabase();
            self::$isDbSetUp = true;
        }
    }

    protected static function setUpDatabase(): void
    {
        // Create a separate kernel instance for database setup
        $kernel = static::createKernel();
        $kernel->boot();

        $application = new Application($kernel);
        $application->setAutoExit(false);

        $connection = $kernel->getContainer()->get('doctrine')->getConnection();

        // DBAL cannot introspect expression indexes, so drop ours before schema:drop reads the schema.
        self::dropProductionOnlyIndexes($connection);

        // SQLite file is created automatically; just reset schema
        $application->run(new StringInput('doctrine:schema:drop --force --env=test'));
        $application->run(new StringInput('doctrine:schema:create --env=test'));

        self::createProductionOnlyIndexes($connection);

        // Load fixtures
        $application->run(new StringInput('doctrine:fixtures:load --no-interaction --env=test'));

        $kernel->shutdown();
    }

    /**
     * schema:create builds the test DB from the ORM mapping, so invariants added by raw SQL in
     * migrations are missing. Mirror them here. Source of truth: migrations/Version20260801160339.php.
     */
    private static function createProductionOnlyIndexes(Connection $connection): void
    {
        if (!$connection->getDatabasePlatform() instanceof SQLitePlatform) {
            return;
        }

        // At most one pending-or-accepted row per unordered user pair (Postgres uses LEAST/GREATEST).
        $connection->executeStatement(
            'CREATE UNIQUE INDEX uniq_friend_request_active_pair'
            .' ON friend_request (min(requester_id, addressee_id), max(requester_id, addressee_id))'
            ." WHERE status IN ('pending', 'accepted')"
        );
    }

    private static function dropProductionOnlyIndexes(Connection $connection): void
    {
        if (!$connection->getDatabasePlatform() instanceof SQLitePlatform) {
            return;
        }

        $connection->executeStatement('DROP INDEX IF EXISTS uniq_friend_request_active_pair');
    }
}
