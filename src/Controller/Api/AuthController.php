<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Security\AuthService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * §17 du cahier des charges. Le login lui-même (email+password ->
 * accessToken+refreshToken) est délégué au json_login de
 * LexikJWTAuthenticationBundle configuré en security.yaml (voir
 * README_PHASE4B.md) — pas réimplémenté ici pour éviter de dupliquer une
 * logique que le bundle gère déjà correctement (vérification du hash,
 * throttling des tentatives, etc.). Ce controller ne couvre que ce que le
 * bundle ne fait pas nativement : inscription, refresh, logout.
 */
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly AuthService $authService,
    ) {
    }

    #[Route('/api/v1/auth/register', name: 'api_auth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $email = trim((string) ($payload['email'] ?? ''));
        $plainPassword = (string) ($payload['password'] ?? '');

        if ($email === '' || $plainPassword === '') {
            return new JsonResponse(['error' => 'email et password sont requis.'], 422);
        }

        if ($this->em->getRepository(User::class)->findOneBy(['email' => $email]) !== null) {
            return new JsonResponse(['error' => 'Cet email est déjà utilisé.'], 409);
        }

        $user = new User($email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $user->setPhone($payload['phone'] ?? null);
        $user->setLocale($payload['locale'] ?? 'fr');

        $this->em->persist($user);
        $this->em->flush();

        $tokens = $this->authService->issueTokenPair($user, $payload['deviceLabel'] ?? null);

        return new JsonResponse($tokens, 201);
    }

    /**
     * L'access token JWT (émis par le bundle Lexik au login) a une durée de
     * vie courte (15 min, §Phase 3). Ce endpoint échange un refresh token
     * valide contre une nouvelle paire — rotation systématique (§18).
     */
    #[Route('/api/v1/auth/refresh', name: 'api_auth_refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $refreshToken = (string) ($payload['refreshToken'] ?? '');

        try {
            $tokens = $this->authService->rotateAndIssue($refreshToken, $payload['deviceLabel'] ?? null);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 401);
        }

        return new JsonResponse($tokens);
    }

    #[Route('/api/v1/auth/logout', name: 'api_auth_logout', methods: ['POST'])]
    public function logout(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        $payload = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $refreshToken = $payload['refreshToken'] ?? null;
        $allDevices = (bool) ($payload['allDevices'] ?? false);

        if ($allDevices && $user !== null) {
            $this->authService->revokeAll($user);
        } elseif ($refreshToken !== null) {
            $this->authService->revoke($refreshToken);
        }

        return new JsonResponse(null, 204);
    }
}
