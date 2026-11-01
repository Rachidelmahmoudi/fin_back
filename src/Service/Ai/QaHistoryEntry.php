<?php

declare(strict_types=1);

namespace App\Service\Ai;

/** Une paire question/réponse déjà échangée — passée au provider pour qu'il sache où il en est dans la conversation. */
final class QaHistoryEntry
{
    public function __construct(
        public readonly string $question,
        public readonly string $answer, // "OUI" | "NON", §25 : "questions principalement OUI/NON"
    ) {
    }
}
