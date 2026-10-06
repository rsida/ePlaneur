<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Document;
use App\Entity\DocumentCategory;
use App\Entity\Media;
use App\Form\Admin\MediaUploadType;
use App\Media\MediaStorage;
use App\Repository\MediaRepository;
use App\Security\Permission;
use App\Twig\MediaExtension;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Data of the block editor: the media window (list, upload, alt text) and the choices of the settings
 * panel (official documents and their categories). Open to whoever may write posts or pages;
 * changing the media library needs MEDIA_MANAGE.
 */
#[Route('/admin/editor', name: 'admin_editor_')]
#[IsGranted(new Expression('is_granted("POST_CREATE") or is_granted("POST_EDIT") or is_granted("PAGE_MANAGE")'))]
final class EditorDataController extends AbstractController
{
    /** CSRF token id of the media window (upload, alt text): rendered in the editor page. */
    public const string MEDIA_TOKEN = 'editor_media';

    public function __construct(
        private readonly MediaExtension $mediaHelpers,
    ) {
    }

    /** Media shown per page of the media window. */
    private const int MEDIA_PAGE = 48;

    /**
     * Media of the library for the media window, newest first: `q` searches the name, alt text and
     * credit; `type` = image, pdf or other; `offset` pages through the results.
     */
    #[Route('/media', name: 'media', methods: ['GET'])]
    public function media(Request $request, MediaRepository $media): JsonResponse
    {
        $qb = $media->createQueryBuilder('media')->orderBy('media.uploadedAt', 'DESC')->addOrderBy('media.id', 'DESC');
        $query = trim($request->query->getString('q'));
        if ('' !== $query) {
            $qb->andWhere('media.originalName LIKE :query OR media.alt LIKE :query OR media.credit LIKE :query')
                ->setParameter('query', '%'.addcslashes($query, '%_').'%');
        }
        match ($request->query->getString('type')) {
            'image' => $qb->andWhere('media.mimeType LIKE :type')->setParameter('type', 'image/%'),
            'pdf' => $qb->andWhere('media.mimeType = :type')->setParameter('type', 'application/pdf'),
            'other' => $qb->andWhere('media.mimeType NOT LIKE :image AND media.mimeType != :pdf')->setParameter('image', 'image/%')->setParameter('pdf', 'application/pdf'),
            default => null,
        };

        $total = (int) (clone $qb)->select('COUNT(media.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();
        $offset = max(0, $request->query->getInt('offset'));
        /** @var list<Media> $items */
        $items = $qb->setFirstResult($offset)->setMaxResults(self::MEDIA_PAGE)->getQuery()->getResult();

        return $this->json([
            'items' => array_map($this->describeMedia(...), $items),
            'total' => $total,
            'more' => $offset + \count($items) < $total,
        ]);
    }

    /** One media, to show the current choice of a field. */
    #[Route('/media/{id<\d+>}', name: 'media_show', methods: ['GET'])]
    public function mediaShow(Media $media): JsonResponse
    {
        return $this->json($this->describeMedia($media));
    }

    /**
     * Upload from the media window (MEDIA_MANAGE): the files join the library, visible by everyone
     * until restricted in the media library.
     */
    #[Route('/media/upload', name: 'media_upload', methods: ['POST'])]
    #[IsGranted(Permission::MediaManage->value)]
    public function mediaUpload(Request $request, MediaStorage $storage, ValidatorInterface $validator, EntityManagerInterface $entityManager): JsonResponse
    {
        if (!$this->isCsrfTokenValid(self::MEDIA_TOKEN, $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['error' => 'La session a expiré : rechargez la page.'], Response::HTTP_FORBIDDEN);
        }

        $stored = [];
        $errors = [];
        $constraint = new Assert\File(maxSize: '20M', mimeTypes: MediaUploadType::MIME_TYPES, mimeTypesMessage: 'Ce type de fichier n’est pas accepté.', maxSizeMessage: 'Le fichier dépasse 20 Mo.');
        foreach ($request->files->all('files') as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }
            $violations = $validator->validate($file, $constraint);
            if (\count($violations) > 0) {
                $errors[] = \sprintf('%s : %s', $file->getClientOriginalName(), $violations[0]->getMessage());
                continue;
            }
            $media = $storage->storeUpload($file);
            $entityManager->persist($media);
            $stored[] = $media;
        }
        $entityManager->flush();

        return $this->json(['items' => array_map($this->describeMedia(...), $stored), 'errors' => $errors], [] === $stored && [] !== $errors ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
    }

    /** Alt text and credit, edited in the media window (MEDIA_MANAGE). */
    #[Route('/media/{id<\d+>}', name: 'media_update', methods: ['POST'])]
    #[IsGranted(Permission::MediaManage->value)]
    public function mediaUpdate(Media $media, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        if (!$this->isCsrfTokenValid(self::MEDIA_TOKEN, $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['error' => 'La session a expiré : rechargez la page.'], Response::HTTP_FORBIDDEN);
        }

        $text = static fn (string $name): ?string => '' === trim($request->request->getString($name)) ? null : mb_substr(trim($request->request->getString($name)), 0, 255);
        $media->setAlt($text('alt'))->setCredit($text('credit'));
        $entityManager->flush();

        return $this->json($this->describeMedia($media));
    }

    #[Route('/documents', name: 'documents', methods: ['GET'])]
    public function documents(EntityManagerInterface $entityManager): JsonResponse
    {
        $documents = $entityManager->getRepository(Document::class)->findBy([], ['title' => 'ASC']);

        return $this->json(array_map(static fn (Document $document): array => [
            'id' => $document->getId(),
            'label' => $document->getTitle().(null !== $document->getVersion() ? ' ('.$document->getVersion().')' : ''),
        ], $documents));
    }

    #[Route('/document-categories', name: 'document_categories', methods: ['GET'])]
    public function documentCategories(EntityManagerInterface $entityManager): JsonResponse
    {
        $categories = $entityManager->getRepository(DocumentCategory::class)->findBy([], ['position' => 'ASC']);

        return $this->json(array_map(static fn (DocumentCategory $category): array => [
            'id' => $category->getId(),
            'label' => $category->getName(),
        ], $categories));
    }

    /**
     * @return array{id: int|null, name: string, type: string, format: string, size: string, width: int|null, height: int|null, pages: int|null, uploadedAt: string, alt: string|null, credit: string|null, visibility: string, url: string|null}
     */
    private function describeMedia(Media $media): array
    {
        return [
            'id' => $media->getId(),
            'name' => $media->getOriginalName(),
            'type' => $media->isImage() ? 'image' : ($media->isPdf() ? 'pdf' : 'other'),
            'format' => $media->getFormat(),
            'size' => $this->mediaHelpers->fileSize($media->getSize()),
            'width' => $media->getWidth(),
            'height' => $media->getHeight(),
            'pages' => $media->getPages(),
            'uploadedAt' => $media->getUploadedAt()->setTimezone(new \DateTimeZone('Europe/Paris'))->format('d/m/Y'),
            'alt' => $media->getAlt(),
            'credit' => $media->getCredit(),
            'visibility' => $media->getVisibility()->label(),
            'url' => $media->isImage() ? $this->mediaHelpers->imageUrl($media, 'card') : null,
        ];
    }
}
