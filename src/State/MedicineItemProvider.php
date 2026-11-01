<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Repository\MedicineRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

final class MedicineItemProvider implements ProviderInterface
{
    public function __construct(private readonly MedicineRepository $repository)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
    {
        $id = $uriVariables['id'] ?? null;
        $medicine = $id !== null ? $this->repository->find(Uuid::fromString($id)) : null;

        if ($medicine === null) {
            throw new NotFoundHttpException('Médicament introuvable.');
        }

        return MedicineResourceMapper::map($medicine);
    }
}
