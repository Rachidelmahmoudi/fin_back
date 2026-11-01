<?php

declare(strict_types=1);

namespace App\Service\Wallet;

use App\Entity\User;
use App\Entity\Wallet;
use App\Entity\WalletTransaction;
use App\Entity\WalletTransactionType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Point d'entrée UNIQUE pour toute modification de solde (§27 du cahier des
 * charges). Aucun autre service ne doit appeler Wallet::_internalAdjust*()
 * directement.
 *
 * Concurrence : chaque opération tourne dans une transaction DB avec un
 * verrou pessimiste (SELECT ... FOR UPDATE) sur la ligne wallet, pour
 * garantir qu'un débit ne peut jamais faire passer le solde sous zéro même
 * si deux requêtes arrivent en même temps (ex: deux PrescriptionRequest
 * validées quasi simultanément par erreur de double-clic côté app).
 *
 * Contrainte DB complémentaire recommandée en migration réelle :
 *   ALTER TABLE wallet ADD CONSTRAINT chk_wallet_balance_non_negative
 *     CHECK (balance >= 0);
 * — défense en profondeur, au cas où un futur appel bypasserait ce service.
 */
final class WalletService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Connection $connection,
    ) {
    }

    public function getOrCreateWallet(User $owner): Wallet
    {
        $wallet = $this->em->getRepository(Wallet::class)->findOneBy(['owner' => $owner]);
        if ($wallet !== null) {
            return $wallet;
        }

        $wallet = new Wallet($owner);
        $this->em->persist($wallet);
        $this->em->flush();

        return $wallet;
    }

    /**
     * Rechargement (§27). Toujours un CREDIT réel sur balance.
     */
    public function credit(Wallet $wallet, string $amount, ?string $note = null): WalletTransaction
    {
        return $this->withLockedWallet($wallet, function (Wallet $locked) use ($amount, $note) {
            $locked->_internalAdjustBalance($amount);
            $tx = new WalletTransaction($locked, WalletTransactionType::CREDIT, $amount);
            $tx->setNote($note);
            $this->em->persist($tx);

            return $tx;
        });
    }

    /**
     * Réservation à l'acceptation d'une demande de déchiffrement (§Addendum 2).
     * N'affecte PAS balance, uniquement holdBalance. Échoue si le solde
     * disponible (balance - holdBalance déjà existant) est insuffisant.
     *
     * @throws \DomainException si le solde disponible est insuffisant
     */
    public function hold(Wallet $wallet, string $amount, string $relatedEntityType, string $relatedEntityId): WalletTransaction
    {
        return $this->withLockedWallet($wallet, function (Wallet $locked) use ($amount, $relatedEntityType, $relatedEntityId) {
            if (bccomp($locked->getSpendableBalance(), $amount, 2) < 0) {
                throw new \DomainException(sprintf(
                    'Solde disponible insuffisant pour réserver %s %s (disponible: %s).',
                    $amount,
                    $locked->getCurrency(),
                    $locked->getSpendableBalance(),
                ));
            }

            $locked->_internalAdjustHold($amount);
            $tx = new WalletTransaction($locked, WalletTransactionType::HOLD, $amount);
            $tx->setRelatedEntity($relatedEntityType, $relatedEntityId);
            $this->em->persist($tx);

            return $tx;
        });
    }

    /**
     * Libère un montant précédemment réservé par hold(), sans débit.
     * Utilisé sur EXPIRED/CANCELLED (aucune lecture n'a été validée) et,
     * partiellement, à la validation si le coût réel diffère de l'estimation
     * (cf. debitFromResult()).
     */
    public function release(Wallet $wallet, string $amount, string $relatedEntityType, string $relatedEntityId): WalletTransaction
    {
        return $this->withLockedWallet($wallet, function (Wallet $locked) use ($amount, $relatedEntityType, $relatedEntityId) {
            $locked->_internalAdjustHold(bcmul($amount, '-1', 2));
            $tx = new WalletTransaction($locked, WalletTransactionType::RELEASE, $amount);
            $tx->setRelatedEntity($relatedEntityType, $relatedEntityId);
            $this->em->persist($tx);

            return $tx;
        });
    }

    /**
     * Débit réel au moment de la validation du déchiffreur (§Addendum 2).
     * Libère intégralement le hold existant puis débite le montant EXACT
     * (nombre réel de médicaments identifiés x 1 DH), qui peut différer de
     * l'estimation réservée à l'acceptation.
     */
    public function debitFromResult(Wallet $wallet, string $heldAmount, string $finalAmount, string $relatedEntityId): WalletTransaction
    {
        return $this->withLockedWallet($wallet, function (Wallet $locked) use ($heldAmount, $finalAmount, $relatedEntityId) {
            $locked->_internalAdjustHold(bcmul($heldAmount, '-1', 2));

            // Défense en profondeur : même si le hold couvrait moins que le
            // coût final (ne devrait pas arriver si estimation correcte),
            // on ne débite jamais plus que le solde réel disponible.
            if (bccomp($locked->getBalance(), $finalAmount, 2) < 0) {
                throw new \DomainException('Solde insuffisant pour le débit final — situation anormale à investiguer.');
            }

            $locked->_internalAdjustBalance(bcmul($finalAmount, '-1', 2));
            $tx = new WalletTransaction($locked, WalletTransactionType::DEBIT, $finalAmount);
            $tx->setRelatedEntity('PrescriptionRequest', $relatedEntityId);
            $this->em->persist($tx);

            return $tx;
        });
    }

    public function hasMinimumBalance(Wallet $wallet, string $minimum): bool
    {
        return bccomp($wallet->getSpendableBalance(), $minimum, 2) >= 0;
    }

    /**
     * @template T
     * @param callable(Wallet): T $callback
     * @return T
     */
    private function withLockedWallet(Wallet $wallet, callable $callback): mixed
    {
        return $this->connection->transactional(function () use ($wallet, $callback) {
            // Verrou pessimiste : bloque toute autre transaction concurrente
            // sur cette même ligne wallet jusqu'au commit de celle-ci.
            $locked = $this->em->find(Wallet::class, $wallet->getId(), \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);

            $result = $callback($locked);
            $this->em->flush();

            return $result;
        });
    }
}
