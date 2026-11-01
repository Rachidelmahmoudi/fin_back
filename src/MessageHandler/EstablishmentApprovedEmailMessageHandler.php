<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\User;
use App\Message\EstablishmentApprovedEmailMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class EstablishmentApprovedEmailMessageHandler
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

    public function __invoke(EstablishmentApprovedEmailMessage $message): void
    {
        $user = $this->em->getRepository(User::class)->find($message->userId);
        if ($user === null) {
            return;
        }

        $email = (new TemplatedEmail())
            ->to($user->getEmail())
            ->subject('Votre établissement est maintenant publié sur FinPharma')
            ->htmlTemplate('emails/establishment_approved.html.twig')
            ->context([
                'user' => $user,
                'establishmentType' => $message->establishmentType,
                'establishmentTypeLabel' => self::TYPE_LABELS[$message->establishmentType] ?? $message->establishmentType,
                'establishmentName' => $message->establishmentName,
            ]);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Échec envoi email établissement approuvé', ['user' => $user->getEmail(), 'exception' => $e->getMessage()]);
        }
    }
}
