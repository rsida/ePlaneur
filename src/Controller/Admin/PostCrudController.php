<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Content\EditableContentInterface;
use App\Content\PostPresenter;
use App\Controller\Admin\Filter\PublicationStateFilter;
use App\Entity\Post;
use App\Entity\User;
use App\Form\Admin\PostEditorType;
use App\Security\Permission;
use App\Security\Voter\PostVoter;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * News posts. The list is an EasyAdmin index; creating and editing a post open the block editor
 * (AbstractEditorCrudController), whose canvas shows the post with the site styles.
 *
 * Rights: POST_CREATE writes posts and edits one's own, POST_EDIT edits every post (PostVoter) and
 * chooses their author, POST_PUBLISH sets the publication date, POST_DELETE deletes.
 *
 * @extends AbstractEditorCrudController<Post>
 */
#[IsGranted(new Expression('is_granted("POST_CREATE") or is_granted("POST_EDIT")'))]
final class PostCrudController extends AbstractEditorCrudController
{
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
        return $filters
            ->add(PublicationStateFilter::new())
            ->add('category')
            ->add(BooleanFilter::new('featured', 'À la une'))
            ->add('publishedAt')
            ->add('visibility');
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('title', 'Titre');
        yield AssociationField::new('category', 'Catégorie');
        yield TextField::new('state', 'État')->setVirtual(true)->setSortable(false)->setTemplatePath('admin/field/post_state.html.twig');
        yield AssociationField::new('author', 'Auteur');
        yield DateTimeField::new('publishedAt', 'Publication');
        yield BooleanField::new('featured', 'À la une')->renderAsSwitch(false);
        yield ChoiceField::new('visibility', 'Visibilité')->renderAsBadges(VisibilityFields::BADGES);
    }

    /** Empty article layout loaded in the editor iframe; the editor fills it with render(). */
    #[AdminRoute('/canvas', name: 'canvas', options: ['methods' => ['GET']])]
    public function canvas(): Response
    {
        return $this->render('admin/editor/canvas_post.html.twig', ['zones' => $this->zones()]);
    }

    #[AdminRoute('/render', name: 'render', options: ['methods' => ['POST']])]
    public function renderCanvas(Request $request): JsonResponse
    {
        return $this->renderEditorCanvas($request);
    }

    /** The article page as readers would see it with the unsaved changes. */
    #[AdminRoute('/preview', name: 'preview', options: ['methods' => ['POST']])]
    public function preview(Request $request, PostPresenter $presenter): Response
    {
        $post = $this->editedContent($request, withZones: true);

        return $this->render('post/show.html.twig', ['article' => $presenter->present($post), 'preview' => true]);
    }

    protected function kind(): string
    {
        return 'post';
    }

    protected function zones(): array
    {
        return PostEditorType::ZONES;
    }

    protected function createEditorForm(EditableContentInterface $content): FormInterface
    {
        return $this->createForm(PostEditorType::class, $content, [
            'can_publish' => $this->canPublish(),
            'can_choose_author' => $this->isGranted(Permission::PostEdit->value),
        ]);
    }

    protected function canPublish(): bool
    {
        return $this->isGranted(Permission::PostPublish->value);
    }

    protected function denyWriteUnlessGranted(EditableContentInterface $content): void
    {
        $this->denyAccessUnlessGranted(PostVoter::WRITE, $content);
    }

    protected function savedMessages(): array
    {
        return [
            'saved' => 'Article enregistré.',
            'unpublished' => 'Article enregistré et dépublié : il redevient un brouillon.',
            'published' => 'Article publié.',
            'scheduled' => 'Article programmé : il paraîtra le %s.',
        ];
    }

    /**
     * @param Post $content
     */
    protected function canvasHeader(EditableContentInterface $content): string
    {
        return $this->renderView('post/_header.html.twig', ['post' => $content, 'readingMinutes' => PostPresenter::readingMinutes($content), 'editing' => true]);
    }

    protected function canvasUrl(EditableContentInterface $content): string
    {
        return $this->generateUrl('admin_post_canvas');
    }

    protected function pathPrefix(EditableContentInterface $content): string
    {
        return '/actualites/';
    }
}
