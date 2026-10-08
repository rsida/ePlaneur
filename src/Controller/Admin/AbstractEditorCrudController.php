<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Content\EditableContentInterface;
use App\Content\Editor\BlockSchema;
use App\Content\Editor\CanvasRenderer;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Exception\ForbiddenActionException;
use EasyCorp\Bundle\EasyAdminBundle\Exception\InsufficientEntityPermissionException;
use EasyCorp\Bundle\EasyAdminBundle\Security\Permission as EaPermission;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Posts and pages: creating and editing open the block editor (templates/admin/editor/) instead of
 * EasyAdmin's form pages. The editor posts its state to `render` (canvas) and `preview` (the site
 * page with the unsaved changes); saving dates the content and tells what happened to its
 * publication.
 *
 * Each subclass declares its `canvas`, `render` and `preview` actions (#[AdminRoute]) and lets
 * them call renderEditorCanvas() and editedContent().
 *
 * @template T of EditableContentInterface
 *
 * @extends AbstractCrudController<T>
 */
abstract class AbstractEditorCrudController extends AbstractCrudController
{
    public function __construct(
        protected readonly BlockSchema $schema,
        protected readonly CanvasRenderer $canvas,
        protected readonly EntityManagerInterface $entityManager,
        protected readonly ClockInterface $clock,
    ) {
    }

    /** "post" or "page": block types, route names (admin_post_...) and editor template. */
    abstract protected function kind(): string;

    /**
     * Block zones of the content, in reading order, with their label in the editor.
     *
     * @return array<string, string>
     */
    abstract protected function zones(): array;

    /**
     * @param T $content
     *
     * @return FormInterface<T>
     */
    abstract protected function createEditorForm(EditableContentInterface $content): FormInterface;

    /**
     * Messages after a save: saved, unpublished (back to draft), published, scheduled (with "%s"
     * for the date).
     *
     * @return array{saved: string, unpublished: string, published: string, scheduled: string}
     */
    abstract protected function savedMessages(): array;

    /**
     * Header of the canvas (the content's header with the site styles).
     *
     * @param T $content
     */
    abstract protected function canvasHeader(EditableContentInterface $content): string;

    /**
     * Address of the empty layout loaded in the editor iframe.
     *
     * @param T $content
     */
    abstract protected function canvasUrl(EditableContentInterface $content): string;

    /**
     * Start of the content's address, shown before its slug.
     *
     * @param T $content
     */
    abstract protected function pathPrefix(EditableContentInterface $content): string;

    /** Whether the user may set the publication date. */
    protected function canPublish(): bool
    {
        return true;
    }

    /**
     * New content for the editor.
     *
     * @return T
     */
    protected function newContent(Request $request): EditableContentInterface
    {
        return $this->createEntity(static::getEntityFqcn());
    }

    /**
     * Throws when the user may not write this content (on top of the controller's own rights).
     *
     * @param T $content
     */
    protected function denyWriteUnlessGranted(EditableContentInterface $content): void
    {
    }

    /**
     * Data given to the blocks of the canvas (CanvasRenderer::render()).
     *
     * @param T $content
     *
     * @return array<string, mixed>
     */
    protected function canvasContext(EditableContentInterface $content): array
    {
        return [];
    }

    public function new(AdminContext $context): Response
    {
        if (!$this->isGranted(EaPermission::EA_EXECUTE_ACTION, ['action' => Action::NEW, 'entity' => null, 'entityFqcn' => static::getEntityFqcn()])) {
            throw new ForbiddenActionException($context);
        }

        return $this->editor($context->getRequest(), $this->newContent($context->getRequest()));
    }

    public function edit(AdminContext $context): Response
    {
        $entity = $context->getEntity();
        if (!$this->isGranted(EaPermission::EA_EXECUTE_ACTION, ['action' => Action::EDIT, 'entity' => $entity, 'entityFqcn' => static::getEntityFqcn()])) {
            throw new ForbiddenActionException($context);
        }
        if (!$entity->isAccessible()) {
            throw new InsufficientEntityPermissionException($context);
        }
        /** @var T $content */
        $content = $entity->getInstance();

        return $this->editor($context->getRequest(), $content);
    }

    /**
     * Header and blocks of the canvas for the current state of the editor (form fields and zones
     * sent as JSON). Nothing is saved.
     */
    protected function renderEditorCanvas(Request $request): JsonResponse
    {
        $content = $this->editedContent($request);
        $rendered = $this->canvas->render(CanvasRenderer::decodeZones($request->request->getString('zones', '{}'), array_keys($this->zones())), $this->canvasContext($content));

        return new JsonResponse([...$rendered, 'header' => $this->canvasHeader($content)]);
    }

    /**
     * Content of a `render` or `preview` request (`?id=`, or a new one) with the posted settings,
     * not saved. `render` sends the block zones apart (`zones`), `preview` in the form.
     *
     * @return T
     */
    protected function editedContent(Request $request, bool $withZones = false): EditableContentInterface
    {
        $id = $request->query->getInt('id');
        /** @var T|null $content */
        $content = 0 !== $id ? $this->entityManager->find(static::getEntityFqcn(), $id) : $this->newContent($request);
        if (null === $content) {
            throw $this->createNotFoundException();
        }
        $this->denyWriteUnlessGranted($content);

        $form = $this->createEditorForm($content);
        $values = $request->request->all($form->getName());
        foreach ([...$withZones ? [] : array_keys($this->zones()), '_token'] as $name) {
            unset($values[$name]);
        }
        $form->submit($values, false);

        return $content;
    }

    /**
     * @param T $content
     */
    private function editor(Request $request, EditableContentInterface $content): Response
    {
        $wasPublishedAt = $content->getPublishedAt();
        $wasUpdatedOn = $content->getUpdatedAt()->format('Y-m-d');
        $form = $this->createEditorForm($content);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Saving dates the content, unless the editor chose another date (pages)
            if ($content->getUpdatedAt()->format('Y-m-d') === $wasUpdatedOn) {
                $content->setUpdatedAt($this->clock->now());
            }
            if (null === $content->getId()) {
                $this->entityManager->persist($content);
            }
            $this->entityManager->flush();
            $this->addFlash('success', $this->savedMessage($content, $wasPublishedAt));

            return $this->redirectToRoute(\sprintf('admin_%s_edit', $this->kind()), ['entityId' => $content->getId()]);
        }

        $route = fn (string $action): string => \sprintf('admin_%s_%s', $this->kind(), $action);

        return $this->render('admin/editor/editor.html.twig', [
            'form' => $form,
            'kind' => $this->kind(),
            'entity' => $content,
            'zones' => $this->zones(),
            'schema' => $this->schema->describe($this->kind()),
            'can_publish' => $this->canPublish(),
            'urls' => [
                'index' => $this->generateUrl($route('index')),
                'canvas' => $this->canvasUrl($content),
                'render' => $this->generateUrl($route('render'), ['id' => $content->getId()]),
                'preview' => $this->generateUrl($route('preview'), ['id' => $content->getId()]),
                'path_prefix' => $this->pathPrefix($content),
            ],
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    private function savedMessage(EditableContentInterface $content, ?\DateTimeImmutable $wasPublishedAt): string
    {
        $messages = $this->savedMessages();
        $publishedAt = $content->getPublishedAt();

        return match (true) {
            $publishedAt == $wasPublishedAt => $messages['saved'],
            null === $publishedAt => $messages['unpublished'],
            $content->isPublished($this->clock->now()) => $messages['published'],
            default => \sprintf($messages['scheduled'], $publishedAt->format('d/m/Y à H:i')),
        };
    }
}
