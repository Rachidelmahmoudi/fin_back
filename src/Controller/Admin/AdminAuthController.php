<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Le POST vers cette même route (`check_path: admin_login`, security.yaml)
 * est intercepté par le listener form_login de Symfony AVANT d'atteindre
 * ce controller — cette action ne gère donc que le rendu du formulaire
 * (GET) et l'affichage d'une éventuelle erreur d'authentification.
 */
final class AdminAuthController extends AbstractController
{
    #[Route('/admin/login', name: 'admin_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        return $this->render('admin/login.html.twig', [
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'lastUsername' => $authenticationUtils->getLastUsername(),
        ]);
    }

    #[Route('/admin/logout', name: 'admin_logout', methods: ['GET'])]
    public function logout(): never
    {
        // Interceptée par le listener `logout` du firewall admin
        // (security.yaml) — cette méthode ne s'exécute jamais réellement,
        // mais Symfony exige un controller pour que la route existe.
        throw new \LogicException('Cette méthode ne devrait jamais être appelée directement.');
    }
}
