<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\User;
use App\Message\DecoderRegisteredEmailMessage;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Doctrine\ORM\EntityManagerInterface;

/** Prêt à l'emploi — voir DecoderRegisteredEmailMessage.php pour ce qui manque encore pour le déclencher réellement. */
#[AsMessageHandler]
final class DecoderRegisteredEmailMessageHandler
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(DecoderRegisteredEmailMessage $message): void
    {
        $user = $this->em->getRepository(User::class)->find($message->userId);
        if ($user === null) {
            return;
        }

        $email = (new TemplatedEmail())
            ->to($user->getEmail())
            ->subject('Votre profil déchiffreur FinPharma')
            ->htmlTemplate('emails/decoder_registered.html.twig')
            ->context(['user' => $user]);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Échec envoi email inscription déchiffreur', ['user' => $user->getEmail(), 'exception' => $e->getMessage()]);
        }
    }
}
