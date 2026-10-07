<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Page;
use App\Entity\Post;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

/**
 * Answer to a reader who may not open a page or post. Announced content gets the "Contenu réservé"
 * page (HTTP 403): its header (breadcrumb, kicker, title) stays, the body is replaced by the access
 * card; visitors get the login and registration actions, logged-in users learn who it is for.
 * Private content is not found (404): it does not even reveal it exists.
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
     *
     * @throws NotFoundHttpException for private content
     */
    public function respond(Page|Post $content, ?RestrictedContentInterface $restricted = null): Response
    {
        $restricted ??= $content;
        if (!$restricted->isAnnounced()) {
            throw new NotFoundHttpException('Contenu introuvable.');
        }

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
