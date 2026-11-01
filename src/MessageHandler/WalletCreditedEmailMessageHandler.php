<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\User;
use App\Message\WalletCreditedEmailMessage;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Doctrine\ORM\EntityManagerInterface;

#[AsMessageHandler]
final class WalletCreditedEmailMessageHandler
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(WalletCreditedEmailMessage $message): void
    {
        $user = $this->em->getRepository(User::class)->find($message->userId);
        if ($user === null) {
            return;
        }

        $email = (new TemplatedEmail())
            ->to($user->getEmail())
            ->subject('Votre portefeuille FinPharma a été crédité')
            ->htmlTemplate('emails/wallet_credited.html.twig')
            ->context([
                'user' => $user,
                'creditedAmount' => $message->creditedAmount,
                'newBalance' => $message->newBalance,
            ]);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Échec envoi email recharge confirmée', ['user' => $user->getEmail(), 'exception' => $e->getMessage()]);
        }
    }
}
