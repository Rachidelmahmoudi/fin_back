<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\City;
use App\Entity\Doctor;
use App\Entity\LabTest;
use App\Entity\Laboratory;
use App\Entity\Pharmacy;
use App\Entity\Specialty;
use App\Entity\User;
use App\Service\Establishment\EstablishmentModerationService;
use App\Service\Import\SlugHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * §13 et §Addendum D — endpoints derrière "/ajouter-mon-etablissement".
 * Volontairement deux endpoints séparés (doctors/laboratories) plutôt
 * qu'un seul endpoint générique avec un paramètre "type", pour que chaque
 * payload de création reste explicite et fortement typé côté client
 * mobile/web (le formulaire multi-étapes diffère réellement entre un
 * médecin et un laboratoire — spécialités vs analyses proposées).
 *
 * Chaque soumission crée l'entité en DRAFT puis la fait immédiatement
 * transiter vers PENDING_REVIEW via EstablishmentModerationService — non
 * visible publiquement tant qu'un admin n'a pas approuvé (§Addendum D).
 */
#[IsGranted('ROLE_USER')]
final class EstablishmentController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EstablishmentModerationService $moderationService,
    ) {
    }

    #[Route('/api/v1/establishments/doctors', name: 'api_establishments_create_doctor', methods: ['POST'])]
    public function createDoctor(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $payload = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $name = trim((string) ($payload['name'] ?? ''));
        $addressRaw = trim((string) ($payload['addressRaw'] ?? ''));
        $cityId = $payload['cityId'] ?? null;

        if ($name === '' || $addressRaw === '' || $cityId === null) {
            return new JsonResponse(['error' => 'name, addressRaw et cityId sont requis.'], 422);
        }

        $city = $this->em->getRepository(City::class)->find($cityId);
        if ($city === null) {
            return new JsonResponse(['error' => 'Ville introuvable.'], 422);
        }

        $doctor = new Doctor(SlugHelper::shortUid(), SlugHelper::slugify($name), $name, $addressRaw, $city);
        $doctor->setOwner($user);
        $doctor->setDistrict($payload['district'] ?? null);
        $doctor->setPhone($payload['phone'] ?? null);
        $doctor->setEmail($payload['email'] ?? null);
        $doctor->setWebsite($payload['website'] ?? null);
        $doctor->setBio($payload['bio'] ?? null);
        $doctor->setOrderNumber($payload['orderNumber'] ?? null);
        if (isset($payload['consultationPrice'])) {
            $doctor->setConsultationPrice((string) $payload['consultationPrice']);
        }
        if (isset($payload['lat'], $payload['lng'])) {
            $doctor->setLat((string) $payload['lat']);
            $doctor->setLng((string) $payload['lng']);
        }
        if (!empty($payload['photoUrl'])) {
            $doctor->setPhotoUrls([(string) $payload['photoUrl']]);
        }

        foreach ((array) ($payload['specialtyIds'] ?? []) as $specialtyId) {
            $specialty = $this->em->getRepository(Specialty::class)->find($specialtyId);
            if ($specialty !== null) {
                $doctor->addSpecialty($specialty);
            }
        }

        $this->em->persist($doctor);
        $this->em->flush();

        $this->moderationService->submitForReview($doctor);

        return new JsonResponse(['id' => $doctor->getId()->toRfc4122(), 'status' => $doctor->getStatus()->value], 201);
    }

    #[Route('/api/v1/establishments/laboratories', name: 'api_establishments_create_laboratory', methods: ['POST'])]
    public function createLaboratory(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $payload = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $name = trim((string) ($payload['name'] ?? ''));
        $addressRaw = trim((string) ($payload['addressRaw'] ?? ''));
        $cityId = $payload['cityId'] ?? null;

        if ($name === '' || $addressRaw === '' || $cityId === null) {
            return new JsonResponse(['error' => 'name, addressRaw et cityId sont requis.'], 422);
        }

        $city = $this->em->getRepository(City::class)->find($cityId);
        if ($city === null) {
            return new JsonResponse(['error' => 'Ville introuvable.'], 422);
        }

        $laboratory = new Laboratory(SlugHelper::shortUid(), SlugHelper::slugify($name), $name, $addressRaw, $city);
        $laboratory->setOwner($user);
        $laboratory->setDistrict($payload['district'] ?? null);
        $laboratory->setPhone($payload['phone'] ?? null);
        $laboratory->setEmail($payload['email'] ?? null);
        $laboratory->setWebsite($payload['website'] ?? null);
        $laboratory->setAccreditation($payload['accreditation'] ?? null);
        if (isset($payload['lat'], $payload['lng'])) {
            $laboratory->setLat((string) $payload['lat']);
            $laboratory->setLng((string) $payload['lng']);
        }
        if (!empty($payload['photoUrl'])) {
            $laboratory->setPhotoUrls([(string) $payload['photoUrl']]);
        }

        foreach ((array) ($payload['labTests'] ?? []) as $testPayload) {
            if (!isset($testPayload['name'])) {
                continue;
            }
            $test = new LabTest($laboratory, (string) $testPayload['name']);
            if (isset($testPayload['price'])) {
                $test->setPrice((string) $testPayload['price']);
            }
            $test->setCategory($testPayload['category'] ?? null);
            $laboratory->addLabTest($test);
        }

        $this->em->persist($laboratory);
        $this->em->flush();

        $this->moderationService->submitForReview($laboratory);

        return new JsonResponse(['id' => $laboratory->getId()->toRfc4122(), 'status' => $laboratory->getStatus()->value], 201);
    }

    /**
     * §Phase 4f — endpoint manquant identifié en câblant le formulaire web :
     * il n'existait jusqu'ici aucune création de pharmacie, seulement un
     * PATCH pour éditer une pharmacie existante (Phase 4b).
     *
     * Pharmacy ne suit PAS EstablishmentModerationService/
     * ModeratableEstablishment (elle a son propre PharmacyStatus depuis la
     * Phase 4a — PENDING/ACTIVE/SUSPENDED, pas de REJECTED). Son
     * constructeur initialise déjà `status = PENDING` par défaut : créer
     * une pharmacie la place donc automatiquement en attente de modération,
     * sans transition explicite supplémentaire comme pour Doctor/Laboratory.
     * L'approbation (PENDING -> ACTIVE) est gérée par
     * ModerationController::approvePharmacy() ci-dessous, dans un
     * controller séparé, par cohérence avec ce choix déjà pris en Phase 4c
     * de ne pas forcer Pharmacy dans la même hiérarchie que les deux autres.
     */
    #[Route('/api/v1/establishments/pharmacies', name: 'api_establishments_create_pharmacy', methods: ['POST'])]
    public function createPharmacy(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $payload = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $name = trim((string) ($payload['name'] ?? ''));
        $addressRaw = trim((string) ($payload['addressRaw'] ?? ''));
        $cityId = $payload['cityId'] ?? null;

        if ($name === '' || $addressRaw === '' || $cityId === null) {
            return new JsonResponse(['error' => 'name, addressRaw et cityId sont requis.'], 422);
        }

        $city = $this->em->getRepository(City::class)->find($cityId);
        if ($city === null) {
            return new JsonResponse(['error' => 'Ville introuvable.'], 422);
        }

        $pharmacy = new Pharmacy(SlugHelper::shortUid(), SlugHelper::slugify($name), $name, $addressRaw, $city);
        $pharmacy->setOwner($user);
        $pharmacy->setDistrict($payload['district'] ?? null);
        $pharmacy->setPhone($payload['phone'] ?? null);
        $pharmacy->setEmail($payload['email'] ?? null);
        $pharmacy->setWebsite($payload['website'] ?? null);
        if (isset($payload['lat'], $payload['lng'])) {
            $pharmacy->setLat((string) $payload['lat']);
            $pharmacy->setLng((string) $payload['lng']);
        }
        if (!empty($payload['photoUrl'])) {
            $pharmacy->setPhoto((string) $payload['photoUrl']);
        }

        $this->em->persist($pharmacy);
        $this->em->flush();

        // Pas d'appel à EstablishmentModerationService ici : Pharmacy est
        // déjà PENDING par construction (voir docblock ci-dessus).
        return new JsonResponse(['id' => $pharmacy->getId()->toRfc4122(), 'status' => $pharmacy->getStatus()->value], 201);
    }
}
