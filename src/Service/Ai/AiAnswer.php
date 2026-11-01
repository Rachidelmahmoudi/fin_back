<?php

declare(strict_types=1);

namespace App\Service\Ai;

/**
 * §25 du cahier des charges : "L'IA ne doit jamais être présentée comme un
 * médecin. Toujours afficher une recommandation de consulter un
 * médecin/pharmacien lorsque nécessaire." `disclaimer` est donc un champ
 * NON NULLABLE et NON optionnel — un AiProvider ne peut pas physiquement
 * construire un AiAnswer sans en fournir un (pas de valeur par défaut),
 * pour que ce rappel ne puisse jamais être oublié par erreur dans une
 * future implémentation.
 */
final class AiAnswer
{
    public function __construct(
        public readonly bool $isConclusion,
        public readonly ?string $nextQuestion,
        public readonly ?string $conclusionText,
        public readonly string $disclaimer,
    ) {
    }

    public static function question(string $question, string $disclaimer): self
    {
        return new self(isConclusion: false, nextQuestion: $question, conclusionText: null, disclaimer: $disclaimer);
    }

    public static function conclusion(string $conclusionText, string $disclaimer): self
    {
        return new self(isConclusion: true, nextQuestion: null, conclusionText: $conclusionText, disclaimer: $disclaimer);
    }
}
