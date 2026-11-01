<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Un message Messenger ne doit contenir QUE des données simples et
 * sérialisables — jamais une entité Doctrine directement (les proxies
 * Doctrine ne survivent pas au passage par la file d'attente). Le
 * handler recharge l'entité par son id.
 */
final class WelcomeEmailMessage
{
    public function __construct(public readonly string $userId)
    {
    }
}
