<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Entity\Doctor;
use App\Entity\EstablishmentStatus;
use App\Entity\Laboratory;
use App\Entity\Pharmacy;
use App\Entity\PharmacyStatus;
use App\Entity\WalletRechargeRequest;
use App\Entity\WalletRechargeStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Chaque controller admin (Dashboard, Establishment*, Wallet) a besoin des
 * mêmes 4 compteurs pour les badges de la sidebar (admin/base.html.twig) —
 * centralisé ici plutôt que dupliqué dans chaque action, ou pire, appelé
 * d'un controller à l'autre (pas idiomatique en Symfony).
 */
final class AdminSidebarCountsService
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** @return array{pharmacies: int, doctors: int, laboratories: int, recharges: int} */
    public function get(): array
    {
        return [
            'pharmacies' => (int) $this->em->getRepository(Pharmacy::class)->count(['status' => PharmacyStatus::PENDING]),
            'doctors' => (int) $this->em->getRepository(Doctor::class)->count(['status' => EstablishmentStatus::PENDING_REVIEW]),
            'laboratories' => (int) $this->em->getRepository(Laboratory::class)->count(['status' => EstablishmentStatus::PENDING_REVIEW]),
            'recharges' => (int) $this->em->getRepository(WalletRechargeRequest::class)->count(['status' => WalletRechargeStatus::PENDING]),
        ];
    }
}
