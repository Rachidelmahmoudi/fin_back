<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\Admin\AdminSidebarCountsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminDashboardController extends AbstractController
{
    public function __construct(private readonly AdminSidebarCountsService $sidebarCounts)
    {
    }

    #[Route('/admin', name: 'admin_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        $counts = $this->sidebarCounts->get();

        return $this->render('admin/dashboard.html.twig', [
            'pendingCounts' => $counts,
            'totalPending' => array_sum($counts),
        ]);
    }
}
