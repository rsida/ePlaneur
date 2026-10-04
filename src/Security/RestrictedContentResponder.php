<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Page;
use App\Entity\Post;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * "Contenu réservé" page (HTTP 403) shown instead of a page or post the reader may not see: the
 * content header (breadcrumb, title, lead) stays, the body is replaced by the access card. Visitors
 * get the login and registration actions, logged-in users learn who the content is for.
 */
final readonly class RestrictedContentResponder
{
    public function __construct(
        private Environment $twig,
        private Security $security,
    ) {
    }

    /**
     * @param Page|Post                  $content    the content asked for (title, lead, breadcrumb)
     * @param RestrictedContentInterface $restricted the content whose visibility refuses the reader
     *                                               (the content itself, or one of its ancestor pages)
     */
    public function respond(Page|Post $content, ?RestrictedContentInterface $restricted = null): Response
    {
        $restricted ??= $content;
        $groups = [];
        if (Visibility::Groups === $restricted->getVisibility()) {
            foreach ($restricted->getAllowedGroups() as $group) {
                $groups[] = $group->getName();
            }
        }

        return new Response($this->twig->render('security/restricted.html.twig', [
            'content' => $content,
            'visibility' => $restricted->getVisibility(),
            'groups' => $groups,
            'loggedIn' => null !== $this->security->getUser(),
        ]), Response::HTTP_FORBIDDEN);
    }
}
