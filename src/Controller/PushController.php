<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\PushSubscription;
use App\Entity\User;
use App\Push\PushEndpointInput;
use App\Push\PushMessage;
use App\Push\PushNotifier;
use App\Push\PushSubscriptionInput;
use App\Repository\PushSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Notifications of the installable site (roadmap 11l, doc/installable-site.md#notifications): a
 * device of the member turns them on or off, or tries them. Called by assets/controllers/push_controller.js
 * with the token "push" in the X-CSRF-Token header.
 */
#[Route('/notifications/appareils', name: 'app_push_')]
#[IsGranted('IS_AUTHENTICATED')]
final class PushController extends AbstractController
{
    public const string CSRF = 'push';

    public function __construct(
        private readonly PushSubscriptionRepository $subscriptions,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'subscribe', methods: ['POST'])]
    public function subscribe(Request $request, #[MapRequestPayload] PushSubscriptionInput $input, PushNotifier $notifier, ClockInterface $clock): Response
    {
        if (!$notifier->isEnabled() || !$this->tokenIsValid($request)) {
            return new Response(null, Response::HTTP_FORBIDDEN);
        }
        /** @var User $user */
        $user = $this->getUser();
        $device = $this->subscriptions->findOneBy(['endpoint' => $input->endpoint]);
        if (null !== $device) {
            $device->renew($user, $input->keys['p256dh'], $input->keys['auth']);
        } else {
            $this->entityManager->persist(new PushSubscription($user, $input->endpoint, $input->keys['p256dh'], $input->keys['auth'], $clock->now()));
        }
        $this->entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/retrait', name: 'unsubscribe', methods: ['POST'])]
    public function unsubscribe(Request $request, #[MapRequestPayload] PushEndpointInput $input): Response
    {
        if (!$this->tokenIsValid($request)) {
            return new Response(null, Response::HTTP_FORBIDDEN);
        }
        $device = $this->own($input);
        if (null !== $device) {
            $this->entityManager->remove($device);
            $this->entityManager->flush();
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/essai', name: 'try', methods: ['POST'])]
    public function try(Request $request, #[MapRequestPayload] PushEndpointInput $input, PushNotifier $notifier): Response
    {
        if (!$this->tokenIsValid($request)) {
            return new Response(null, Response::HTTP_FORBIDDEN);
        }
        $device = $this->own($input);
        if (null === $device) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }
        $reached = $notifier->send([$device], new PushMessage(
            'Club ePlaneur',
            'Les notifications fonctionnent sur cet appareil. À bientôt en vol !',
            $this->generateUrl('app_account_profile', ['_fragment' => 'notifications']),
            'essai',
        ));

        return new Response(null, 1 === $reached ? Response::HTTP_NO_CONTENT : Response::HTTP_BAD_GATEWAY);
    }

    private function own(PushEndpointInput $input): ?PushSubscription
    {
        return $this->subscriptions->findOneBy(['endpoint' => $input->endpoint, 'user' => $this->getUser()]);
    }

    private function tokenIsValid(Request $request): bool
    {
        return $this->isCsrfTokenValid(self::CSRF, (string) $request->headers->get('X-CSRF-Token'));
    }
}
