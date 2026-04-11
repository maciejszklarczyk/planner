<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class InvitationMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire(env: 'FRONTEND_URL')]
        private readonly string $frontendUrl,
        #[Autowire(env: 'MAILER_FROM')]
        private readonly string $mailerFrom,
    ) {
    }

    public function sendInvitation(string $to, string $token): void
    {
        $setPasswordUrl = $this->frontendUrl.'/set-password?token='.$token;

        $email = (new Email())
            ->from($this->mailerFrom)
            ->to($to)
            ->subject('Zaproszenie do Planner')
            ->html("<p>Zostałeś zaproszony do aplikacji Planner.</p><p><a href='$setPasswordUrl'>Kliknij tutaj</a>, aby ustawić hasło i aktywować konto.</p><p>Link jest ważny przez 24 godziny.</p>");

        $this->mailer->send($email);
    }
}
