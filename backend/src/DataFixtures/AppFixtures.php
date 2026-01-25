<?php

namespace App\DataFixtures;

use App\Entity\Enum\UserGroupRoleEnum;
use App\Entity\Group;
use App\Entity\User;
use App\Entity\UserHasGroup;
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
        $this->generateGroups($manager);
        $this->generateUsers($manager);

        $manager->flush();
        $this->generateUserHasGroups($manager);
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

    private function generateUsers(ObjectManager $manager): void
    {
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
    }

    private function generateGroups(ObjectManager $manager): void
    {
        $group1 = new Group();
        $group1->setName('group1');
        $group1->setDescription('First group');
        $manager->persist($group1);

        $group2 = new Group();
        $group2->setName('group2');
        $group2->setDescription('Second group');
        $manager->persist($group2);

        $group3 = new Group();
        $group3->setName('group3');
        $group3->setDescription('Third group with emoji 🧸');
        $manager->persist($group3);
    }

    private function generateUserHasGroups(ObjectManager $manager): void
    {
        $userHasGroup1 = new UserHasGroup();
        $userHasGroup1->setUser($manager->find(User::class, 1));
        $userHasGroup1->setGroup($manager->find(Group::class, 1));

        $userHasGroup2 = new UserHasGroup();
        $userHasGroup2->setUser($manager->find(User::class, 1));
        $userHasGroup2->setGroup($manager->find(Group::class, 2));
        $userHasGroup2->setRole(UserGroupRoleEnum::OWNER);

        $userHasGroup3 = new UserHasGroup();
        $userHasGroup3->setUser($manager->find(User::class, 2));
        $userHasGroup3->setGroup($manager->find(Group::class, 2));

        $userHasGroup4 = new UserHasGroup();
        $userHasGroup4->setUser($manager->find(User::class, 2));
        $userHasGroup4->setGroup($manager->find(Group::class, 3));

        $manager->persist($userHasGroup1);
        $manager->persist($userHasGroup2);
        $manager->persist($userHasGroup3);
        $manager->persist($userHasGroup4);
    }
}
