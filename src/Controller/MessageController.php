<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\FlightPlan;
use App\Entity\Message;
use App\Entity\User;
use App\Form\ConversationType;
use App\Form\MessageType;
use App\Messaging\Conversations;
use App\Repository\ConversationRepository;
use App\Repository\FlightRepository;
use App\Security\Permission;
use App\Security\Voter\ConversationVoter;
use App\Security\Voter\FlightVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Messages of the member space (roadmap step 9c): members write to each other, to the committee or
 * to the administrators (MESSAGE_SEND), possibly about a flight; the readers of a conversation get
 * an e-mail for each new message.
 */
#[Route('/mon-compte/messages', name: 'app_account_message')]
#[IsGranted('ROLE_USER')]
final class MessageController extends AbstractController
{
    #[Route('', name: 's', methods: ['GET'])]
    public function index(ConversationRepository $conversations): Response
    {
        $user = $this->user();

        return $this->render('account/messages/index.html.twig', [
            'conversations' => $conversations->findFor($user),
        ]);
    }

    /** A new conversation; `?a=<user id>` picks the member to write to, `?vol=<flight id>` the flight it is about. */
    #[Route('/nouveau', name: '_new', methods: ['GET', 'POST'])]
    #[IsGranted(Permission::MessageSend->value)]
    public function new(Request $request, Conversations $conversations, FlightRepository $flights, RateLimiterFactoryInterface $messageLimiter): Response
    {
        $user = $this->user();
        $groups = $conversations->groups();
        $members = $conversations->correspondents($user);
        $recipients = [];
        foreach ($groups as $code => $group) {
            $recipients['Le club'][Conversations::GROUPS[$code]] = 'group:'.$code;
        }
        foreach ($members as $member) {
            $recipients['Membres'][$member->getDisplayName().(null !== $member->getCondorCn() ? ' ('.$member->getCondorCn().')' : '')] = 'user:'.$member->getId();
        }
        $flight = $request->query->has('vol') ? $flights->find($request->query->getInt('vol')) : null;
        if (null !== $flight && !$this->isGranted(FlightVoter::VIEW, $flight)) {
            $flight = null;
        }
        $data = [
            'recipient' => $request->query->has('a') ? 'user:'.$request->query->getInt('a') : null,
            'subject' => null !== $flight ? \sprintf('Votre vol %s', FlightPlan::codeIn($flight->getFlightPlan()) ?? $flight->getFlightPlan() ?? $flight->getOriginalName()) : null,
            'body' => null,
        ];
        $form = $this->createForm(ConversationType::class, $data, ['recipients' => $recipients]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid() && $this->allowed($form, $messageLimiter, $user)) {
            /** @var array{recipient: string, subject: string, body: string} $values */
            $values = $form->getData();
            [$kind, $key] = explode(':', $values['recipient'], 2);
            $recipient = 'group' === $kind ? $groups[$key] : array_find($members, static fn (User $member): bool => (string) $member->getId() === $key);
            if (null !== $recipient) {
                $conversation = $conversations->start($user, $recipient, $values['subject'], $values['body'], $flight);
                $this->addFlash('info', 'Message envoyé.');

                return $this->redirectToRoute('app_account_message_show', ['id' => $conversation->getId()]);
            }
        }

        return $this->render('account/messages/new.html.twig', [
            'form' => $form,
            'flight' => $flight,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id<\d+>}', name: '_show', methods: ['GET', 'POST'])]
    #[IsGranted(ConversationVoter::VIEW, 'conversation')]
    public function show(Conversation $conversation, Request $request, Conversations $conversations, ClockInterface $clock, RateLimiterFactoryInterface $messageLimiter): Response
    {
        $user = $this->user();
        $form = null;
        if ($this->isGranted(ConversationVoter::REPLY, $conversation)) {
            $form = $this->createForm(MessageType::class, new Message($conversation, $user, $clock->now()));
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid() && $this->allowed($form, $messageLimiter, $user)) {
                $conversations->reply($conversation, $user, $form->getData()->getBody());

                return $this->redirectToRoute('app_account_message_show', ['id' => $conversation->getId(), '_fragment' => 'dernier']);
            }
        }
        $unread = $conversation->isUnreadFor($user);
        if (!$request->isMethod('POST')) {
            $conversations->markRead($conversation, $user);
        }

        return $this->render('account/messages/show.html.twig', [
            'conversation' => $conversation,
            'form' => $form,
            'was_unread' => $unread,
        ], new Response(status: null !== $form && $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * Twenty messages an hour per member.
     *
     * @param FormInterface<mixed> $form
     */
    private function allowed(FormInterface $form, RateLimiterFactoryInterface $limiter, User $user): bool
    {
        if ($limiter->create('user-'.$user->getId())->consume()->isAccepted()) {
            return true;
        }
        $form->addError(new FormError('Vous avez envoyé beaucoup de messages en peu de temps : réessayez dans une heure.'));

        return false;
    }

    private function user(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
