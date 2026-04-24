<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto;

use App\Dto\User\EditUserDto;
use App\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EditUserDto::class)]
final class EditUserDtoTest extends TestCase
{
    public function testFromEntityMapsFields(): void
    {
        $user = $this->makeUser(id: 42, email: 'test@example.com', name: 'John');

        $dto = EditUserDto::fromEntity($user);

        self::assertSame(42, $dto->id);
        self::assertSame('test@example.com', $dto->email);
        self::assertSame('John', $dto->name);
    }

    public function testFromEntityFallsBackToEmptyStringForNullName(): void
    {
        $user = $this->makeUser(id: 1, email: 'test@example.com', name: null);

        $dto = EditUserDto::fromEntity($user);

        self::assertSame('', $dto->name);
    }

    public function testFromEntityThrowsWhenIdIsNull(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        $this->expectException(\LogicException::class);
        EditUserDto::fromEntity($user);
    }

    public function testFromEntityThrowsWhenEmailIsNull(): void
    {
        $user = $this->makeUser(id: 1, email: null, name: 'John');

        $this->expectException(\LogicException::class);
        EditUserDto::fromEntity($user);
    }

    private function makeUser(int $id, ?string $email, ?string $name): User
    {
        $user = new User();

        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, $id);

        if (null !== $email) {
            $user->setEmail($email);
        }
        $user->setName($name);

        return $user;
    }
}
