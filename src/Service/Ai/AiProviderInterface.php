<?php

declare(strict_types=1);

namespace App\Service\Ai;

use App\Entity\Medicine;

/**
 * §25 du cahier des charges : "Créer une abstraction permettant de
 * remplacer facilement le fournisseur IA externe par notre future base
 * interne [de prospectus médicaux]." C'est exactement le rôle de cette
 * interface — AskIaController (et AskIaSessionService) ne connaissent que
 * ce contrat, jamais un provider concret directement.
 *
 * Implémentations prévues :
 * - ExternalAiProvider (ce lot) : appelle une API IA externe configurable.
 * - InternalProspectusProvider (futur) : base JSON de prospectus médicaux,
 *   pas encore construite (§25 : "Plus tard : base JSON de prospectus
 *   médicaux") — le jour où elle existe, switch par config uniquement.
 */
interface AiProviderInterface
{
    /**
     * @param QaHistoryEntry[] $qaHistory questions déjà posées et réponses OUI/NON déjà données dans cette session
     */
    public function askAboutMedicine(Medicine $medicine, array $qaHistory): AiAnswer;
}
