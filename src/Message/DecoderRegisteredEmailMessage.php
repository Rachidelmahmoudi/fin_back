<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Prêt à l'emploi mais PAS ENCORE DÉCLENCHÉ : aucune inscription en tant
 * que déchiffreur n'existe dans le projet actuel (ROLE_DECODER est
 * attribué à la main par un admin). À déclencher depuis le futur
 * endpoint d'inscription déchiffreur, une fois ce module construit.
 */
final class DecoderRegisteredEmailMessage
{
    public function __construct(public readonly string $userId)
    {
    }
}
