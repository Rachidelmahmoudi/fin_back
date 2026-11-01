<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Pharmacy;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Applique le PATCH sur l'entité déjà autorisée par PharmacyVoter (voir
 * ApiResource: security sur l'opération). Seuls les champs listés ici sont
 * modifiables via l'API — délibérément une allowlist plutôt qu'un mapping
 * automatique de toutes les propriétés du DTO, pour ne jamais laisser un
 * champ sensible (owner, isVerified, status, legacyReference) être modifié
 * par un simple PATCH pharmacien.
 *
 * §Addendum 2 — republication après édition : directe, aucun retour à
 * PENDING_REVIEW ici (contrairement à la création initiale d'un
 * Establishment, cf. Phase 4c).
 */
final class PharmacyPatchProcessor implements ProcessorInterface
{
    private const EDITABLE_FIELDS = ['name', 'addressRaw', 'district', 'phone', 'email', 'website'];

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Pharmacy
    {
        /** @var Pharmacy $pharmacy */
        $pharmacy = $data;

        $request = $context['request'] ?? null;
        $payload = $request !== null ? json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR) : [];

        foreach (self::EDITABLE_FIELDS as $field) {
            if (!\array_key_exists($field, $payload)) {
                continue;
            }
            $setter = 'set'.ucfirst($field);
            if (method_exists($pharmacy, $setter)) {
                $pharmacy->$setter($payload[$field]);
            }
        }

        $this->em->persist($pharmacy);
        $this->em->flush();

        return $pharmacy;
    }
}
