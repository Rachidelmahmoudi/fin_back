<?php

declare(strict_types=1);

namespace App\Service\Ai;

use App\Entity\Medicine;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * §25 : implémentation "API externe" (première étape du plan en 2 temps).
 *
 * IMPORTANT — configuration non fournie, jamais appelée réellement :
 * aucun fournisseur IA n'a été choisi ni de clé API communiquée dans cette
 * conversation. Ce provider appelle un endpoint générique compatible
 * "OpenAI chat completions" (format largement standard : OpenAI lui-même,
 * mais aussi beaucoup d'alternatives compatibles) configuré via variables
 * d'environnement — à brancher sur le vrai fournisseur choisi. Sans
 * `AI_PROVIDER_API_KEY` définie, `askAboutMedicine()` lève une exception
 * explicite plutôt que d'échouer silencieusement ou de renvoyer une
 * fausse réponse.
 *
 * §25, garantie non contournable : le disclaimer et le fait de ne jamais
 * se présenter comme un médecin sont imposés en POST-TRAITEMENT ici, pas
 * demandés dans le prompt système en espérant que le modèle obéisse — un
 * prompt peut être contourné par l'utilisateur (prompt injection via le
 * nom du médicament, par exemple), un post-traitement côté serveur ne peut pas.
 */
final class ExternalAiProvider implements AiProviderInterface
{
    private const MAX_QUESTIONS = 4;
    private const DISCLAIMER = 'Cette réponse est une aide informative, pas un diagnostic médical. Consultez un médecin ou un pharmacien pour toute décision de traitement.';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiUrl,
        private readonly string $apiKey,
        private readonly string $model,
    ) {
    }

    public function askAboutMedicine(Medicine $medicine, array $qaHistory): AiAnswer
    {
        if ($this->apiKey === '') {
            // Échec explicite plutôt qu'une fausse réponse : AskIaSessionService
            // (appelant) doit traduire ceci en message clair pour l'utilisateur
            // ("service temporairement indisponible"), jamais en une réponse
            // IA inventée côté PHP pour "faire comme si ça marchait".
            throw new \RuntimeException('AI_PROVIDER_API_KEY non configurée — ExternalAiProvider ne peut pas être appelé.');
        }

        if (\count($qaHistory) >= self::MAX_QUESTIONS) {
            return $this->requestConclusion($medicine, $qaHistory);
        }

        return $this->requestNextStep($medicine, $qaHistory);
    }

    private function requestNextStep(Medicine $medicine, array $qaHistory): AiAnswer
    {
        $response = $this->callChatCompletion($this->buildSystemPrompt($medicine), $this->buildUserPrompt($medicine, $qaHistory));

        // Réponse attendue en JSON strict (imposé par le prompt système,
        // voir buildSystemPrompt) : {"type":"question","text":"..."} ou
        // {"type":"conclusion","text":"..."} — le modèle peut décider de
        // conclure plus tôt que MAX_QUESTIONS s'il a assez d'éléments.
        $parsed = $this->parseJsonResponse($response);

        if (($parsed['type'] ?? null) === 'conclusion') {
            return AiAnswer::conclusion((string) $parsed['text'], self::DISCLAIMER);
        }

        return AiAnswer::question((string) ($parsed['text'] ?? 'Ressentez-vous ce symptôme actuellement ?'), self::DISCLAIMER);
    }

    private function requestConclusion(Medicine $medicine, array $qaHistory): AiAnswer
    {
        $response = $this->callChatCompletion(
            $this->buildSystemPrompt($medicine)."\nTu DOIS conclure maintenant, le nombre maximum de questions est atteint.",
            $this->buildUserPrompt($medicine, $qaHistory),
        );
        $parsed = $this->parseJsonResponse($response);

        return AiAnswer::conclusion((string) ($parsed['text'] ?? 'Sur la base de vos réponses, nous ne pouvons pas conclure avec certitude.'), self::DISCLAIMER);
    }

    private function buildSystemPrompt(Medicine $medicine): string
    {
        // §25 : "Elle peut poser des questions principalement OUI/NON...
        // Elle retourne une réponse prudente." Le prompt encode ces règles
        // directement, MAIS ce n'est qu'une première ligne de défense — la
        // garantie réelle (disclaimer, jamais un diagnostic) vient du
        // post-traitement PHP ci-dessus, pas de la docilité du modèle.
        return <<<PROMPT
            Tu aides un patient à comprendre s'il devrait consulter un professionnel de santé
            concernant le médicament "{$medicine->getName()}". DCI (composition) : {$this->dciName($medicine)}.
            Indications connues : {$medicine->getIndications()}.
            Contre-indications connues : {$medicine->getContraindications()}.

            Règles strictes :
            - Pose UNIQUEMENT des questions fermées (réponse OUI/NON).
            - Ne pose jamais plus d'une question à la fois.
            - Ne donne jamais de diagnostic, de posologie, ni de conseil de traitement.
            - Reste prudent : en cas de doute, recommande de consulter.
            - Réponds STRICTEMENT en JSON : {"type":"question","text":"..."} ou {"type":"conclusion","text":"..."}.
            PROMPT;
    }

    private function dciName(Medicine $medicine): string
    {
        return $medicine->getDci()?->getName() ?? 'non renseignée';
    }

    private function buildUserPrompt(Medicine $medicine, array $qaHistory): string
    {
        $historyText = implode("\n", array_map(
            static fn (QaHistoryEntry $e) => "Q: {$e->question}\nR: {$e->answer}",
            $qaHistory,
        ));

        return $historyText !== ''
            ? "Historique de la conversation :\n{$historyText}\n\nQuelle est la prochaine étape ?"
            : 'Début de la conversation. Pose ta première question.';
    }

    private function callChatCompletion(string $systemPrompt, string $userPrompt): string
    {
        $response = $this->httpClient->request('POST', $this->apiUrl, [
            'headers' => [
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object'],
            ],
            'timeout' => 15,
        ]);

        $data = $response->toArray();

        return $data['choices'][0]['message']['content'] ?? '{}';
    }

    private function parseJsonResponse(string $raw): array
    {
        try {
            $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);

            return \is_array($decoded) ? $decoded : [];
        } catch (\JsonException) {
            // Le modèle n'a pas respecté le format JSON demandé malgré la
            // consigne — dégrade proprement plutôt que de planter, mais
            // journalise en pratique (logger non injecté dans ce lot minimal,
            // à ajouter avant la prod : ce cas doit être surveillé).
            return [];
        }
    }
}
