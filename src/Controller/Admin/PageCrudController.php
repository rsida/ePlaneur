<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Content\Editor\BlockSchema;
use App\Content\Editor\CanvasRenderer;
use App\Content\PagePresenter;
use App\Entity\Page;
use App\Form\Admin\PageEditorType;
use App\Repository\PageRepository;
use App\Security\Permission;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Exception\ForbiddenActionException;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Security\Permission as EaPermission;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Institutional pages. The list is the page tree (each page under its parent); creating and editing
 * a page open the block editor, like posts. A page with sub-pages cannot be deleted.
 *
 * @extends AbstractCrudController<Page>
 */
#[IsGranted(Permission::PageManage->value)]
final class PageCrudController extends AbstractCrudController
{
    /** Block zones of a page with their label in the editor. */
    private const array ZONES = ['body' => 'Corps', 'aside' => 'Colonne latérale'];

    public function __construct(
        private readonly BlockSchema $schema,
        private readonly CanvasRenderer $canvas,
        private readonly EntityManagerInterface $entityManager,
        private readonly PageRepository $pages,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Page::class;
    }

    public function createEntity(string $entityFqcn): Page
    {
        return new Page('', '');
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('page')
            ->setEntityLabelInPlural('Pages')
            ->setPageTitle(Crud::PAGE_NEW, 'Nouvelle page');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->disable(Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('title', 'Titre');
    }

    /** The page tree instead of a paginated table: the site has a few dozen pages. */
    public function index(AdminContext $context): Response
    {
        if (!$this->isGranted(EaPermission::EA_EXECUTE_ACTION, ['action' => Action::INDEX, 'entity' => null, 'entityFqcn' => Page::class])) {
            throw new ForbiddenActionException($context);
        }

        return $this->render('admin/page/tree.html.twig', ['tree' => $this->pages->findTree()]);
    }

    /** The editor replaces EasyAdmin's form pages; `?parent=12` creates a sub-page. */
    public function new(AdminContext $context): Response
    {
        $page = $this->createEntity(Page::class);
        $parentId = $context->getRequest()->query->getInt('parent');
        if (0 !== $parentId) {
            $page->setParent($this->pages->find($parentId));
        }

        return $this->editor($context->getRequest(), $page);
    }

    public function edit(AdminContext $context): Response
    {
        $page = $context->getEntity()->getInstance();
        \assert($page instanceof Page);

        return $this->editor($context->getRequest(), $page);
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance->getChildren()->isEmpty()) {
            $this->addFlash('danger', \sprintf('« %s » a des sous-pages : déplacez-les ou supprimez-les d’abord.', $entityInstance->getTitle()));

            return;
        }

        parent::deleteEntity($entityManager, $entityInstance);
    }

    /** Empty page layout loaded in the editor iframe: a section, or a sub-page of `parent`. */
    #[AdminRoute('/canvas', name: 'canvas', options: ['methods' => ['GET']])]
    public function canvas(Request $request): Response
    {
        $page = $this->pages->find($request->query->getInt('id'));
        $parent = $this->pages->find($request->query->getInt('parent'));

        return $this->render('admin/editor/canvas_page.html.twig', [
            'zones' => self::ZONES,
            'page' => $page,
            'parent' => $parent,
            'siblings' => $parent?->getChildren() ?? [],
        ]);
    }

    /** Header and blocks of the canvas for the current state of the editor. Nothing is saved. */
    #[AdminRoute('/render', name: 'render', options: ['methods' => ['POST']])]
    public function renderCanvas(Request $request): JsonResponse
    {
        $page = $this->editedPage($request);
        $this->createEditorForm($page)->submit($this->formValues($request), false);
        $children = array_values(array_filter($page->getChildren()->toArray(), static fn (Page $child): bool => $child->isPublished()));
        $highlight = $page->getHighlightSource();

        return new JsonResponse([
            ...$this->canvas->render(CanvasRenderer::decodeZones($request->request->getString('zones', '{}'), array_keys(self::ZONES)), ['pages' => $children]),
            'header' => $this->renderView('admin/editor/_page_header.html.twig', [
                'page' => $page,
                'highlight' => $highlight,
            ]),
        ]);
    }

    /** The page as readers would see it with the unsaved changes. */
    #[AdminRoute('/preview', name: 'preview', options: ['methods' => ['POST']])]
    public function preview(Request $request, PagePresenter $presenter): Response
    {
        $page = $this->editedPage($request);
        $this->createEditorForm($page)->handleRequest($request);

        return $this->render('page/show.html.twig', ['view' => $presenter->present($page), 'preview' => true]);
    }

    private function editor(Request $request, Page $page): Response
    {
        $wasPublishedAt = $page->getPublishedAt();
        $wasUpdatedOn = $page->getUpdatedAt()->format('Y-m-d');
        $form = $this->createEditorForm($page);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Saving dates the page, unless the editor chose another date
            if ($page->getUpdatedAt()->format('Y-m-d') === $wasUpdatedOn) {
                $page->setUpdatedAt(new \DateTimeImmutable());
            }
            if (null === $page->getId()) {
                $this->entityManager->persist($page);
            }
            $this->entityManager->flush();
            $this->addFlash('success', self::savedMessage($page, $wasPublishedAt));

            return $this->redirectToRoute('admin_page_edit', ['entityId' => $page->getId()]);
        }

        return $this->render('admin/editor/editor.html.twig', [
            'form' => $form,
            'kind' => 'page',
            'entity' => $page,
            'zones' => self::ZONES,
            'schema' => $this->schema->describe('page'),
            'can_publish' => true,
            'urls' => [
                'index' => $this->generateUrl('admin_page_index'),
                'canvas' => $this->generateUrl('admin_page_canvas', ['id' => $page->getId()]),
                'render' => $this->generateUrl('admin_page_render', ['id' => $page->getId()]),
                'preview' => $this->generateUrl('admin_page_preview', ['id' => $page->getId()]),
                'path_prefix' => '/'.(null !== $page->getParent() ? $page->getParent()->getPath().'/' : ''),
            ],
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * @return FormInterface<Page>
     */
    private function createEditorForm(Page $page): FormInterface
    {
        return $this->createForm(PageEditorType::class, $page);
    }

    private function editedPage(Request $request): Page
    {
        $id = $request->query->getInt('id');
        $page = 0 !== $id ? $this->pages->find($id) : $this->createEntity(Page::class);
        if (null === $page) {
            throw $this->createNotFoundException();
        }

        return $page;
    }

    /**
     * Form values posted by the editor, without the block zones (rendered from `zones`).
     *
     * @return array<string, mixed>
     */
    private function formValues(Request $request): array
    {
        $values = $request->request->all('page_editor');
        unset($values['body'], $values['aside'], $values['_token']);

        return $values;
    }

    private static function savedMessage(Page $page, ?\DateTimeImmutable $wasPublishedAt): string
    {
        $publishedAt = $page->getPublishedAt();
        if ($publishedAt == $wasPublishedAt) {
            return 'Page enregistrée.';
        }
        if (null === $publishedAt) {
            return 'Page enregistrée et dépubliée : elle redevient un brouillon.';
        }

        return $page->isPublished()
            ? 'Page publiée.'
            : \sprintf('Page programmée : elle paraîtra le %s.', $publishedAt->setTimezone(new \DateTimeZone('Europe/Paris'))->format('d/m/Y à H:i'));
    }
}
