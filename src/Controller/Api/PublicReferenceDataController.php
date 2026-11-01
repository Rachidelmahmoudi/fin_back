<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\City;
use App\Entity\Specialty;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpoints publics manquants, identifiés en construisant les écrans
 * mobiles (OnboardingPage — sélection de ville, §20 — et
 * EstablishmentCreatePage — sélection ville/spécialité, §13/§Addendum D)
 * qui devaient jusqu'ici se rabattre sur des listes statiques codées en
 * dur côté client faute d'endpoint serveur.
 *
 * Pas d'ApiResource API Platform ici (City/Specialty n'ont pas besoin du
 * DTO-first ni de pagination complexe — juste une liste complète pour
 * remplir un select) : un controller simple suffit et reste cohérent avec
 * le reste des endpoints "légers" du projet (ex: MedicineAutocompleteController).
 */
final class PublicReferenceDataController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/api/v1/cities', name: 'api_cities_list', methods: ['GET'])]
    public function cities(): JsonResponse
    {
        $cities = $this->em->getRepository(City::class)->findBy(['isActive' => true], ['name' => 'ASC']);

        return new JsonResponse(array_map(
            static fn (City $c) => [
                'id' => $c->getId()->toRfc4122(),
                'slug' => $c->getSlug(),
                'name' => $c->getName(),
                'lat' => $c->getLat() !== null ? (float) $c->getLat() : null,
                'lng' => $c->getLng() !== null ? (float) $c->getLng() : null,
            ],
            $cities,
        ));
    }

    #[Route('/api/v1/specialties', name: 'api_specialties_list', methods: ['GET'])]
    public function specialties(): JsonResponse
    {
        $specialties = $this->em->getRepository(Specialty::class)->findBy([], ['name' => 'ASC']);

        return new JsonResponse(array_map(
            static fn (Specialty $s) => [
                'id' => $s->getId()->toRfc4122(),
                'slug' => $s->getSlug(),
                'name' => $s->getName(),
            ],
            $specialties,
        ));
    }
}
