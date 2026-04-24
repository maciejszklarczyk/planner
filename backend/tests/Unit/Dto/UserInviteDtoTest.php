<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto;

use App\Dto\User\UserInviteDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

#[CoversClass(UserInviteDto::class)]
final class UserInviteDtoTest extends TestCase
{
    public function testValidEmailPassesValidation(): void
    {
        $dto = new UserInviteDto(email: 'user@example.com');
        $violations = $this->validate($dto);

        self::assertCount(0, $violations);
        self::assertSame('user@example.com', $dto->email);
    }

    #[DataProvider('invalidEmailProvider')]
    public function testInvalidEmailFailsValidation(string $email): void
    {
        $violations = $this->validate(new UserInviteDto(email: $email));

        self::assertGreaterThan(0, count($violations));
    }

    public static function invalidEmailProvider(): array
    {
        return [
            'empty' => [''],
            'no at sign' => ['notanemail'],
            'no domain' => ['user@'],
        ];
    }

    private function validate(object $dto): \Symfony\Component\Validator\ConstraintViolationListInterface
    {
        return Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator()
            ->validate($dto);
    }
}
