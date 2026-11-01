<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\PrescriptionRequest;
use App\Entity\PrescriptionRequestStatus;
use App\Entity\User;
use App\Security\Voter\PrescriptionRequestVoter;
use App\Service\Prescription\PrescriptionAlreadyAcceptedException;
use App\Service\Prescription\PrescriptionMatchingService;
use App\Service\Wallet\WalletService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * §26 du cahier des charges — endpoints du flux pharmacien <-> déchiffreur.
 * Controller custom (pas un ApiResource CRUD standard) car le cycle de vie
 * de PrescriptionRequest est piloté par des transitions métier explicites
 * (publish/accept/submit), pas par un simple PATCH générique — exactement
 * le type de cas où API Platform recommande des opérations personnalisées.
 */
#[IsGranted('ROLE_USER')]
final class PrescriptionController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly WalletService $walletService,
        private readonly PrescriptionMatchingService $matchingService,
    ) {
    }

    /**
     * Publication (§26, étapes PHARMACIEN 1-4).
     *
     * §Correctif — essai gratuit : un pharmacien avec encore des essais
     * disponibles (User.trialDecodingsRemaining > 0, par défaut 2 à la
     * création de n'importe quel compte) peut publier SANS vérifier le
     * solde — la demande est marquée isTrialRequest=true, consommée
     * seulement à la VALIDATION du résultat (voir
     * PrescriptionMatchingService::submitResult()), pas à la simple
     * publication : une demande jamais acceptée ou expirée ne doit pas
     * "gaspiller" un essai gratuit, même logique que le wallet réel (débit
     * à la validation, pas à la publication — §Addendum 2).
     *
     * Hors essai : vérifie que le solde disponible >= 5 DH (§27) — aucun
     * débit/hold à ce stade non plus (décision confirmée).
     */
    #[Route('/api/v1/prescriptions', name: 'api_prescriptions_publish', methods: ['POST'])]
    #[IsGranted('ROLE_PHARMACIST')]
    public function publish(Request $request, #[CurrentUser] User $pharmacist): JsonResponse
    {
        $payload = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $imageUrl = (string) ($payload['imageUrl'] ?? '');
        $estimatedItemsCount = (int) ($payload['estimatedItemsCount'] ?? 0);
        $note = isset($payload['note']) ? trim((string) $payload['note']) : null;

        if ($imageUrl === '' || $estimatedItemsCount < 1) {
            return new JsonResponse(['error' => 'imageUrl et estimatedItemsCount (>=1) sont requis.'], 422);
        }

        $isTrial = $pharmacist->hasTrialDecodingsRemaining();

        if (!$isTrial) {
            $wallet = $this->walletService->getOrCreateWallet($pharmacist);
            if (!$this->walletService->hasMinimumBalance($wallet, PrescriptionRequest::MIN_BALANCE_TO_PUBLISH)) {
                return new JsonResponse([
                    'error' => sprintf(
                        'Solde insuffisant : minimum %s %s requis pour publier une demande.',
                        PrescriptionRequest::MIN_BALANCE_TO_PUBLISH,
                        $wallet->getCurrency(),
                    ),
                ], 422);
            }
        }

        $prescriptionRequest = new PrescriptionRequest($pharmacist, $imageUrl, $estimatedItemsCount);
        $prescriptionRequest->setStatus(PrescriptionRequestStatus::PUBLISHED);
        if ($note !== null && $note !== '') {
            $prescriptionRequest->setNote($note);
        }
        if ($isTrial) {
            $prescriptionRequest->markAsTrialRequest();
        }

        $this->em->persist($prescriptionRequest);
        $this->em->flush();

        return new JsonResponse([
            'id' => $prescriptionRequest->getId()->toRfc4122(),
            'isTrialRequest' => $isTrial,
            'trialDecodingsRemaining' => $pharmacist->getTrialDecodingsRemaining(),
        ], 201);
    }

    /** Permet au mobile d'afficher "Il vous reste X essais gratuits" avant même de publier. */
    #[Route('/api/v1/me/trial-decodings', name: 'api_me_trial_decodings', methods: ['GET'])]
    public function trialDecodingsRemaining(#[CurrentUser] User $user): JsonResponse
    {
        return new JsonResponse(['remaining' => $user->getTrialDecodingsRemaining()]);
    }

    /** Liste des demandes disponibles pour un déchiffreur (§26, DÉCHIFFREUR étape 2). */
    #[Route('/api/v1/prescriptions/available', name: 'api_prescriptions_available', methods: ['GET'])]
    #[IsGranted('ROLE_DECODER')]
    public function available(): JsonResponse
    {
        $requests = $this->em->getRepository(PrescriptionRequest::class)->findBy(
            ['status' => PrescriptionRequestStatus::PUBLISHED],
            ['createdAt' => 'ASC'],
        );

        return new JsonResponse(array_map(
            static fn (PrescriptionRequest $r) => [
                'id' => $r->getId()->toRfc4122(),
                'imageUrl' => null, // volontairement omis en liste — l'image ne se charge qu'après acceptation
                'estimatedItemsCount' => $r->getEstimatedItemsCount(),
                'estimatedCost' => $r->getEstimatedCost(),
                'note' => $r->getNote(),
                'createdAt' => $r->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ],
            $requests,
        ));
    }

    /**
     * Acceptation atomique (§26, DÉCHIFFREUR étape 3-4). Voir
     * PrescriptionMatchingService::accept() pour la garantie d'unicité —
     * et pour la gestion du HOLD, désormais sautée pour une demande
     * d'essai (voir le service).
     */
    #[Route('/api/v1/prescriptions/{id}/accept', name: 'api_prescriptions_accept', methods: ['POST'])]
    #[IsGranted('ROLE_DECODER')]
    public function accept(string $id, #[CurrentUser] User $decoder): JsonResponse
    {
        $prescriptionRequest = $this->em->getRepository(PrescriptionRequest::class)->find($id)
            ?? throw $this->createNotFoundException();

        try {
            $this->matchingService->accept($prescriptionRequest, $decoder);
        } catch (PrescriptionAlreadyAcceptedException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 409);
        } catch (\DomainException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        return new JsonResponse([
            'id' => $prescriptionRequest->getId()->toRfc4122(),
            'imageUrl' => $prescriptionRequest->getImageUrl(),
            'note' => $prescriptionRequest->getNote(),
            'expiresAt' => $prescriptionRequest->getExpiresAt()?->format(\DateTimeInterface::ATOM),
            'expiresInSeconds' => PrescriptionRequest::ACCEPTANCE_WINDOW_SECONDS,
        ]);
    }

    /**
     * Soumission du résultat (§26, étapes 6-8). `items` = liste de
     * {medicineId, medicineName, quantity} issus de l'autocomplétion
     * médicament EN PRIORITÉ — `medicineId` toléré à `null` en repli
     * manuel (médicament introuvable dans notre base).
     */
    #[Route('/api/v1/prescriptions/{id}/submit', name: 'api_prescriptions_submit', methods: ['POST'])]
    #[IsGranted('ROLE_DECODER')]
    public function submit(string $id, Request $request, #[CurrentUser] User $decoder): JsonResponse
    {
        $prescriptionRequest = $this->em->getRepository(PrescriptionRequest::class)->find($id)
            ?? throw $this->createNotFoundException();

        $this->denyAccessUnlessGranted(PrescriptionRequestVoter::SUBMIT_RESULT, $prescriptionRequest);

        $payload = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $items = $payload['items'] ?? [];

        if (!\is_array($items) || \count($items) === 0) {
            return new JsonResponse(['error' => 'items ne peut pas être vide.'], 422);
        }

        foreach ($items as $item) {
            if (!\is_array($item) || empty(trim((string) ($item['medicineName'] ?? '')))) {
                return new JsonResponse(['error' => 'Chaque médicament doit avoir au moins un nom.'], 422);
            }
        }

        try {
            $result = $this->matchingService->submitResult($prescriptionRequest, $decoder, $items);
        } catch (\DomainException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        return new JsonResponse([
            'resultId' => $result->getId()->toRfc4122(),
            'finalCost' => $result->getFinalCost(),
            'pointsAwarded' => $result->getPointsAwarded(),
        ]);
    }
}
