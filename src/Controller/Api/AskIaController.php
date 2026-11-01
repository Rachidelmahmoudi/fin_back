<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\AskIaSession;
use App\Entity\Medicine;
use App\Entity\User;
use App\Service\Ai\AskIaSessionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * §25. Workflow : POST /ask-ia/sessions {medicineId} démarre une session
 * et retourne la première question ; POST .../answer {answer: "OUI"|"NON"}
 * avance la conversation jusqu'à une conclusion.
 */
#[IsGranted('ROLE_USER')]
final class AskIaController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AskIaSessionService $sessionService,
    ) {
    }

    #[Route('/api/v1/ask-ia/sessions', name: 'api_ask_ia_start', methods: ['POST'])]
    public function start(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $payload = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $medicineId = $payload['medicineId'] ?? null;

        $medicine = $medicineId !== null ? $this->em->getRepository(Medicine::class)->find($medicineId) : null;
        if ($medicine === null) {
            return new JsonResponse(['error' => 'medicineId invalide.'], 422);
        }

        try {
            $session = $this->sessionService->start($user, $medicine);
        } catch (\RuntimeException $e) {
            // ExternalAiProvider non configuré (pas de clé API) — message
            // explicite plutôt qu'une 500 générique ou une fausse réponse.
            return new JsonResponse(['error' => 'Le service Ask IA est temporairement indisponible.'], 503);
        }

        return new JsonResponse($this->serialize($session), 201);
    }

    #[Route('/api/v1/ask-ia/sessions/{id}/answer', name: 'api_ask_ia_answer', methods: ['POST'])]
    public function answer(string $id, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $session = $this->em->getRepository(AskIaSession::class)->find($id)
            ?? throw $this->createNotFoundException();

        if (!$session->getUser()->getId()->equals($user->getId())) {
            throw $this->createAccessDeniedException();
        }

        $payload = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $userAnswer = strtoupper((string) ($payload['answer'] ?? ''));

        if (!\in_array($userAnswer, ['OUI', 'NON'], true)) {
            return new JsonResponse(['error' => 'answer doit être "OUI" ou "NON".'], 422);
        }

        try {
            $session = $this->sessionService->answer($session, $userAnswer);
        } catch (\DomainException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => 'Le service Ask IA est temporairement indisponible.'], 503);
        }

        return new JsonResponse($this->serialize($session));
    }

    private function serialize(AskIaSession $session): array
    {
        return [
            'id' => $session->getId()->toRfc4122(),
            'status' => $session->getStatus()->value,
            'currentQuestion' => $session->getCurrentQuestion(),
            'conclusionText' => $session->getConclusionText(),
            'qaHistory' => $session->getQaHistory(),
        ];
    }
}
