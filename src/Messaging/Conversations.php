<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Entity\Conversation;
use App\Entity\Flight;
use App\Entity\Group;
use App\Entity\Message;
use App\Entity\User;
use App\Repository\GroupRepository;
use App\Repository\UserRepository;
use App\Security\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Messages between members (roadmap step 9c): a member writes to another member, to the committee
 * or to the administrators; everyone in the conversation is told by e-mail (MessageMailer).
 */
final readonly class Conversations
{
    /** The groups a member may write to, by code, with the name shown in the form. */
    public const array GROUPS = ['committee' => 'Comité directeur', 'admin' => 'Administrateurs du site'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $users,
        private GroupRepository $groups,
        private MessageMailer $mailer,
        private ClockInterface $clock,
    ) {
    }

    /**
     * The members a user may write to: every other account allowed to write messages, by name.
     *
     * @return list<User>
     */
    public function correspondents(User $user): array
    {
        return array_values(array_filter(
            $this->users->findBy([], ['displayName' => 'ASC']),
            static fn (User $other): bool => $other !== $user && $other->hasPermission(Permission::MessageSend),
        ));
    }

    /** @return array<string, Group> the groups a member may write to that exist, by code */
    public function groups(): array
    {
        $groups = [];
        foreach (array_keys(self::GROUPS) as $code) {
            $group = $this->groups->findOneByCode($code);
            if (null !== $group) {
                $groups[$code] = $group;
            }
        }

        return $groups;
    }

    /** Starts a conversation with a member or a group, its first message written by `author`. */
    public function start(User $author, User|Group $recipient, string $subject, string $body, ?Flight $flight = null): Conversation
    {
        $now = $this->clock->now();
        $conversation = (new Conversation($now))->setSubject($subject)->setFlight($flight);
        $conversation->join($author)->markRead($now);
        if ($recipient instanceof Group) {
            $conversation->setGroup($recipient);
        } else {
            $conversation->join($recipient);
        }
        $this->entityManager->persist($conversation);
        $this->write($conversation, $author, $body);

        return $conversation;
    }

    public function reply(Conversation $conversation, User $author, string $body): Message
    {
        $conversation->join($author);

        return $this->write($conversation, $author, $body);
    }

    /** The user has read the conversation up to now (and joins it, for a member of its group). */
    public function markRead(Conversation $conversation, User $user): void
    {
        $conversation->join($user)->markRead($this->clock->now());
        $this->entityManager->flush();
    }

    /**
     * Who reads a conversation besides an author: its participants and the members of its group.
     *
     * @return list<User>
     */
    public function readers(Conversation $conversation, ?User $except = null): array
    {
        $readers = [];
        foreach ($conversation->getParticipants() as $participant) {
            $readers[] = $participant->getUser();
        }
        $group = $conversation->getGroup();
        if (null !== $group) {
            foreach ($this->users->findAll() as $user) {
                if ($user->isInGroup($group->getCode())) {
                    $readers[] = $user;
                }
            }
        }

        $unique = [];
        foreach ($readers as $reader) {
            if ($reader !== $except) {
                $unique[spl_object_id($reader)] = $reader;
            }
        }

        return array_values($unique);
    }

    private function write(Conversation $conversation, User $author, string $body): Message
    {
        $now = $this->clock->now();
        $message = (new Message($conversation, $author, $now))->setBody($body);
        $conversation->add($message);
        $conversation->join($author)->markRead($now);
        $this->entityManager->persist($message);
        $this->entityManager->flush();
        $this->mailer->send($message, $this->readers($conversation, $author));

        return $message;
    }
}
