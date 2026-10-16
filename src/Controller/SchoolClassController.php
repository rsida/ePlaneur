<?php

declare(strict_types=1);

namespace App\Controller;

use App\Account\NameShown;
use App\Account\PublicNameCheck;
use App\Entity\SchoolClass;
use App\Entity\User;
use App\Form\Account\ProfileForms;
use App\Form\SchoolClassType;
use App\Repository\SchoolClassRepository;
use App\Security\Permission;
use App\Security\Voter\SchoolClassVoter;
use App\Training\ClassProgress;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Teacher space (roadmap 11f, doc/teacher-space.md): a teacher creates classes, gives their link to
 * the pupils and follows their progression; a pupil joins a class with its link and leaves it when
 * they want.
 */
final class SchoolClassController extends AbstractController
{
    public function __construct(
        private readonly SchoolClassRepository $classes,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** The classes of the teacher and a new one; the classes the account joined. */
    #[Route('/mon-compte/classes', name: 'app_account_classes', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function index(Request $request, ClockInterface $clock): Response
    {
        $user = $this->user();
        $form = null;
        if ($this->isGranted(Permission::ClassTeach->value)) {
            $class = new SchoolClass($user, '', $clock->now());
            $form = $this->createForm(SchoolClassType::class, $class)->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $this->entityManager->persist($class);
                $this->entityManager->flush();
                $this->addFlash('success', \sprintf('La classe « %s » est créée : donnez son lien à vos élèves.', $class->getName()));

                return $this->redirectToRoute('app_account_class', ['id' => $class->getId()]);
            }
        }

        return $this->render('account/classes.html.twig', [
            'section' => 'classes',
            'teaching' => null !== $form ? $this->classes->findOfTeacher($user) : [],
            'joined' => $this->classes->findOfPupil($user),
            'form' => $form,
        ], new Response(status: $form?->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /** The progression of the pupils of a class, its link and its settings. */
    #[Route('/mon-compte/classes/{id<\d+>}', name: 'app_account_class', methods: ['GET', 'POST'])]
    #[IsGranted(SchoolClassVoter::FOLLOW, 'class')]
    public function show(SchoolClass $class, Request $request, ClassProgress $progress): Response
    {
        $form = $this->createForm(SchoolClassType::class, $class, ['with_open' => true])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'La classe est enregistrée.');

            return $this->redirectToRoute('app_account_class', ['id' => $class->getId()]);
        }
        if ($form->isSubmitted()) {
            $this->entityManager->refresh($class);
        }

        return $this->render('account/class.html.twig', [
            'section' => 'classes',
            'class' => $class,
            'pupils' => $progress->of($class),
            'form' => $form,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /** A new link: the old one no longer lets anyone join. */
    #[Route('/mon-compte/classes/{id<\d+>}/nouveau-lien', name: 'app_account_class_code', methods: ['POST'])]
    #[IsGranted(SchoolClassVoter::FOLLOW, 'class')]
    public function renewCode(SchoolClass $class, Request $request): Response
    {
        $this->checkToken('class-code-'.$class->getId(), $request);
        $class->renewCode()->setOpen(true);
        $this->entityManager->flush();
        $this->addFlash('success', 'Le lien est changé : l’ancien ne permet plus de rejoindre la classe.');

        return $this->redirectToRoute('app_account_class', ['id' => $class->getId(), '_fragment' => 'lien']);
    }

    #[Route('/mon-compte/classes/{id<\d+>}/eleves/{pupil<\d+>}/retrait', name: 'app_account_class_remove', methods: ['POST'])]
    #[IsGranted(SchoolClassVoter::FOLLOW, 'class')]
    public function removePupil(SchoolClass $class, #[MapEntity(id: 'pupil')] User $pupil, Request $request): Response
    {
        $this->checkToken('class-remove-'.$class->getId().'-'.$pupil->getId(), $request);
        $class->removePupil($pupil);
        $this->entityManager->flush();
        $this->addFlash('success', \sprintf('%s n’est plus dans la classe.', $pupil->getDisplayName()));

        return $this->redirectToRoute('app_account_class', ['id' => $class->getId()]);
    }

    #[Route('/mon-compte/classes/{id<\d+>}/suppression', name: 'app_account_class_delete', methods: ['POST'])]
    #[IsGranted(SchoolClassVoter::FOLLOW, 'class')]
    public function delete(SchoolClass $class, Request $request): Response
    {
        $this->checkToken('class-delete-'.$class->getId(), $request);
        $this->entityManager->remove($class);
        $this->entityManager->flush();
        $this->addFlash('success', \sprintf('La classe « %s » est supprimée. Les comptes de ses élèves restent.', $class->getName()));

        return $this->redirectToRoute('app_account_classes');
    }

    /** The pupil leaves a class: their teacher no longer sees their progression. */
    #[Route('/mon-compte/classes/{id<\d+>}/quitter', name: 'app_account_class_leave', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function leave(SchoolClass $class, Request $request): Response
    {
        $this->checkToken('class-leave-'.$class->getId(), $request);
        $class->removePupil($this->user());
        $this->entityManager->flush();
        $this->addFlash('success', \sprintf('Vous avez quitté la classe « %s ».', $class->getName()));

        return $this->redirectToRoute('app_account_classes');
    }

    /**
     * The link given by the teacher: who the class is, what the teacher will see, and how the pupil
     * will be named to the others (first name or pseudonym, never their name).
     */
    #[Route('/classe/{code<[a-z0-9]{12}>}', name: 'app_class_join', methods: ['GET', 'POST'])]
    public function join(string $code, Request $request, ProfileForms $forms, PublicNameCheck $nameCheck): Response
    {
        $class = $this->classes->findOneBy(['code' => $code]);
        if (null === $class || !$class->isOpen()) {
            return $this->render('school_class/join.html.twig', ['class' => null, 'form' => null], new Response(status: Response::HTTP_NOT_FOUND));
        }
        $user = $this->getUser();
        if (!$user instanceof User || $class->getTeacher() === $user || $class->hasPupil($user)) {
            return $this->render('school_class/join.html.twig', ['class' => $class, 'form' => null]);
        }

        $form = $forms->classJoin([
            'nameShown' => NameShown::Name === $user->getNameShown() ? NameShown::FirstName : $user->getNameShown(),
            'pseudonym' => $user->getPseudonym(),
        ])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $pseudonym = trim((string) $data['pseudonym']);
            $errors = $nameCheck->errors($user, $user->getDisplayName(), $data['nameShown'], $pseudonym);
            foreach ($errors as $field => $error) {
                $form->get($field)->addError(new FormError($error));
            }
            if ([] === $errors) {
                $user->setNameShown($data['nameShown'])->setPseudonym($pseudonym)->setFlightsPublicByDefault(false);
                $class->addPupil($user);
                $this->entityManager->flush();
                $this->addFlash('success', \sprintf('Vous avez rejoint la classe « %s ».', $class->getName()));

                return $this->redirectToRoute('app_account_classes');
            }
        }

        return $this->render('school_class/join.html.twig', [
            'class' => $class,
            'form' => $form,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /** Logs in, then comes back to the link (the firewall keeps where to go back). */
    #[Route('/classe/{code<[a-z0-9]{12}>}/connexion', name: 'app_class_join_login', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function login(string $code): Response
    {
        return $this->redirectToRoute('app_class_join', ['code' => $code]);
    }

    private function checkToken(string $id, Request $request): void
    {
        if (!$this->isCsrfTokenValid($id, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
    }

    private function user(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
