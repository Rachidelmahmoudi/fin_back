<?php

declare(strict_types=1);

namespace App\Service\Storage;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Stocke sous public/uploads/{subdirectory}/{uuid}.{ext} — servi
 * directement par le serveur web comme n'importe quel asset statique
 * (aucune route Symfony dédiée nécessaire pour la lecture).
 *
 * Limites assumées pour ce lot (voir FileStorageInterface pour le plan de
 * remplacement) :
 * - Pas de redimensionnement/compression automatique — une photo prise
 *   directement au téléphone peut faire plusieurs Mo, stockée telle
 *   quelle. À ajouter avant la prod (ex: Intervention Image) si le volume
 *   de stockage devient un problème réel.
 * - Pas de scan antivirus/contenu — accepte tout fichier dont le type MIME
 *   déclaré est une image, sans inspection plus poussée du contenu binaire.
 */
final class LocalFileStorage implements FileStorageInterface
{
    private const MAX_SIZE_BYTES = 8 * 1024 * 1024; // 8 Mo — généreux pour une photo de téléphone
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/heic'];

    public function __construct(
        private readonly string $uploadsDir, // %kernel.project_dir%/public/uploads
        private readonly string $publicBaseUrl, // ex: https://finpharma.ma
        private readonly SluggerInterface $slugger,
    ) {
    }

    public function storeImage(UploadedFile $file, string $subdirectory): string
    {
        if (!\in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Type de fichier non autorisé (%s). Formats acceptés : JPEG, PNG, WebP, HEIC.',
                $file->getMimeType() ?? 'inconnu',
            ));
        }

        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            throw new \InvalidArgumentException('Le fichier dépasse la taille maximale autorisée (8 Mo).');
        }

        $subdirectory = trim($this->slugger->slug($subdirectory)->lower()->toString(), '/');
        $extension = $file->guessExtension() ?? 'bin';
        $filename = bin2hex(random_bytes(16)).'.'.$extension;

        $targetDir = rtrim($this->uploadsDir, '/').'/'.$subdirectory;
        $file->move($targetDir, $filename);

        return rtrim($this->publicBaseUrl, '/').'/uploads/'.$subdirectory.'/'.$filename;
    }
}
