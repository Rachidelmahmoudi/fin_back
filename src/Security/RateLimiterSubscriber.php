<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * §18 : applique les limiteurs déclarés dans rate_limiter.yaml. La clé du
 * limiteur combine IP + utilisateur authentifié (si présent) pour que le
 * throttling ne pénalise pas tous les utilisateurs d'un même NAT/proxy
 * d'entreprise, tout en restant efficace contre un utilisateur isolé qui
 * abuse d'un endpoint depuis plusieurs IP (rotation via proxy) — la clé
 * utilisateur prend le dessus quand elle est disponible.
 */
final class RateLimiterSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RateLimiterFactory $apiAuthLimiter,
        private readonly RateLimiterFactory $apiAuthRefreshLimiter,
        private readonly RateLimiterFactory $apiMedicineAutocompleteLimiter,
        private readonly RateLimiterFactory $apiPrescriptionMutationLimiter,
        private readonly RateLimiterFactory $apiDefaultLimiter,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 8]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route', '');

        if (!str_starts_with($route, 'api_')) {
            return;
        }

        $key = $request->getClientIp().'|'.($request->attributes->get('_user_identifier') ?? 'anon');

        $factory = match (true) {
            $route === 'api_auth_register' || $route === 'api_login' => $this->apiAuthLimiter,
            $route === 'api_auth_refresh' => $this->apiAuthRefreshLimiter,
            $route === 'api_medicines_autocomplete' => $this->apiMedicineAutocompleteLimiter,
            str_starts_with($route, 'api_prescriptions_') => $this->apiPrescriptionMutationLimiter,
            default => $this->apiDefaultLimiter,
        };

        $limiter = $factory->create($key);
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            $event->setResponse(new JsonResponse(
                ['error' => 'Trop de requêtes — réessaie dans quelques instants.'],
                429,
                ['Retry-After' => (string) $limit->getRetryAfter()->getTimestamp()],
            ));
        }
    }
}
