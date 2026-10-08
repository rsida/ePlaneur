<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Content\EditableContentInterface;
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
use EasyCorp\Bundle\EasyAdminBundle\Exception\ForbiddenActionException;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Security\Permission as EaPermission;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Institutional pages. The list is the page tree (each page under its parent); creating and editing
 * a page open the block editor, like posts (AbstractEditorCrudController). A page with sub-pages
 * cannot be deleted.
 *
 * @extends AbstractEditorCrudController<Page>
 */
#[IsGranted(Permission::PageManage->value)]
final class PageCrudController extends AbstractEditorCrudController
{
    public function __construct(
        BlockSchema $schema,
        CanvasRenderer $canvas,
        EntityManagerInterface $entityManager,
        ClockInterface $clock,
        private readonly PageRepository $pages,
    ) {
        parent::__construct($schema, $canvas, $entityManager, $clock);
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
            'zones' => $this->zones(),
            'page' => $page,
            'parent' => $parent,
            'siblings' => $parent?->getChildren() ?? [],
        ]);
    }

    #[AdminRoute('/render', name: 'render', options: ['methods' => ['POST']])]
    public function renderCanvas(Request $request): JsonResponse
    {
        return $this->renderEditorCanvas($request);
    }

    /** The page as readers would see it with the unsaved changes. */
    #[AdminRoute('/preview', name: 'preview', options: ['methods' => ['POST']])]
    public function preview(Request $request, PagePresenter $presenter): Response
    {
        $page = $this->editedContent($request, withZones: true);

        return $this->render('page/show.html.twig', ['view' => $presenter->present($page), 'preview' => true]);
    }

    protected function kind(): string
    {
        return 'page';
    }

    protected function zones(): array
    {
        return PageEditorType::ZONES;
    }

    /** `?parent=12` creates a sub-page. */
    protected function newContent(Request $request): Page
    {
        $page = $this->createEntity(Page::class);
        $parentId = $request->query->getInt('parent');
        if (0 !== $parentId) {
            $page->setParent($this->pages->find($parentId));
        }

        return $page;
    }

    protected function createEditorForm(EditableContentInterface $content): FormInterface
    {
        return $this->createForm(PageEditorType::class, $content);
    }

    protected function savedMessages(): array
    {
        return [
            'saved' => 'Page enregistrée.',
            'unpublished' => 'Page enregistrée et dépubliée : elle redevient un brouillon.',
            'published' => 'Page publiée.',
            'scheduled' => 'Page programmée : elle paraîtra le %s.',
        ];
    }

    /**
     * @param Page $content
     */
    protected function canvasContext(EditableContentInterface $content): array
    {
        return ['pages' => array_values(array_filter($content->getChildren()->toArray(), static fn (Page $child): bool => $child->isPublished()))];
    }

    /**
     * @param Page $content
     */
    protected function canvasHeader(EditableContentInterface $content): string
    {
        return $this->renderView('admin/editor/_page_header.html.twig', ['page' => $content, 'highlight' => $content->getHighlightSource()]);
    }

    /**
     * @param Page $content
     */
    protected function canvasUrl(EditableContentInterface $content): string
    {
        return $this->generateUrl('admin_page_canvas', ['id' => $content->getId()]);
    }

    /**
     * @param Page $content
     */
    protected function pathPrefix(EditableContentInterface $content): string
    {
        return '/'.(null !== $content->getParent() ? $content->getParent()->getPath().'/' : '');
    }
}
