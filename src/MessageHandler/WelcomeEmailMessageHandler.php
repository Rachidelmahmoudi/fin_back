<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\User;
use App\Message\WelcomeEmailMessage;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Doctrine\ORM\EntityManagerInterface;

/**
 * §Emails transactionnels. L'échec d'envoi n'affecte JAMAIS l'inscription
 * elle-même (déjà commitée en base avant la mise en file de ce message) —
 * une erreur ici est journalisée, pas remontée à l'utilisateur.
 */
#[AsMessageHandler]
final class WelcomeEmailMessageHandler
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(WelcomeEmailMessage $message): void
    {
        $user = $this->em->getRepository(User::class)->find($message->userId);
        if ($user === null) {
            return; // compte supprimé entre-temps : rien à envoyer, pas une erreur
        }

        $email = (new TemplatedEmail())
            ->to($user->getEmail())
            ->subject('Bienvenue sur FinPharma')
            ->htmlTemplate('emails/welcome.html.twig')
            ->context(['user' => $user]);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Échec envoi email de bienvenue', ['user' => $user->getEmail(), 'exception' => $e->getMessage()]);
        }
    }
}
