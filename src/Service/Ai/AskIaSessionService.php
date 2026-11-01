<?php

declare(strict_types=1);

namespace App\Service\Ai;

use App\Entity\AskIaSession;
use App\Entity\Medicine;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Ne dépend que d'AiProviderInterface (§25 : abstraction remplaçable) —
 * jamais d'ExternalAiProvider directement. Le jour où InternalProspectusProvider
 * existe, seul le wiring dans services.yaml change, ce fichier ne bouge pas.
 */
final class AskIaSessionService
{
    public function __construct(
        private readonly AiProviderInterface $aiProvider,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function start(User $user, Medicine $medicine): AskIaSession
    {
        $session = new AskIaSession($user, $medicine);
        $this->em->persist($session);

        $answer = $this->aiProvider->askAboutMedicine($medicine, []);
        $this->applyAnswer($session, $answer);

        $this->em->flush();

        return $session;
    }

    /**
     * @throws \DomainException si la session est déjà terminée ou si aucune question n'est en attente
     */
    public function answer(AskIaSession $session, string $userAnswer): AskIaSession
    {
        $pendingQuestion = $session->getCurrentQuestion();
        if ($pendingQuestion === null) {
            throw new \DomainException('Aucune question en attente pour cette session.');
        }

        $session->recordAnswer($pendingQuestion, $userAnswer);

        $qaHistory = array_map(
            static fn (array $entry) => new QaHistoryEntry($entry['question'], $entry['answer']),
            $session->getQaHistory(),
        );

        $answer = $this->aiProvider->askAboutMedicine($session->getMedicine(), $qaHistory);
        $this->applyAnswer($session, $answer);

        $this->em->flush();

        return $session;
    }

    private function applyAnswer(AskIaSession $session, AiAnswer $answer): void
    {
        if ($answer->isConclusion) {
            // §25 : le disclaimer est concaténé à la conclusion stockée —
            // même si un jour quelqu'un affiche conclusionText seul sans
            // repasser par l'API (export, log, etc.), le rappel reste présent.
            $session->complete($answer->conclusionText.' '.$answer->disclaimer);
        } else {
            $session->setCurrentQuestion($answer->nextQuestion ?? '');
        }

        $this->em->persist($session);
    }
}
