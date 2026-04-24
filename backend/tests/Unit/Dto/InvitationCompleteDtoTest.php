<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto;

use App\Dto\User\InvitationCompleteDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

#[CoversClass(InvitationCompleteDto::class)]
final class InvitationCompleteDtoTest extends TestCase
{
    public function testValidDataPassesValidation(): void
    {
        $dto = new InvitationCompleteDto(token: 'abc123', password: 'secret123');
        $violations = $this->validate($dto);

        self::assertCount(0, $violations);
        self::assertSame('abc123', $dto->token);
        self::assertSame('secret123', $dto->password);
    }

    #[DataProvider('invalidDataProvider')]
    public function testInvalidDataFailsValidation(string $token, string $password): void
    {
        $violations = $this->validate(new InvitationCompleteDto(token: $token, password: $password));

        self::assertGreaterThan(0, count($violations));
    }

    public static function invalidDataProvider(): array
    {
        return [
            'empty token' => ['', 'secret123'],
            'empty password' => ['abc123', ''],
            'password too short' => ['abc123', 'short'],
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
