<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Entity\WalletRechargeMethod;
use App\Entity\WalletRechargeRequest;
use App\Entity\WalletRechargeStatus;
use App\Service\Admin\AdminSidebarCountsService;
use App\Service\Wallet\WalletService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * §27, décision confirmée (PayPal/virement + reçu WhatsApp + validation
 * manuelle) : "on va mettre un petit backend admin pour ça" — c'est
 * exactement cette page. Réutilise WalletService::credit() (Phase 4b), la
 * seule voie autorisée à modifier un solde (verrou pessimiste inclus).
 */
#[IsGranted('ROLE_ADMIN')]
final class AdminWalletController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly WalletService $walletService,
        private readonly AdminSidebarCountsService $sidebarCounts,
    ) {
    }

    #[Route('/admin/wallet/recharges', name: 'admin_wallet_recharges', methods: ['GET'])]
    public function pending(): Response
    {
        $requests = $this->em->getRepository(WalletRechargeRequest::class)->findBy(
            ['status' => WalletRechargeStatus::PENDING],
            ['createdAt' => 'ASC'],
        );

        return $this->render('admin/wallet_recharges.html.twig', [
            'requests' => $requests,
            'pendingCounts' => $this->sidebarCounts->get(),
        ]);
    }

    #[Route('/admin/wallet/recharges/{id}/confirm', name: 'admin_wallet_recharge_confirm', methods: ['POST'])]
    public function confirm(string $id, Request $request, #[CurrentUser] User $admin): RedirectResponse
    {
        $rechargeRequest = $this->em->getRepository(WalletRechargeRequest::class)->find($id) ?? throw $this->createNotFoundException();

        if ($rechargeRequest->getStatus() !== WalletRechargeStatus::PENDING) {
            $this->addFlash('error', 'Cette demande a déjà été traitée.');

            return $this->redirectToRoute('admin_wallet_recharges');
        }

        $confirmedAmount = $request->request->get('confirmedAmount') !== null && $request->request->get('confirmedAmount') !== ''
            ? (string) $request->request->get('confirmedAmount')
            : $rechargeRequest->getAnnouncedAmount();

        $wallet = $this->walletService->getOrCreateWallet($rechargeRequest->getUser());
        $this->walletService->credit(
            $wallet,
            $confirmedAmount,
            sprintf('Recharge %s confirmée (réf: %s)', $rechargeRequest->getMethod()->value, $rechargeRequest->getReference() ?? 'n/a'),
        );
        $rechargeRequest->confirm($admin, $confirmedAmount);

        $this->em->persist($rechargeRequest);
        $this->em->flush();

        $this->addFlash('success', sprintf(
            '%s %s crédités à %s.',
            $confirmedAmount,
            $wallet->getCurrency(),
            $rechargeRequest->getUser()->getEmail(),
        ));

        return $this->redirectToRoute('admin_wallet_recharges');
    }

    #[Route('/admin/wallet/recharges/{id}/reject', name: 'admin_wallet_recharge_reject', methods: ['POST'])]
    public function reject(string $id, #[CurrentUser] User $admin): RedirectResponse
    {
        $rechargeRequest = $this->em->getRepository(WalletRechargeRequest::class)->find($id) ?? throw $this->createNotFoundException();

        $rechargeRequest->reject($admin);
        $this->em->persist($rechargeRequest);
        $this->em->flush();

        $this->addFlash('success', 'Demande de recharge rejetée.');

        return $this->redirectToRoute('admin_wallet_recharges');
    }

    /**
     * §Correctif — l'app mobile ne fait plus déclarer de recharge par
     * l'utilisateur (juste un CTA WhatsApp) : ce formulaire permet à
     * l'admin de créditer directement un utilisateur, identifié par son
     * email, sans qu'une WalletRechargeRequest n'existe au préalable.
     * Crée quand même une WalletRechargeRequest immédiatement confirmée
     * (pas juste un WalletService::credit() nu) pour que cette recharge
     * reste visible dans le même historique que les anciennes demandes
     * auto-déclarées — une seule source de vérité pour "l'historique des
     * recharges", pas deux mécanismes parallèles.
     */
    #[Route('/admin/wallet/credit-direct', name: 'admin_wallet_credit_direct_form', methods: ['GET'])]
    public function creditDirectForm(): Response
    {
        return $this->render('admin/wallet_credit_direct.html.twig', [
            'pendingCounts' => $this->sidebarCounts->get(),
        ]);
    }

    #[Route('/admin/wallet/credit-direct', name: 'admin_wallet_credit_direct', methods: ['POST'])]
    public function creditDirect(Request $request): RedirectResponse
    {
        $email = trim((string) $request->request->get('email', ''));
        $amount = trim((string) $request->request->get('amount', ''));
        $note = trim((string) $request->request->get('note', ''));

        if ($email === '' || $amount === '' || !is_numeric($amount) || (float) $amount <= 0) {
            $this->addFlash('error', 'Email et montant (nombre positif) sont obligatoires.');

            return $this->redirectToRoute('admin_wallet_credit_direct_form');
        }

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($user === null) {
            $this->addFlash('error', sprintf('Aucun utilisateur trouvé avec l\'email "%s".', $email));

            return $this->redirectToRoute('admin_wallet_credit_direct_form');
        }

        /** @var User $admin */
        $admin = $this->getUser();

        // Créée puis confirmée dans la foulée — voir le docblock ci-dessus
        // pour pourquoi (garder un historique unique plutôt que deux
        // mécanismes de crédit parallèles).
        $rechargeRequest = new WalletRechargeRequest($user, WalletRechargeMethod::MANUAL, $amount);
        if ($note !== '') {
            $rechargeRequest->setReference($note);
        }
        $this->em->persist($rechargeRequest);
        $this->em->flush();

        $wallet = $this->walletService->getOrCreateWallet($user);
        $this->walletService->credit($wallet, $amount, 'Recharge manuelle admin (WhatsApp)'.($note !== '' ? ' — '.$note : ''));
        $rechargeRequest->confirm($admin, $amount);

        $this->em->persist($rechargeRequest);
        $this->em->flush();

        $this->addFlash('success', sprintf('%s MAD crédités à %s.', $amount, $email));

        return $this->redirectToRoute('admin_wallet_credit_direct_form');
    }
}
