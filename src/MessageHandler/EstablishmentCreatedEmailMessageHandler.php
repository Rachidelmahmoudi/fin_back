<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\User;
use App\Message\EstablishmentCreatedEmailMessage;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Doctrine\ORM\EntityManagerInterface;

#[AsMessageHandler]
final class EstablishmentCreatedEmailMessageHandler
{
    private const TYPE_LABELS = [
        'pharmacy' => 'pharmacie',
        'doctor' => 'cabinet médical',
        'laboratory' => 'laboratoire',
    ];

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(EstablishmentCreatedEmailMessage $message): void
    {
        $user = $this->em->getRepository(User::class)->find($message->userId);
        if ($user === null) {
            return;
        }

        $email = (new TemplatedEmail())
            ->to($user->getEmail())
            ->subject('Votre établissement a été soumis à FinPharma')
            ->htmlTemplate('emails/establishment_created.html.twig')
            ->context([
                'user' => $user,
                'establishmentTypeLabel' => self::TYPE_LABELS[$message->establishmentType] ?? $message->establishmentType,
                'establishmentName' => $message->establishmentName,
            ]);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Échec envoi email établissement créé', ['user' => $user->getEmail(), 'exception' => $e->getMessage()]);
        }
    }
}
