<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Media;
use App\Media\MediaStorage;
use App\Security\Visibility;
use App\Security\Voter\ContentVoter;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serves uploaded files after checking who may see them. The name in the URL is cosmetic (readable
 * links and downloads); the id identifies the file.
 */
final class MediaController extends AbstractController
{
    /** Types a browser may display inline; anything else is always downloaded. */
    private const array INLINE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif', 'application/pdf'];

    #[Route('/media/{id<\d+>}/{name}', name: 'app_media', methods: ['GET'])]
    public function show(#[MapEntity(id: 'id')] Media $media, Request $request, MediaStorage $storage): BinaryFileResponse
    {
        // Public files skip the security check: reading the user would start the session and make
        // the response uncacheable
        $public = Visibility::Public === $media->getVisibility();
        if (!$public) {
            $this->denyAccessUnlessGranted(ContentVoter::VIEW, $media);
        }

        $path = $storage->pathOf($media);
        if (!is_file($path)) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', $media->getMimeType());
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        $inline = !$request->query->has('download') && \in_array($media->getMimeType(), self::INLINE_TYPES, true);
        $response->setContentDisposition(
            $inline ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $media->getOriginalName(),
            preg_replace('/[^\x20-\x7e]/', '_', $media->getOriginalName()) ?? 'file',
        );

        if ($public) {
            // A file never changes behind its id: browsers and proxies may keep it
            $response->setPublic();
            $response->setMaxAge(31536000);
            $response->headers->addCacheControlDirective('immutable');
        } else {
            $response->setPrivate();
            $response->headers->addCacheControlDirective('no-store');
        }

        return $response;
    }
}
