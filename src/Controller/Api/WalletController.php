<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Entity\WalletRechargeRequest;
use App\Entity\WalletRechargeMethod;
use App\Service\Wallet\WalletService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * §27 du cahier des charges. Le recharge réelle (paiement) n'est PAS
 * implémentée ici (§27 : "Ne pas implémenter un vrai système de paiement
 * sans analyser d'abord les possibilités et contraintes du projet") —
 * `credit()` ci-dessous est le point d'intégration où brancher un futur
 * provider de paiement marocain (CMI, Payzone, etc.), actuellement appelé
 * uniquement par un flux admin/back-office ou un webhook de paiement à
 * construire en Phase 4d.
 */
#[IsGranted('ROLE_USER')]
final class WalletController extends AbstractController
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/api/v1/wallet/me', name: 'api_wallet_me', methods: ['GET'])]
    public function me(#[CurrentUser] User $user): JsonResponse
    {
        $wallet = $this->walletService->getOrCreateWallet($user);

        return new JsonResponse([
            'balance' => $wallet->getBalance(),
            'holdBalance' => $wallet->getHoldBalance(),
            'spendableBalance' => $wallet->getSpendableBalance(),
            'currency' => $wallet->getCurrency(),
        ]);
    }

    /**
     * Point d'intégration futur paiement — protégé ROLE_ADMIN pour l'instant
     * (rechargement manuel back-office) tant que le vrai provider de
     * paiement n'est pas branché.
     */
    #[Route('/api/v1/wallet/{userId}/credit', name: 'api_wallet_credit', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function credit(string $userId, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $amount = (string) ($payload['amount'] ?? '0');

        if (bccomp($amount, '0', 2) <= 0) {
            return new JsonResponse(['error' => 'Le montant doit être positif.'], 422);
        }

        $user = $this->em->getRepository(User::class)->find($userId)
            ?? throw $this->createNotFoundException();

        $wallet = $this->walletService->getOrCreateWallet($user);
        $tx = $this->walletService->credit($wallet, $amount, $payload['note'] ?? 'Rechargement manuel back-office');

        return new JsonResponse(['transactionId' => $tx->getId()->toRfc4122(), 'newBalance' => $wallet->getBalance()]);
    }

    /**
     * Déclaration de recharge par l'utilisateur (§ PayPal/virement + reçu
     * WhatsApp, décision confirmée) : ne crédite RIEN automatiquement,
     * crée juste une entrée PENDING que l'admin rapproche du reçu reçu sur
     * WhatsApp avant de confirmer via /confirm ci-dessous.
     */
    #[Route('/api/v1/wallet/recharge-requests', name: 'api_wallet_recharge_declare', methods: ['POST'])]
    public function declareRecharge(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $payload = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $method = WalletRechargeMethod::tryFrom((string) ($payload['method'] ?? ''));
        $amount = (string) ($payload['amount'] ?? '0');

        if ($method === null || bccomp($amount, '0', 2) <= 0) {
            return new JsonResponse(['error' => 'method (PAYPAL|BANK_TRANSFER) et amount (>0) sont requis.'], 422);
        }

        $rechargeRequest = new WalletRechargeRequest($user, $method, $amount);
        $rechargeRequest->setReference($payload['reference'] ?? null);

        $this->em->persist($rechargeRequest);
        $this->em->flush();

        return new JsonResponse([
            'id' => $rechargeRequest->getId()->toRfc4122(),
            'status' => $rechargeRequest->getStatus()->value,
            'message' => 'Demande enregistrée. Envoie ton reçu sur le WhatsApp indiqué dans l\'app pour validation.',
        ], 201);
    }

    /** Back-office admin : liste des recharges en attente de rapprochement avec le reçu WhatsApp. */
    #[Route('/api/v1/admin/wallet/recharge-requests', name: 'api_admin_wallet_recharge_pending', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function pendingRecharges(): JsonResponse
    {
        $requests = $this->em->getRepository(WalletRechargeRequest::class)->findBy(
            ['status' => \App\Entity\WalletRechargeStatus::PENDING],
            ['createdAt' => 'ASC'],
        );

        return new JsonResponse(array_map(
            static fn (WalletRechargeRequest $r) => [
                'id' => $r->getId()->toRfc4122(),
                'userEmail' => $r->getUser()->getEmail(),
                'method' => $r->getMethod()->value,
                'announcedAmount' => $r->getAnnouncedAmount(),
                'reference' => $r->getReference(),
                'createdAt' => $r->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ],
            $requests,
        ));
    }

    /**
     * Confirmation admin après vérification manuelle du reçu WhatsApp :
     * crédite réellement le wallet (montant potentiellement différent de
     * l'annonce si le reçu montre autre chose) et marque la demande CONFIRMED.
     */
    #[Route('/api/v1/admin/wallet/recharge-requests/{id}/confirm', name: 'api_admin_wallet_recharge_confirm', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function confirmRecharge(string $id, Request $request, #[CurrentUser] User $admin): JsonResponse
    {
        $rechargeRequest = $this->em->getRepository(WalletRechargeRequest::class)->find($id)
            ?? throw $this->createNotFoundException();

        if ($rechargeRequest->getStatus() !== \App\Entity\WalletRechargeStatus::PENDING) {
            return new JsonResponse(['error' => 'Cette demande a déjà été traitée.'], 422);
        }

        $payload = json_decode($request->getContent() ?: '{}', true);
        $confirmedAmount = (string) ($payload['confirmedAmount'] ?? $rechargeRequest->getAnnouncedAmount());

        $wallet = $this->walletService->getOrCreateWallet($rechargeRequest->getUser());
        $tx = $this->walletService->credit(
            $wallet,
            $confirmedAmount,
            sprintf('Recharge %s confirmée (réf: %s)', $rechargeRequest->getMethod()->value, $rechargeRequest->getReference() ?? 'n/a'),
        );
        $rechargeRequest->confirm($admin, $confirmedAmount);

        $this->em->persist($rechargeRequest);
        $this->em->flush();

        return new JsonResponse(['transactionId' => $tx->getId()->toRfc4122(), 'newBalance' => $wallet->getBalance()]);
    }

    #[Route('/api/v1/admin/wallet/recharge-requests/{id}/reject', name: 'api_admin_wallet_recharge_reject', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function rejectRecharge(string $id, #[CurrentUser] User $admin): JsonResponse
    {
        $rechargeRequest = $this->em->getRepository(WalletRechargeRequest::class)->find($id)
            ?? throw $this->createNotFoundException();

        $rechargeRequest->reject($admin);
        $this->em->persist($rechargeRequest);
        $this->em->flush();

        return new JsonResponse(['status' => $rechargeRequest->getStatus()->value]);
    }
}
