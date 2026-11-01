<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\EstablishmentStatus;
use App\Repository\DoctorRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class DoctorItemProvider implements ProviderInterface
{
    public function __construct(private readonly DoctorRepository $repository)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
    {
        $doctor = $this->repository->find($uriVariables['id'] ?? null);

        if ($doctor === null || $doctor->getStatus() !== EstablishmentStatus::PUBLISHED) {
            throw new NotFoundHttpException('Médecin introuvable.');
        }

        return DoctorResourceMapper::map($doctor);
    }
}
