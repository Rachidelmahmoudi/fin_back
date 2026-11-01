<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

/**
 * §18 du cahier des charges — sécurité API mobile.
 *
 * Choix technique : LexikJWTAuthenticationBundle pour l'émission/vérification
 * des access tokens JWT (standard de facto Symfony, RS256 par défaut) —
 * PAS un JWT fait maison. `composer require lexik/jwt-authentication-bundle`.
 *
 * Ce service ne gère QUE le refresh token (rotatif, stocké hashé) et
 * l'émission d'un nouvel access token à partir d'un refresh valide ;
 * LexikJWTAuthenticationBundle gère nativement le login initial
 * (email+password -> access token) via son propre listener, donc pas
 * dupliqué ici.
 *
 * RAPPEL sécurité explicite (§18) : le Bundle ID / identifiant de l'app
 * mobile n'intervient JAMAIS dans ce flux. Il peut être loggé pour de
 * l'analytics côté serveur, mais n'est ni vérifié ni fait confiance comme
 * preuve d'identité — seuls email+password puis JWT+refresh token le sont.
 */
final class AuthService
{
    /** Durée de vie du refresh token — volontairement plus longue que l'access token (15 min). */
    private const REFRESH_TOKEN_TTL_DAYS = 30;

    public function __construct(
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly EntityManagerInterface $em,
        private readonly RefreshTokenRepository $refreshTokenRepository,
    ) {
    }

    /**
     * Émission initiale, après authentification réussie (login).
     *
     * @return array{accessToken: string, refreshToken: string, expiresIn: int}
     */
    public function issueTokenPair(User $user, ?string $deviceLabel = null): array
    {
        $accessToken = $this->jwtManager->create($user);
        $refreshTokenPlain = $this->generatePlainToken();

        $entity = new RefreshToken(
            $user,
            $this->hashToken($refreshTokenPlain),
            new \DateTimeImmutable(sprintf('+%d days', self::REFRESH_TOKEN_TTL_DAYS)),
            $deviceLabel,
        );
        $this->em->persist($entity);
        $this->em->flush();

        return [
            'accessToken' => $accessToken,
            'refreshToken' => $refreshTokenPlain,
            'expiresIn' => 900, // 15 min, doit matcher lexik_jwt_authentication.token_ttl
        ];
    }

    /**
     * Rotation : le refresh token présenté est immédiatement révoqué et
     * remplacé par un nouveau, même s'il était encore valide. Un refresh
     * token déjà révoqué qui est réutilisé est un signal de compromission
     * potentielle — à journaliser/alerter en Phase 4c (pas encore fait ici).
     *
     * @return array{accessToken: string, refreshToken: string, expiresIn: int}
     *
     * @throws \RuntimeException si le refresh token est invalide/expiré/révoqué
     */
    public function rotateAndIssue(string $refreshTokenPlain, ?string $deviceLabel = null): array
    {
        $hash = $this->hashToken($refreshTokenPlain);
        $existing = $this->refreshTokenRepository->findValidByHash($hash);

        if ($existing === null) {
            throw new \RuntimeException('Refresh token invalide, expiré ou déjà utilisé.');
        }

        $user = $existing->getUser();
        $existing->revoke();
        $this->em->persist($existing);

        return $this->issueTokenPair($user, $deviceLabel);
    }

    /** Déconnexion "cet appareil" : révoque un seul refresh token. */
    public function revoke(string $refreshTokenPlain): void
    {
        $existing = $this->refreshTokenRepository->findValidByHash($this->hashToken($refreshTokenPlain));
        $existing?->revoke();
        $this->em->flush();
    }

    /** Déconnexion "tous les appareils" — ex. après changement de mot de passe. */
    public function revokeAll(User $user): void
    {
        $this->refreshTokenRepository->revokeAllForUser($user);
    }

    private function generatePlainToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }
}
