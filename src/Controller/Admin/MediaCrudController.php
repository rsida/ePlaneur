<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Document;
use App\Entity\Group;
use App\Entity\Media;
use App\Entity\Post;
use App\Form\Admin\MediaUploadType;
use App\Media\MediaStorage;
use App\Security\Permission;
use App\Security\Visibility;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Media library: files are added with the "Téléverser" page (several at once), then described (alt
 * text, credit) and restricted here. A file used by a document or a post cover cannot be deleted.
 *
 * @extends AbstractCrudController<Media>
 */
#[IsGranted(Permission::MediaManage->value)]
final class MediaCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly MediaStorage $storage,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Media::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('média')
            ->setEntityLabelInPlural('Médiathèque')
            ->setDefaultSort(['uploadedAt' => 'DESC'])
            ->setSearchFields(['originalName', 'alt', 'credit']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $upload = Action::new('upload', 'Téléverser', 'upload')
            ->linkToCrudAction('upload')
            ->createAsGlobalAction()
            ->asPrimaryAction();

        return $actions
            ->disable(Action::NEW)
            ->add(Crud::PAGE_INDEX, $upload);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('mimeType')->add('visibility');
    }

    public function configureFields(string $pageName): iterable
    {
        // Index: thumbnail and name in one column, short format, date without time (Figma "Médiathèque")
        yield TextField::new('originalName', 'Nom du fichier')->setTemplatePath('admin/field/media_name.html.twig')->onlyOnIndex();
        yield TextField::new('format', 'Type')->setSortable(false)->onlyOnIndex();
        yield FormField::addFieldset('Fichier', 'files');
        yield TextField::new('originalName', 'Nom')->setDisabled()->hideOnIndex();
        yield TextField::new('mimeType', 'Type')->onlyOnDetail();
        yield IntegerField::new('size', 'Taille')->setTemplatePath('admin/field/file_size.html.twig')->hideOnForm();
        yield DateTimeField::new('uploadedAt', 'Date d’ajout')->setFormat('dd/MM/yyyy')->hideOnForm();
        yield TextField::new('alt', 'Texte alternatif')
            ->setHelp('Décrit l’image pour les personnes qui ne la voient pas. Laisser vide pour une image décorative.')
            ->hideOnIndex();
        yield TextField::new('credit', 'Crédit')->hideOnIndex();
        yield from VisibilityFields::create();
    }

    #[AdminRoute('/upload', name: 'upload')]
    public function upload(Request $request, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(MediaUploadType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var list<UploadedFile> $files */
            $files = $form->get('files')->getData();
            /** @var Visibility $visibility */
            $visibility = $form->get('visibility')->getData();
            /** @var iterable<Group> $groups */
            $groups = $form->get('allowedGroups')->getData();

            foreach ($files as $file) {
                $media = $this->storage->storeUpload($file)->setVisibility($visibility);
                foreach ($groups as $group) {
                    $media->addAllowedGroup($group);
                }
                $entityManager->persist($media);
            }
            $entityManager->flush();
            $this->addFlash('success', \sprintf('%d fichier(s) ajouté(s) à la médiathèque.', \count($files)));

            return $this->redirectToRoute('admin_media_index');
        }

        return $this->render('admin/media/upload.html.twig', ['form' => $form]);
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $usedBy = $entityManager->getRepository(Document::class)->count(['file' => $entityInstance])
            + $entityManager->getRepository(Post::class)->count(['cover' => $entityInstance]);
        if ($usedBy > 0) {
            $this->addFlash('danger', \sprintf('« %s » est utilisé par un document officiel ou comme couverture d’article : retirez-le d’abord.', $entityInstance->getOriginalName()));

            return;
        }

        parent::deleteEntity($entityManager, $entityInstance);
        $this->storage->delete($entityInstance);
    }
}
