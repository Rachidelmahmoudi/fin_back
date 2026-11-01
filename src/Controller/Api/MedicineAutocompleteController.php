<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\MedicineRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * §Addendum 2, décision confirmée : "le déchiffreur doit avoir un moyen
 * d'autocomplétion par nom de médicament pour plus de rapidité sur le
 * service". Endpoint dédié plutôt qu'un simple filtre sur /api/v1/medicines
 * pour pouvoir :
 * - limiter volontairement la charge utile (id + name uniquement, pas toute
 *   la fiche médicament) puisqu'il est appelé à chaque frappe clavier ;
 * - appliquer un rate limiting plus permissif que sur les endpoints de
 *   mutation, mais quand même présent (§18) pour éviter l'abus ;
 * - à terme, brancher un vrai moteur de recherche texte (Meilisearch/
 *   Typesense ou pg_trgm) sans changer le contrat de cet endpoint.
 *
 * Utilisé par PrescriptionResult (§26/§Addendum 2) : chaque ligne du
 * résultat soumis par le déchiffreur référence un `medicineId` retourné
 * ici, jamais une saisie texte libre — garantit que le lien vers
 * Medicine reste exploitable pour le calcul du coût et la recherche de
 * disponibilité en pharmacie.
 */
final class MedicineAutocompleteController extends AbstractController
{
    private const MAX_RESULTS = 10;
    private const MIN_QUERY_LENGTH = 2;

    public function __construct(private readonly MedicineRepository $repository)
    {
    }

    // priority: sans elle, API Platform (GET /medicines/{id}) capture "autocomplete" comme un id et répond "Invalid UUID" (500).
    #[Route('/api/v1/medicines/autocomplete', name: 'api_medicines_autocomplete', methods: ['GET'], priority: 10)]
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query->get('q', ''));

        if (mb_strlen($q) < self::MIN_QUERY_LENGTH) {
            return new JsonResponse([]);
        }

        $medicines = $this->repository->createQueryBuilder('m')
            ->andWhere('LOWER(m.name) LIKE :q')
            ->setParameter('q', mb_strtolower($q).'%') // préfixe : plus rapide et plus pertinent pour de la saisie au clavier
            ->orderBy('m.name', 'ASC')
            ->setMaxResults(self::MAX_RESULTS)
            ->getQuery()
            ->getResult();

        return new JsonResponse(array_map(
            static fn ($m) => [
                'id' => $m->getId()->toRfc4122(),
                'name' => $m->getName(),
                'dosage' => $m->getDosageValue() !== null ? $m->getDosageValue().' '.$m->getDosageUnit() : null,
                'form' => $m->getForm(),
            ],
            $medicines,
        ));
    }
}
