<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Repository\PharmacyRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

final class PharmacyItemProvider implements ProviderInterface
{
    public function __construct(private readonly PharmacyRepository $repository)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
    {
        $id = $uriVariables['id'] ?? null;
        $pharmacy = $id !== null ? $this->repository->find(Uuid::fromString($id)) : null;

        if ($pharmacy === null) {
            throw new NotFoundHttpException('Pharmacie introuvable.');
        }

        // PATCH : le PharmacyVoter (branché via security: sur l'opération)
        // reçoit l'entité Doctrine renvoyée ici comme $object à voter, avant
        // que le processor n'applique les changements — c'est pourquoi ce
        // provider renvoie l'entité et non le DTO pour l'opération d'écriture.
        if ($operation instanceof \ApiPlatform\Metadata\Patch) {
            return $pharmacy;
        }

        return PharmacyResourceMapper::map($pharmacy);
    }
}
