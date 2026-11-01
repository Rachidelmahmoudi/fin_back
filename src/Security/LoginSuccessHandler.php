<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * Référencé par config/packages/security.yaml (firewall api_login).
 *
 * Correctif : la réponse est maintenant construite ici avec les MÊMES clés
 * que /auth/register et /auth/refresh (accessToken, refreshToken,
 * expiresIn). Avant, on renvoyait JWTAuthenticationSuccessResponse de
 * Lexik, dont la clé est "token" : le client mobile (qui lit
 * "accessToken") stockait `undefined` après un login réussi.
 * Un seul format de réponse pour les trois endpoints d'authentification.
 */
final class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        /** @var User $user */
        $user = $token->getUser();

        $payload = json_decode($request->getContent(), true) ?? [];
        $tokens = $this->authService->issueTokenPair($user, $payload['deviceLabel'] ?? null);

        return new JsonResponse([
            'accessToken' => $tokens['accessToken'],
            'refreshToken' => $tokens['refreshToken'],
            'expiresIn' => $tokens['expiresIn'],
        ]);
    }
}
