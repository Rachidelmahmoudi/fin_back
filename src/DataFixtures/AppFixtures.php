<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\City;
use App\Entity\Dci;
use App\Entity\Doctor;
use App\Entity\DoctorOpeningHour;
use App\Entity\EstablishmentStatus;
use App\Entity\LabTest;
use App\Entity\Laboratory;
use App\Entity\LaboratoryOpeningHour;
use App\Entity\Medicine;
use App\Entity\Pharmacy;
use App\Entity\PharmacyDutyPeriod;
use App\Entity\PharmacyOpeningHour;
use App\Entity\PharmacyStatus;
use App\Entity\PrescriptionRequest;
use App\Entity\PrescriptionRequestStatus;
use App\Entity\Specialty;
use App\Entity\User;
use App\Entity\WalletRechargeMethod;
use App\Entity\WalletRechargeRequest;
use App\Service\Wallet\WalletService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Données de test — NE JAMAIS charger en production (voir README_FIXTURES.md).
 *
 * Couvre volontairement chaque rôle et chaque statut de modération, pour
 * pouvoir tester l'app mobile ET le back-office admin sans rien créer à la
 * main : un compte par rôle, une pharmacie/un médecin en attente pour
 * tester l'approbation admin, une recharge wallet en attente, une demande
 * de déchiffrement déjà publiée pour tester le flux déchiffreur.
 *
 * Mot de passe UNIQUE pour tous les comptes de test, en clair ici
 * volontairement (fixtures = données jetables, jamais en prod) :
 *
 *     Test1234!
 *
 * Lancer avec : bin/console doctrine:fixtures:load
 * (⚠️ purge toute la base — jamais sur une base contenant de vraies données)
 */
final class AppFixtures extends Fixture
{
    private const PASSWORD = 'Test1234!';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly WalletService $walletService,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // ---------- Villes (region = City.region, utilisé par le ciblage régional) ----------
        $casablanca = new City('Casablanca', 'casablanca');
        $casablanca->setRegion('Casablanca-Settat');
        $casablanca->setLat('33.5731');
        $casablanca->setLng('-7.5898');
        $casablanca->setIsActive(true);

        $rabat = new City('Rabat', 'rabat');
        $rabat->setRegion('Rabat-Salé-Kénitra');
        $rabat->setLat('34.0209');
        $rabat->setLng('-6.8416');
        $rabat->setIsActive(true);

        $marrakech = new City('Marrakech', 'marrakech');
        $marrakech->setRegion('Marrakech-Safi');
        $marrakech->setLat('31.6295');
        $marrakech->setLng('-7.9811');
        $marrakech->setIsActive(true);

        foreach ([$casablanca, $rabat, $marrakech] as $city) {
            $manager->persist($city);
        }

        // ---------- Spécialités ----------
        $cardiologie = new Specialty('Cardiologie', 'cardiologie');
        $generaliste = new Specialty('Médecine générale', 'medecine-generale');
        $pediatrie = new Specialty('Pédiatrie', 'pediatrie');
        foreach ([$cardiologie, $generaliste, $pediatrie] as $specialty) {
            $manager->persist($specialty);
        }

        // ================================================================
        // UTILISATEURS — un par rôle, mot de passe Test1234! pour tous
        // ================================================================

        $admin = $this->makeUser($manager, 'admin@finpharma.test', ['ROLE_ADMIN']);

        $patient = $this->makeUser($manager, 'patient@finpharma.test', ['ROLE_USER']);

        // Pharmacien avec pharmacie APPROUVÉE (ACTIVE) — pour tester l'app normalement
        $pharmacistActive = $this->makeUser($manager, 'pharmacien@finpharma.test', ['ROLE_PHARMACIST']);

        // Pharmacien avec pharmacie EN ATTENTE — pour tester l'écran d'approbation admin
        $pharmacistPending = $this->makeUser($manager, 'pharmacien.attente@finpharma.test', ['ROLE_USER']);

        // Médecin APPROUVÉ
        $doctorActive = $this->makeUser($manager, 'medecin@finpharma.test', ['ROLE_DOCTOR']);

        // Médecin EN ATTENTE — pour tester l'admin
        $doctorPending = $this->makeUser($manager, 'medecin.attente@finpharma.test', ['ROLE_USER']);

        // Laboratoire d'analyses (approuvé)
        $labOwner = $this->makeUser($manager, 'laboratoire@finpharma.test', ['ROLE_LAB_OWNER']);

        // Déchiffreur — avec du solde côté wallet du pharmacien pour tester tout le flux
        $decoder = $this->makeUser($manager, 'dechiffreur@finpharma.test', ['ROLE_DECODER']);

        $manager->flush(); // les User doivent avoir un id avant d'être référencés ailleurs

        // ================================================================
        // PHARMACIES
        // ================================================================

        // #1 — ACTIVE, DE GARDE MAINTENANT (visible immédiatement dans l'app mobile)
        $pharmacyDuty = new Pharmacy('a1b2c3', 'pharmacie-atlas-marrakech', 'Pharmacie Atlas', 'Avenue Mohammed VI, Guéliz', $marrakech);
        $pharmacyDuty->setLat('31.6350')->setLng('-8.0089');
        $pharmacyDuty->setPhone('+212524000001')->setEmail('contact@pharmacie-atlas.test');
        $pharmacyDuty->setStatus(PharmacyStatus::ACTIVE);
        $pharmacyDuty->setOwner($pharmacistActive);
        $pharmacyDuty->markVerified($admin);
        $pharmacyDuty->setIsOnDutyNow(true); // pour un affichage immédiat sans attendre le cron de recalcul
        $manager->persist($pharmacyDuty);
        $manager->persist(new PharmacyDutyPeriod($pharmacyDuty, new \DateTimeImmutable('-1 hour'), new \DateTimeImmutable('+7 hours')));
        $manager->persist(new PharmacyOpeningHour($pharmacyDuty, 1, new \DateTimeImmutable('08:30'), new \DateTimeImmutable('22:00')));

        // #2 — ACTIVE, ouverte aux horaires normaux (pas de garde)
        $pharmacyOpen = new Pharmacy('d4e5f6', 'pharmacie-centrale-casablanca', 'Pharmacie Centrale', 'Boulevard Zerktouni', $casablanca);
        $pharmacyOpen->setLat('33.5892')->setLng('-7.6114');
        $pharmacyOpen->setPhone('+212522000002');
        $pharmacyOpen->setStatus(PharmacyStatus::ACTIVE);
        $pharmacyOpen->markVerified($admin);
        $pharmacyOpen->setIsOpenNow(true);
        $manager->persist($pharmacyOpen);
        $manager->persist(new PharmacyOpeningHour($pharmacyOpen, 1, new \DateTimeImmutable('08:00'), new \DateTimeImmutable('20:00')));

        // #3 — EN ATTENTE — pour tester GET /admin/pharmacies + approbation
        $pharmacyPending = new Pharmacy('g7h8i9', 'pharmacie-najah-rabat', 'Pharmacie Najah', 'Avenue Allal Ben Abdellah', $rabat);
        $pharmacyPending->setLat('34.0150')->setLng('-6.8326');
        $pharmacyPending->setPhone('+212537000003');
        $pharmacyPending->setStatus(PharmacyStatus::PENDING);
        $pharmacyPending->setOwner($pharmacistPending);
        $manager->persist($pharmacyPending);

        // ================================================================
        // MÉDECINS
        // ================================================================

        $doctorPublished = new Doctor('j1k2l3', 'dr-amine-alaoui', 'Dr. Amine Alaoui', 'Rue Ibn Sina, Guéliz', $marrakech);
        $doctorPublished->setLat('31.6339')->setLng('-8.0125');
        $doctorPublished->setPhone('+212524000004');
        $doctorPublished->setConsultationPrice('300');
        $doctorPublished->setBio('Médecin généraliste, 12 ans d\'expérience.');
        $doctorPublished->addSpecialty($generaliste);
        $doctorPublished->setOwner($doctorActive);
        $doctorPublished->setStatus(EstablishmentStatus::PUBLISHED);
        $doctorPublished->markVerified($admin);
        $manager->persist($doctorPublished);
        $manager->persist(new DoctorOpeningHour($doctorPublished, 1, new \DateTimeImmutable('09:00'), new \DateTimeImmutable('17:00')));

        $doctorReview = new Doctor('m4n5o6', 'dr-salma-idrissi', 'Dr. Salma Idrissi', 'Avenue Hassan II', $casablanca);
        $doctorReview->setLat('33.5950')->setLng('-7.6187');
        $doctorReview->addSpecialty($cardiologie);
        $doctorReview->setOwner($doctorPending);
        $doctorReview->setStatus(EstablishmentStatus::PENDING_REVIEW);
        $manager->persist($doctorReview);

        // ================================================================
        // LABORATOIRE D'ANALYSES
        // ================================================================

        $lab = new Laboratory('p7q8r9', 'labo-santeplus-marrakech', 'Labo SantéPlus', 'Avenue Mohammed V', $marrakech);
        $lab->setLat('31.6280')->setLng('-7.9908');
        $lab->setPhone('+212524000005');
        $lab->setOwner($labOwner);
        $lab->setStatus(EstablishmentStatus::PUBLISHED);
        $lab->markVerified($admin);
        $manager->persist($lab);
        $manager->persist(new LaboratoryOpeningHour($lab, 1, new \DateTimeImmutable('07:30'), new \DateTimeImmutable('18:00')));
        $manager->persist(new LabTest($lab, 'Numération formule sanguine (NFS)'));
        $manager->persist(new LabTest($lab, 'Glycémie à jeun'));

        // ================================================================
        // MÉDICAMENTS
        // ================================================================

        // $paracetamolDci = new Dci('Paracétamol', 'paracetamol');
        // $ibuprofeneDci = new Dci('Ibuprofène', 'ibuprofene');
        // $manager->persist($paracetamolDci);
        // $manager->persist($ibuprofeneDci);

        // $doliprane = new Medicine('s1t2u3', 'doliprane-1000mg', 'Doliprane 1000mg');
        // $doliprane->setDci($paracetamolDci);
        // $doliprane->setDosageValue('1000')->setDosageUnit('mg');
        // $doliprane->setForm('Comprimé')->setPresentation('Boîte de 8');
        // $doliprane->setIndications('Douleurs, fièvre.');
        // $doliprane->setContraindications('Insuffisance hépatique sévère.');
        // $doliprane->setTherapeuticClass('Antalgique / Antipyrétique');
        // $manager->persist($doliprane);

        // $advil = new Medicine('v4w5x6', 'advil-400mg', 'Advil 400mg');
        // $advil->setDci($ibuprofeneDci);
        // $advil->setDosageValue('400')->setDosageUnit('mg');
        // $advil->setForm('Comprimé pelliculé')->setPresentation('Boîte de 20');
        // $advil->setIndications('Douleurs, inflammations.');
        // $advil->setTherapeuticClass('Anti-inflammatoire non stéroïdien');
        // $manager->persist($advil);

        // $manager->flush(); // Pharmacy/Doctor/Laboratory doivent avoir un id avant les entités qui les référencent ci-dessous

        // ================================================================
        // WALLET — solde côté pharmacien pour tester le déchiffrement
        // ================================================================

        $pharmacistWallet = $this->walletService->getOrCreateWallet($pharmacistActive);
        $this->walletService->credit($pharmacistWallet, '20.00', 'Solde de test (fixtures)');

        // Recharge EN ATTENTE — pour tester /admin/wallet/recharges
        $rechargeRequest = new WalletRechargeRequest($pharmacistActive, WalletRechargeMethod::BANK_TRANSFER, '50.00');
        $rechargeRequest->setReference('VIR-TEST-001');
        $manager->persist($rechargeRequest);

        // ================================================================
        // DEMANDE DE DÉCHIFFREMENT déjà PUBLIÉE — le compte déchiffreur peut
        // l'accepter immédiatement en ouvrant l'app mobile (Déchiffrer -> Demandes disponibles).
        // ================================================================

        $prescription = new PrescriptionRequest(
            $pharmacistActive,
            'https://placehold.co/600x800/025FAC/FFFFFF?text=Ordonnance+Test',
            3,
        );
        $prescription->setStatus(PrescriptionRequestStatus::PUBLISHED);
        $manager->persist($prescription);

        $manager->flush();

        $this->printSummary();
    }

    /** @param string[] $roles */
    private function makeUser(ObjectManager $manager, string $email, array $roles): User
    {
        $user = new User($email);
        $user->setPassword($this->passwordHasher->hashPassword($user, self::PASSWORD));
        foreach ($roles as $role) {
            $user->addRole($role);
        }
        $manager->persist($user);

        return $user;
    }

    private function printSummary(): void
    {
        $pw = self::PASSWORD;
        echo <<<TXT

        ================================================================
         Comptes de test créés — mot de passe pour TOUS : {$pw}
        ================================================================
         admin@finpharma.test              ROLE_ADMIN     (back-office /admin)
         patient@finpharma.test            ROLE_USER      (aucune fonctionnalité pro)
         pharmacien@finpharma.test         ROLE_PHARMACIST (pharmacie ACTIVE + garde + solde wallet 20 DH)
         pharmacien.attente@finpharma.test ROLE_USER      (pharmacie EN ATTENTE -> à approuver dans /admin)
         medecin@finpharma.test            ROLE_DOCTOR    (fiche PUBLIÉE + vérifiée)
         medecin.attente@finpharma.test    ROLE_USER      (fiche EN ATTENTE -> à approuver dans /admin)
         laboratoire@finpharma.test        ROLE_LAB_OWNER (labo PUBLIÉ, 2 analyses)
         dechiffreur@finpharma.test        ROLE_DECODER   (1 demande PUBLIÉE déjà disponible à accepter)
        ================================================================
        Recharge wallet EN ATTENTE (pharmacien@finpharma.test, 50 DH virement)
        -> à confirmer/rejeter dans /admin/wallet/recharges
        ================================================================

        TXT;
    }
}
