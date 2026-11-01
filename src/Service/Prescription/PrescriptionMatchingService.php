<?php

declare(strict_types=1);

namespace App\Service\Prescription;

use App\Entity\PrescriptionRequest;
use App\Entity\PrescriptionRequestStatus;
use App\Entity\PrescriptionResult;
use App\Entity\User;
use App\Service\Wallet\WalletService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * §26 du cahier des charges — cœur du problème de concurrence : deux
 * déchiffreurs ne doivent jamais pouvoir accepter la même demande.
 *
 * La garantie ne vient PAS d'un verrou Doctrine (ORM\LockMode sur une
 * entité déjà chargée en mémoire est trop tard : la race a déjà pu se
 * produire entre le SELECT et l'UPDATE de deux requêtes concurrentes).
 * Elle vient d'un UPDATE conditionnel exécuté directement en DBAL.
 *
 * §Correctif — essai gratuit : une PrescriptionRequest marquée
 * isTrialRequest=true ne déclenche NI hold (accept) NI débit (submitResult)
 * sur le wallet du pharmacien — un pharmacien en essai peut avoir un solde
 * à 0 DH, et un HOLD dessus violerait la contrainte "balance >= 0" en
 * base. Le "coût" d'une demande d'essai se traduit uniquement par la
 * décrémentation de User.trialDecodingsRemaining, à la VALIDATION (pas à
 * la publication ni à l'acceptation) — même philosophie que le wallet réel
 * (§Addendum 2 : débit au moment où le travail est confirmé livré, pas avant).
 */
final class PrescriptionMatchingService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Connection $connection,
        private readonly WalletService $walletService,
    ) {
    }

    /**
     * @throws PrescriptionAlreadyAcceptedException si un autre déchiffreur a été plus rapide
     * @throws \DomainException si le pharmacien n'a plus le solde minimum requis
     */
    public function accept(PrescriptionRequest $request, User $decoder): PrescriptionRequest
    {
        $now = new \DateTimeImmutable();
        $expiresAt = $now->modify(sprintf('+%d seconds', PrescriptionRequest::ACCEPTANCE_WINDOW_SECONDS));

        $affectedRows = $this->connection->executeStatement(
            <<<'SQL'
                UPDATE prescription_request
                SET status = 'ACCEPTED', decoder_id = :decoderId, accepted_at = :acceptedAt, expires_at = :expiresAt
                WHERE id = :id AND status = 'PUBLISHED'
                SQL,
            [
                'decoderId' => $decoder->getId()->toRfc4122(),
                'acceptedAt' => $now->format('Y-m-d H:i:s'),
                'expiresAt' => $expiresAt->format('Y-m-d H:i:s'),
                'id' => $request->getId()->toRfc4122(),
            ],
        );

        if ($affectedRows === 0) {
            throw new PrescriptionAlreadyAcceptedException(
                'Cette demande a déjà été acceptée par un autre déchiffreur ou n\'est plus disponible.',
            );
        }

        // Réserve (HOLD) le montant estimé sur le wallet du pharmacien —
        // SAUF pour une demande d'essai, qui ne touche jamais au wallet réel.
        if (!$request->isTrialRequest()) {
            $pharmacistWallet = $this->walletService->getOrCreateWallet($request->getPharmacist());
            $this->walletService->hold(
                $pharmacistWallet,
                $request->getEstimatedCost(),
                'PrescriptionRequest',
                $request->getId()->toRfc4122(),
            );
        }

        // Recharge l'entité depuis la DB pour refléter l'UPDATE atomique
        // ci-dessus dans l'objet retourné à l'appelant.
        $this->em->refresh($request);

        return $request;
    }

    /**
     * Soumission du résultat par le déchiffreur (§26 étapes 6-8).
     * Débite le coût RÉEL (pas l'estimation) et crédite les points — sauf
     * pour une demande d'essai, qui décrémente le compteur d'essais du
     * pharmacien à la place, sans jamais toucher au wallet.
     *
     * @param array<int, array{medicineId: string|null, medicineName: string, quantity: int|null}> $items
     */
    public function submitResult(PrescriptionRequest $request, User $decoder, array $items): PrescriptionResult
    {
        if ($request->getStatus() !== PrescriptionRequestStatus::ACCEPTED
            && $request->getStatus() !== PrescriptionRequestStatus::IN_PROGRESS) {
            throw new \DomainException('Cette demande n\'est pas dans un état permettant la soumission d\'un résultat.');
        }

        if ($request->getDecoder()?->getId() != $decoder->getId()) {
            throw new \DomainException('Seul le déchiffreur ayant accepté cette demande peut soumettre un résultat.');
        }

        $now = new \DateTimeImmutable();
        if ($request->isExpired($now)) {
            throw new \DomainException('Le délai d\'acceptation est dépassé — la demande a expiré.');
        }

        $result = new PrescriptionResult($request, $items);

        if ($request->isTrialRequest()) {
            $pharmacist = $request->getPharmacist();
            $pharmacist->consumeTrialDecoding();
            $this->em->persist($pharmacist);
        } else {
            // Débit réel (coût final, pas l'estimation) + libération
            // intégrale du hold — flux confirmé Addendum 2.
            $pharmacistWallet = $this->walletService->getOrCreateWallet($request->getPharmacist());
            $this->walletService->debitFromResult(
                $pharmacistWallet,
                $request->getEstimatedCost(),
                $result->getFinalCost(),
                $request->getId()->toRfc4122(),
            );
        }

        $decoder->addDecoderPoints($result->getPointsAwarded());
        $request->markCompleted($now);

        $this->em->persist($result);
        $this->em->persist($decoder);
        $this->em->persist($request);
        $this->em->flush();

        return $result;
    }

    /**
     * À exécuter par un job planifié (cron/Messenger, toutes les minutes) :
     * repasse en EXPIRED toute demande ACCEPTED dont expiresAt est dépassé
     * sans validation, et libère le hold correspondant — sauf pour une
     * demande d'essai, qui n'a jamais eu de hold à libérer.
     */
    public function expireOverdueRequests(): int
    {
        $now = new \DateTimeImmutable();

        $overdue = $this->em->getRepository(PrescriptionRequest::class)->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->andWhere('r.expiresAt < :now')
            ->setParameter('status', PrescriptionRequestStatus::ACCEPTED)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        foreach ($overdue as $request) {
            /** @var PrescriptionRequest $request */
            if (!$request->isTrialRequest()) {
                $wallet = $this->walletService->getOrCreateWallet($request->getPharmacist());
                $this->walletService->release(
                    $wallet,
                    $request->getEstimatedCost(),
                    'PrescriptionRequest',
                    $request->getId()->toRfc4122(),
                );
            }
            $request->setStatus(PrescriptionRequestStatus::EXPIRED);
            $this->em->persist($request);
        }

        $this->em->flush();

        return \count($overdue);
    }
}
