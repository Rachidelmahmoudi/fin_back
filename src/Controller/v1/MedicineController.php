<?php

declare(strict_types=1);

namespace App\Controller\v1;

use App\Repository\MedicineRepository;
use App\Service\Seo\SeoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** §8/§9 : /medicaments, /medicaments/{slug}-{uid}. */
final class MedicineController extends AbstractController
{
    private const PAGE_SIZE = 24;

    public function __construct(
        private readonly MedicineRepository $medicineRepository,
        private readonly SeoService $seoService,
    ) {
    }

    #[Route('/{_locale}/medicaments', name: 'v1_medicine_list', requirements: ['_locale' => 'fr|ar'], methods: ['GET'])]
    public function list(string $_locale, Request $request): Response
    {
        $page = (int) $request->query->get('page', 1);

        $qb = $this->medicineRepository->createQueryBuilder('m')
            ->leftJoin('m.dci', 'd')->addSelect('d')
            ->andWhere('m.needsReview = false');

        if (($q = $request->query->get('q')) !== null) {
            $qb->andWhere('LOWER(m.name) LIKE :q OR LOWER(d.name) LIKE :q')->setParameter('q', '%'.mb_strtolower($q).'%');
        }
        if ($request->query->getBoolean('cnssReimbursed', false)) {
            $qb->andWhere('m.ppvCnss IS NOT NULL');
        }
        if (($form = $request->query->get('form')) !== null) {
            $qb->andWhere('m.form = :form')->setParameter('form', $form);
        }

        $qb->orderBy('m.name', 'ASC')->setFirstResult(($page - 1) * self::PAGE_SIZE)->setMaxResults(self::PAGE_SIZE);

        return $this->render('v1/medicine/list.html.twig', [
            'medicines' => $qb->getQuery()->getResult(),
            'seo' => $this->seoService->forMedicineList($_locale),
        ]);
    }

    #[Route('/{_locale}/medicaments/show/{slug}', name: 'v1_medicine_show', requirements: ['_locale' => 'fr|ar'], methods: ['GET'])]
    public function show(string $_locale, string $slug): Response
    {
        $uid = substr($slug, strrpos($slug, '-') + 1);
        $medicine = $this->medicineRepository->findOneBy(['uid' => $uid]);

        if ($medicine === null || $medicine->needsReview()) {
            throw $this->createNotFoundException();
        }

        return $this->render('v1/medicine/show.html.twig', [
            'medicine' => $medicine,
            'similar' => $this->similarMedicines($medicine),
            'seo' => $this->seoService->forMedicineShow($medicine, $_locale),
        ]);
    }

    /**
     * §detail-page (patron directory) : "médicaments similaires" en bas de
     * fiche — même DCI si connue (substituts/génériques), sinon même classe
     * thérapeutique. Exclut toujours needsReview (§Addendum §B).
     *
     * @return \App\Entity\Medicine[]
     */
    private function similarMedicines(\App\Entity\Medicine $medicine): array
    {
        $qb = $this->medicineRepository->createQueryBuilder('m')
            ->andWhere('m.id != :id')
            ->andWhere('m.needsReview = false')
            ->setParameter('id', $medicine->getId())
            ->setMaxResults(4);

        if ($medicine->getDci() !== null) {
            $qb->andWhere('m.dci = :dci')->setParameter('dci', $medicine->getDci());
        } elseif ($medicine->getTherapeuticClass() !== null) {
            $qb->andWhere('m.therapeuticClass = :class')->setParameter('class', $medicine->getTherapeuticClass());
        } else {
            return [];
        }

        return $qb->getQuery()->getResult();
    }
}
