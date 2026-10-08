<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\MembershipRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Personal space of the logged-in user.
 */
#[IsGranted('ROLE_USER')]
final class AccountController extends AbstractController
{
    #[Route('/mon-compte', name: 'app_account', methods: ['GET'])]
    public function index(MembershipRepository $memberships): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $this->render('account/index.html.twig', ['membership' => $memberships->findByUser($user)[0] ?? null]);
    }
}
