<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\EstablishmentStatus;
use App\Repository\LaboratoryRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class LaboratoryItemProvider implements ProviderInterface
{
    public function __construct(private readonly LaboratoryRepository $repository)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
    {
        $laboratory = $this->repository->find($uriVariables['id'] ?? null);

        if ($laboratory === null || $laboratory->getStatus() !== EstablishmentStatus::PUBLISHED) {
            throw new NotFoundHttpException('Laboratoire introuvable.');
        }

        return LaboratoryResourceMapper::map($laboratory);
    }
}
