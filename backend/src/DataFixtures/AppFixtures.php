<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $hasher,
        private Connection $connection,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $this->resetAutoIncrements();

        // Create admin user
        $adminUser = new User();
        $adminUser->setEmail('admin@example.com');
        $adminUser->setPassword($this->hasher->hashPassword($adminUser, 'password'));
        $adminUser->setRoles(['ROLE_ADMIN']);

        // Create user1
        $user1 = new User();
        $user1->setEmail('user1@example.com');
        $user1->setPassword($this->hasher->hashPassword($user1, 'password'));
        $user1->setRoles(['ROLE_USER']);

        $manager->persist($adminUser);
        $manager->persist($user1);
        $manager->flush();
    }

    // TODO temp solution until i find out why purge doesnt work
    private function resetAutoIncrements(): void
    {
        $tables = $this->connection->createSchemaManager()->listTableNames();

        foreach ($tables as $table) {
            $cleanTable = trim($table, '"');
            $this->connection->executeStatement(
                "ALTER SEQUENCE IF EXISTS \"{$cleanTable}_id_seq\" RESTART WITH 1"
            );
        }
    }
}
