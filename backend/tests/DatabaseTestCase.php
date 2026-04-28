<?php

declare(strict_types=1);

namespace App\Tests;

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

        // SQLite file is created automatically; just reset schema
        $application->run(new StringInput('doctrine:schema:drop --force --env=test'));
        $application->run(new StringInput('doctrine:schema:create --env=test'));

        // Load fixtures
        $application->run(new StringInput('doctrine:fixtures:load --no-interaction --env=test'));

        $kernel->shutdown();
    }
}
