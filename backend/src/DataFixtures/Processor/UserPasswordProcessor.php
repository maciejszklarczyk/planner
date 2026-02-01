<?php

namespace App\DataFixtures\Processor;

use App\Entity\User;
use Fidry\AliceDataFixtures\ProcessorInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserPasswordProcessor implements ProcessorInterface
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function preProcess(string $id, object $object): void
    {
        if (!$object instanceof User) {
            return;
        }

        $plainPassword = $object->getPassword();
        if ($plainPassword) {
            $hashedPassword = $this->passwordHasher->hashPassword($object, $plainPassword);
            $object->setPassword($hashedPassword);
        }
    }

    public function postProcess(string $id, object $object): void
    {
        // Nothing to do
    }
}
