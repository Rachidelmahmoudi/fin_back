<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\Storage\FileStorageInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Endpoint manquant depuis le début du projet, signalé à répétition
 * (formulaires établissement web/mobile, publication d'ordonnance mobile)
 * — chaque champ photo existait dans les formulaires mais n'avait rien à
 * appeler. Un seul endpoint générique plutôt qu'un par cas d'usage
 * (photo pharmacie, photo ordonnance...) : le `purpose` en paramètre
 * détermine juste le sous-dossier de rangement, la logique de stockage
 * est identique dans tous les cas.
 */
#[IsGranted('ROLE_USER')]
final class UploadController extends AbstractController
{
    private const ALLOWED_PURPOSES = ['establishment', 'prescription'];

    public function __construct(private readonly FileStorageInterface $fileStorage)
    {
    }

    #[Route('/api/v1/uploads/photo', name: 'api_uploads_photo', methods: ['POST'])]
    public function uploadPhoto(Request $request): JsonResponse
    {
        $purpose = (string) $request->request->get('purpose', 'establishment');
        if (!\in_array($purpose, self::ALLOWED_PURPOSES, true)) {
            return new JsonResponse(['error' => 'purpose doit être : '.implode(', ', self::ALLOWED_PURPOSES)], 422);
        }

        $file = $request->files->get('file');
        if ($file === null) {
            return new JsonResponse(['error' => 'Aucun fichier reçu (champ "file" attendu en multipart/form-data).'], 422);
        }

        try {
            $url = $this->fileStorage->storeImage($file, $purpose);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        return new JsonResponse(['url' => $url], 201);
    }
}
