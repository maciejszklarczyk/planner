<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\InvitationMailer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[CoversClass(InvitationMailer::class)]
final class InvitationMailerTest extends TestCase
{
    private MailerInterface&MockObject $mailer;
    private InvitationMailer $invitationMailer;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->invitationMailer = new InvitationMailer(
            mailer: $this->mailer,
            frontendUrl: 'https://app.example.com',
            mailerFrom: 'no-reply@example.com',
        );
    }

    public function testSendInvitationDispatchesEmail(): void
    {
        $this->mailer->expects(self::once())->method('send');

        $this->invitationMailer->sendInvitation('user@example.com', 'abc123token');
    }

    public function testSendInvitationSetsCorrectRecipientAndSender(): void
    {
        $this->mailer
            ->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email): bool {
                self::assertSame('no-reply@example.com', $email->getFrom()[0]->getAddress());
                self::assertSame('user@example.com', $email->getTo()[0]->getAddress());

                return true;
            }));

        $this->invitationMailer->sendInvitation('user@example.com', 'abc123token');
    }

    public function testSendInvitationBodyContainsSetPasswordUrl(): void
    {
        $this->mailer
            ->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email): bool {
                self::assertStringContainsString(
                    'https://app.example.com/set-password?token=abc123token',
                    $email->getHtmlBody()
                );

                return true;
            }));

        $this->invitationMailer->sendInvitation('user@example.com', 'abc123token');
    }

    public function testSendInvitationSubject(): void
    {
        $this->mailer
            ->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email): bool {
                self::assertSame('Zaproszenie do Planner', $email->getSubject());

                return true;
            }));

        $this->invitationMailer->sendInvitation('user@example.com', 'abc123token');
    }
}
