<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Dci;
use App\Entity\Medicine;
use App\Entity\MedicineTableau;
use App\Entity\PrincepsOrGeneric;
use App\Service\Admin\AdminSidebarCountsService;
use App\Service\Import\SlugHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gestion des médicaments côté admin (CRUD complet) — vient compléter les
 * commandes d'import en masse (app:import:medicines:*) par une interface
 * pour les ajouts/corrections ponctuels au fil de l'eau, sans repasser par
 * un fichier JSON à chaque fois.
 *
 * Réutilise les mêmes précautions déjà apprises lors de la construction
 * de l'import en masse (voir ImportMergedMedicinesCommand) :
 * - recherche de Dci par SLUG (pas par nom brut) pour éviter les doublons
 *   de casse/accents ("Alendronate" vs "ALENDRONATE") ;
 * - gestion explicite des collisions de slug médicament (plusieurs
 *   dosages d'une même marque partagent le même nom) ;
 * - troncature défensive des champs VARCHAR(255).
 */
#[IsGranted('ROLE_ADMIN')]
final class AdminMedicineController extends AbstractController
{
    private const PAGE_SIZE = 30;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AdminSidebarCountsService $sidebarCounts,
    ) {
    }

    #[Route('/admin/medicines', name: 'admin_medicines_list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $q = trim((string) $request->query->get('q', ''));
        $needsReviewOnly = $request->query->getBoolean('needsReview', false);

        $qb = $this->em->getRepository(Medicine::class)->createQueryBuilder('m')
            ->leftJoin('m.dci', 'd')->addSelect('d')
            ->orderBy('m.createdAt', 'DESC');

        if ($q !== '') {
            $qb->andWhere('LOWER(m.name) LIKE :q')->setParameter('q', '%'.mb_strtolower($q).'%');
        }
        if ($needsReviewOnly) {
            $qb->andWhere('m.needsReview = true');
        }

        $totalCount = (int) (clone $qb)->select('COUNT(m.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        $medicines = $qb
            ->setFirstResult(($page - 1) * self::PAGE_SIZE)
            ->setMaxResults(self::PAGE_SIZE)
            ->getQuery()
            ->getResult();

        return $this->render('admin/medicines_list.html.twig', [
            'medicines' => $medicines,
            'page' => $page,
            'totalPages' => max(1, (int) ceil($totalCount / self::PAGE_SIZE)),
            'totalCount' => $totalCount,
            'q' => $q,
            'needsReviewOnly' => $needsReviewOnly,
            'pendingCounts' => $this->sidebarCounts->get(),
        ]);
    }

    #[Route('/admin/medicines/new', name: 'admin_medicines_new', methods: ['GET'])]
    public function newForm(): Response
    {
        return $this->render('admin/medicine_form.html.twig', [
            'medicine' => null,
            'pendingCounts' => $this->sidebarCounts->get(),
        ]);
    }

    #[Route('/admin/medicines', name: 'admin_medicines_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $name = trim((string) $request->request->get('name', ''));
        if ($name === '') {
            $this->addFlash('error', 'Le nom est obligatoire.');

            return $this->redirectToRoute('admin_medicines_new');
        }

        $medicine = new Medicine(SlugHelper::shortUid(), $this->uniqueSlug(SlugHelper::slugify($name)), $name);
        $this->applyForm($medicine, $request);

        $this->em->persist($medicine);
        $this->em->flush();

        $this->addFlash('success', sprintf('Médicament "%s" créé.', $medicine->getName()));

        return $this->redirectToRoute('admin_medicines_list');
    }

    #[Route('/admin/medicines/{id}/edit', name: 'admin_medicines_edit', methods: ['GET'])]
    public function editForm(string $id): Response
    {
        $medicine = $this->em->getRepository(Medicine::class)->find($id) ?? throw $this->createNotFoundException();

        return $this->render('admin/medicine_form.html.twig', [
            'medicine' => $medicine,
            'pendingCounts' => $this->sidebarCounts->get(),
        ]);
    }

    #[Route('/admin/medicines/{id}', name: 'admin_medicines_update', methods: ['POST'])]
    public function update(string $id, Request $request): Response
    {
        $medicine = $this->em->getRepository(Medicine::class)->find($id) ?? throw $this->createNotFoundException();

        $name = trim((string) $request->request->get('name', ''));
        if ($name === '') {
            $this->addFlash('error', 'Le nom est obligatoire.');

            return $this->redirectToRoute('admin_medicines_edit', ['id' => $id]);
        }

        // Le nom peut changer à l'édition — recalcule le slug seulement si
        // le nom a effectivement changé, pour ne pas casser une URL/slug
        // déjà partagée ou référencée ailleurs à chaque simple correction
        // de prix ou d'indications.
        if ($medicine->getName() !== $name) {
            $medicine->setName($name);
        }

        $this->applyForm($medicine, $request);

        $this->em->persist($medicine);
        $this->em->flush();

        $this->addFlash('success', sprintf('Médicament "%s" mis à jour.', $medicine->getName()));

        return $this->redirectToRoute('admin_medicines_edit', ['id' => $id]);
    }

    #[Route('/admin/medicines/{id}/delete', name: 'admin_medicines_delete', methods: ['POST'])]
    public function delete(string $id): Response
    {
        $medicine = $this->em->getRepository(Medicine::class)->find($id) ?? throw $this->createNotFoundException();
        $name = $medicine->getName();

        // §Prudence : un médicament peut être référencé ailleurs (résultat
        // de déchiffrement, stock pharmacien à venir...) — une vraie
        // suppression pourrait casser des références historiques. Pour ce
        // premier lot, suppression directe (pas d'archivage) : le volume
        // de médicaments créés à la main via cet écran reste faible et
        // récent, contrairement au catalogue importé en masse.
        $this->em->remove($medicine);
        $this->em->flush();

        $this->addFlash('success', sprintf('Médicament "%s" supprimé.', $name));

        return $this->redirectToRoute('admin_medicines_list');
    }

    private function applyForm(Medicine $medicine, Request $request): void
    {
        $dciName = trim((string) $request->request->get('dciName', ''));
        if ($dciName !== '') {
            // Recherche par SLUG, pas par nom brut — voir le docblock de
            // la classe et ImportMergedMedicinesCommand pour le pourquoi
            // (une même DCI orthographiée différemment selon la casse ne
            // doit jamais créer deux lignes qui collisionnent ensuite sur
            // la contrainte d'unicité du slug).
            $dciSlug = SlugHelper::slugify($dciName);
            $dci = $this->em->getRepository(Dci::class)->findOneBy(['slug' => $dciSlug])
                ?? new Dci($this->truncate($dciName), $dciSlug);
            $this->em->persist($dci);
            $medicine->setDci($dci);
        }

        $medicine->setDosageValue($this->nullableString($request, 'dosageValue'));
        $medicine->setDosageUnit($this->truncate($this->nullableString($request, 'dosageUnit')));
        $medicine->setForm($this->truncate($this->nullableString($request, 'form')));
        $medicine->setPresentation($this->truncate($this->nullableString($request, 'presentation')));
        $medicine->setManufacturer($this->truncate($this->nullableString($request, 'manufacturer')));
        $medicine->setTherapeuticClass($this->truncate($this->nullableString($request, 'therapeuticClass')));
        $medicine->setAtcCode($this->truncate($this->nullableString($request, 'atcCode')));

        $tableau = $request->request->get('tableau');
        $medicine->setTableau($tableau !== null && $tableau !== '' && \in_array($tableau, ['A', 'B'], true) ? MedicineTableau::from($tableau) : null);

        $princeps = $request->request->get('princepsOrGeneric');
        $medicine->setPrincepsOrGeneric($princeps !== null && $princeps !== '' && \in_array($princeps, ['P', 'G'], true) ? PrincepsOrGeneric::from($princeps) : null);

        $medicine->setPriceCommercial($this->nullableString($request, 'priceCommercial'));
        $medicine->setPriceHospitalCommercial($this->nullableString($request, 'priceHospitalCommercial'));
        $medicine->setIndications($this->nullableString($request, 'indications'));
        $medicine->setContraindications($this->nullableString($request, 'contraindications'));
        $medicine->setPrecautions($this->nullableString($request, 'precautions'));
        $medicine->setEan13Code($this->truncate($this->nullableString($request, 'ean13Code')));
        $medicine->setPpvCnss($this->nullableString($request, 'ppvCnss'));
        $medicine->setPhCnss($this->nullableString($request, 'phCnss'));
        $medicine->setPpvBrCnss($this->nullableString($request, 'ppvBrCnss'));
        $medicine->setReimbursementRate($this->nullableString($request, 'reimbursementRate'));
        $medicine->setExonerationRate($this->nullableString($request, 'exonerationRate'));
        $medicine->setExonerationEligible($request->request->getBoolean('exonerationEligible'));
        $medicine->setNeedsReview($request->request->getBoolean('needsReview'));
    }

    private function nullableString(Request $request, string $key): ?string
    {
        $value = trim((string) $request->request->get($key, ''));

        return $value === '' ? null : $value;
    }

    private function truncate(?string $value, int $maxLength = 255): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_strlen($value) > $maxLength ? mb_substr($value, 0, $maxLength - 1).'…' : $value;
    }

    private function uniqueSlug(string $baseSlug): string
    {
        $slug = $baseSlug;
        $suffix = 2;
        while ($this->em->getRepository(Medicine::class)->findOneBy(['slug' => $slug]) !== null) {
            $slug = $baseSlug.'-'.$suffix;
            ++$suffix;
        }

        return $slug;
    }
}
