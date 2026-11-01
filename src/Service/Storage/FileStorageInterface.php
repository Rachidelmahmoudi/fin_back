<?php

declare(strict_types=1);

namespace App\Service\Storage;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Même logique que AiProviderInterface (§25) : une abstraction remplaçable
 * plutôt qu'un couplage direct au stockage local. `LocalFileStorage` (ce
 * lot) écrit sur le disque du serveur — suffisant pour démarrer et pour du
 * développement, mais **ne scale pas** sur plusieurs instances de
 * l'application (chaque instance aurait son propre disque). Le jour où le
 * projet passe en multi-instance ou a besoin d'un vrai CDN, une
 * implémentation S3FileStorage (ou équivalent) remplace LocalFileStorage
 * par un seul changement de wiring dans services.yaml — aucun appelant
 * (UploadController, futurs endpoints) n'a besoin de changer.
 */
interface FileStorageInterface
{
    /**
     * @throws \InvalidArgumentException si le fichier n'est pas une image valide ou dépasse la taille max
     * @return string URL publique absolue du fichier stocké
     */
    public function storeImage(UploadedFile $file, string $subdirectory): string;
}
