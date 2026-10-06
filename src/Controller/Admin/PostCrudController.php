<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Content\Editor\BlockSchema;
use App\Content\Editor\CanvasRenderer;
use App\Content\PostPresenter;
use App\Entity\Post;
use App\Entity\User;
use App\Form\Admin\PostEditorType;
use App\Repository\PostRepository;
use App\Security\Permission;
use App\Security\Voter\PostVoter;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Exception\ForbiddenActionException;
use EasyCorp\Bundle\EasyAdminBundle\Exception\InsufficientEntityPermissionException;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Security\Permission as EaPermission;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * News posts. The list is an EasyAdmin index; creating and editing a post open the block editor
 * (templates/admin/editor/), whose canvas shows the post with the site styles.
 *
 * Rights: POST_CREATE writes posts and edits one's own, POST_EDIT edits every post (PostVoter),
 * POST_PUBLISH sets the publication date, POST_DELETE deletes.
 *
 * @extends AbstractCrudController<Post>
 */
#[IsGranted(new Expression('is_granted("POST_CREATE") or is_granted("POST_EDIT")'))]
final class PostCrudController extends AbstractCrudController
{
    /** Block zones of a post, in reading order, with their label in the editor. */
    private const array ZONES = ['body' => 'Corps (lecture)', 'aside' => 'Barre latérale', 'outro' => 'Bande finale · Pleine largeur'];

    public function __construct(
        private readonly BlockSchema $schema,
        private readonly CanvasRenderer $canvas,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Post::class;
    }

    public function createEntity(string $entityFqcn): Post
    {
        $post = new Post('', '');
        $user = $this->getUser();
        if ($user instanceof User) {
            $post->setAuthor($user);
        }

        return $post;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('article')
            ->setEntityLabelInPlural('Articles')
            ->setPageTitle(Crud::PAGE_NEW, 'Nouvel article')
            ->setEntityPermission(PostVoter::WRITE)
            ->setDefaultSort(['updatedAt' => 'DESC'])
            ->setSearchFields(['title', 'excerpt', 'slug']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $show = Action::new('show', 'Voir sur le site', 'external-link')
            ->linkToUrl(fn (Post $post): string => $this->generateUrl('app_post_show', ['slug' => $post->getSlug()]))
            ->setHtmlAttributes(['target' => '_blank']);

        return $actions
            ->add(Crud::PAGE_INDEX, $show)
            ->update(Crud::PAGE_INDEX, Action::NEW, static fn (Action $action): Action => $action->setLabel('Créer un article'))
            ->setPermission(Action::DELETE, Permission::PostDelete->value)
            ->setPermission(Action::BATCH_DELETE, Permission::PostDelete->value);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('category')->add('publishedAt')->add('visibility');
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('title', 'Titre');
        yield AssociationField::new('category', 'Catégorie');
        yield TextField::new('state', 'État')->setVirtual(true)->setSortable(false)->setTemplatePath('admin/field/post_state.html.twig');
        yield AssociationField::new('author', 'Auteur');
        yield DateTimeField::new('publishedAt', 'Publication');
        yield ChoiceField::new('visibility', 'Visibilité')->renderAsBadges(VisibilityFields::BADGES);
    }

    /** The editor replaces EasyAdmin's form pages. */
    public function new(AdminContext $context): Response
    {
        if (!$this->isGranted(EaPermission::EA_EXECUTE_ACTION, ['action' => Action::NEW, 'entity' => null, 'entityFqcn' => Post::class])) {
            throw new ForbiddenActionException($context);
        }

        return $this->editor($context->getRequest(), $this->createEntity(Post::class));
    }

    public function edit(AdminContext $context): Response
    {
        $entity = $context->getEntity();
        if (!$this->isGranted(EaPermission::EA_EXECUTE_ACTION, ['action' => Action::EDIT, 'entity' => $entity, 'entityFqcn' => Post::class])) {
            throw new ForbiddenActionException($context);
        }
        if (!$entity->isAccessible()) {
            throw new InsufficientEntityPermissionException($context);
        }
        $post = $entity->getInstance();
        \assert($post instanceof Post);

        return $this->editor($context->getRequest(), $post);
    }

    /** Empty article layout loaded in the editor iframe; the editor fills it with render(). */
    #[AdminRoute('/canvas', name: 'canvas', options: ['methods' => ['GET']])]
    public function canvas(): Response
    {
        return $this->render('admin/editor/canvas_post.html.twig', ['zones' => self::ZONES]);
    }

    /**
     * Header and blocks of the canvas for the current state of the editor (form fields and zones
     * sent as JSON). Nothing is saved.
     */
    #[AdminRoute('/render', name: 'render', options: ['methods' => ['POST']])]
    public function renderCanvas(Request $request, PostRepository $posts): JsonResponse
    {
        $post = $this->editedPost($request, $posts);
        $this->createEditorForm($post)->submit($this->formValues($request), false);
        $rendered = $this->canvas->render(CanvasRenderer::decodeZones($request->request->getString('zones', '{}'), array_keys(self::ZONES)));

        return new JsonResponse([
            ...$rendered,
            'header' => $this->renderView('post/_header.html.twig', ['post' => $post, 'readingMinutes' => PostPresenter::readingMinutes($post), 'editing' => true]),
        ]);
    }

    /** The article page as readers would see it with the unsaved changes. */
    #[AdminRoute('/preview', name: 'preview', options: ['methods' => ['POST']])]
    public function preview(Request $request, PostRepository $posts, PostPresenter $presenter): Response
    {
        $post = $this->editedPost($request, $posts);
        $this->createEditorForm($post)->handleRequest($request);

        return $this->render('post/show.html.twig', ['article' => $presenter->present($post), 'preview' => true]);
    }

    private function editor(Request $request, Post $post): Response
    {
        $wasPublished = $post->getPublishedAt();
        $form = $this->createEditorForm($post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $post->setUpdatedAt(new \DateTimeImmutable());
            if (null === $post->getId()) {
                $this->entityManager->persist($post);
            }
            $this->entityManager->flush();
            $this->addFlash('success', self::savedMessage($post, $wasPublished));

            return $this->redirectToRoute('admin_post_edit', ['entityId' => $post->getId()]);
        }

        return $this->render('admin/editor/editor.html.twig', [
            'form' => $form,
            'kind' => 'post',
            'entity' => $post,
            'zones' => self::ZONES,
            'schema' => $this->schema->describe('post'),
            'can_publish' => $this->isGranted(Permission::PostPublish->value),
            'urls' => [
                'index' => $this->generateUrl('admin_post_index'),
                'canvas' => $this->generateUrl('admin_post_canvas'),
                'render' => $this->generateUrl('admin_post_render', ['id' => $post->getId()]),
                'preview' => $this->generateUrl('admin_post_preview', ['id' => $post->getId()]),
                'path_prefix' => '/actualites/',
            ],
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * @return FormInterface<Post>
     */
    private function createEditorForm(Post $post): FormInterface
    {
        return $this->createForm(PostEditorType::class, $post, ['can_publish' => $this->isGranted(Permission::PostPublish->value)]);
    }

    private function editedPost(Request $request, PostRepository $posts): Post
    {
        $id = $request->query->getInt('id');
        $post = 0 !== $id ? $posts->find($id) : $this->createEntity(Post::class);
        if (null === $post) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted(PostVoter::WRITE, $post);

        return $post;
    }

    /**
     * Form values posted by the editor, without the block zones (rendered from `zones`).
     *
     * @return array<string, mixed>
     */
    private function formValues(Request $request): array
    {
        $values = $request->request->all('post_editor');
        unset($values['body'], $values['aside'], $values['outro'], $values['_token']);

        return $values;
    }

    private static function savedMessage(Post $post, ?\DateTimeImmutable $wasPublishedAt): string
    {
        $publishedAt = $post->getPublishedAt();
        if ($publishedAt == $wasPublishedAt) {
            return 'Article enregistré.';
        }
        if (null === $publishedAt) {
            return 'Article enregistré et dépublié : il redevient un brouillon.';
        }

        return $post->isPublished()
            ? 'Article publié.'
            : \sprintf('Article programmé : il paraîtra le %s.', $publishedAt->setTimezone(new \DateTimeZone('Europe/Paris'))->format('d/m/Y à H:i'));
    }
}
