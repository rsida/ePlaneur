<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ConversationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Messages between members (roadmap step 9c): a conversation between members, or with a group of
 * the club (the committee, the administrators), whose every member reads it and may answer.
 * Possibly about a flight.
 */
#[ORM\Entity(repositoryClass: ConversationRepository::class)]
#[ORM\Index(columns: ['last_message_at'])]
class Conversation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank(message: 'Donnez un objet à votre message.')]
    #[Assert\Length(max: 160)]
    private string $subject = '';

    /** The group written to (committee, administrators): its members read the conversation. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'addressed_group_id', nullable: true, onDelete: 'SET NULL')]
    private ?Group $group = null;

    /** The flight the conversation is about. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Flight $flight = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $lastMessageAt;

    /** @var Collection<int, ConversationParticipant> */
    #[ORM\OneToMany(targetEntity: ConversationParticipant::class, mappedBy: 'conversation', cascade: ['persist'], orphanRemoval: true)]
    private Collection $participants;

    /** @var Collection<int, Message> */
    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'conversation', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC', 'id' => 'ASC'])]
    private Collection $messages;

    public function __construct(\DateTimeImmutable $createdAt)
    {
        $this->createdAt = $createdAt;
        $this->lastMessageAt = $createdAt;
        $this->participants = new ArrayCollection();
        $this->messages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = trim($subject);

        return $this;
    }

    public function getGroup(): ?Group
    {
        return $this->group;
    }

    public function setGroup(?Group $group): static
    {
        $this->group = $group;

        return $this;
    }

    public function getFlight(): ?Flight
    {
        return $this->flight;
    }

    public function setFlight(?Flight $flight): static
    {
        $this->flight = $flight;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastMessageAt(): \DateTimeImmutable
    {
        return $this->lastMessageAt;
    }

    /** @return Collection<int, ConversationParticipant> */
    public function getParticipants(): Collection
    {
        return $this->participants;
    }

    /** The participation of a user, added when missing (a member of the group reading it). */
    public function join(User $user): ConversationParticipant
    {
        foreach ($this->participants as $participant) {
            if ($participant->getUser() === $user) {
                return $participant;
            }
        }
        $participant = new ConversationParticipant($this, $user);
        $this->participants->add($participant);

        return $participant;
    }

    public function isParticipant(User $user): bool
    {
        return $this->participants->exists(static fn (int $key, ConversationParticipant $participant): bool => $participant->getUser() === $user);
    }

    /** @return Collection<int, Message> */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function getLastMessage(): ?Message
    {
        return $this->messages->last() ?: null;
    }

    public function add(Message $message): void
    {
        $this->messages->add($message);
        $this->lastMessageAt = $message->getCreatedAt();
    }

    /** A message from someone else came after the user last read it. */
    public function isUnreadFor(User $user): bool
    {
        $last = $this->getLastMessage();
        if (null === $last || $last->getAuthor() === $user) {
            return false;
        }
        foreach ($this->participants as $participant) {
            if ($participant->getUser() === $user) {
                return null === $participant->getReadAt() || $participant->getReadAt() < $this->lastMessageAt;
            }
        }

        return true;
    }

    /**
     * Who the conversation is with, seen by a user: for a conversation with a group, the group, or
     * the member who wrote to it when the user belongs to the group; otherwise the other
     * participants.
     *
     * @return list<string>
     */
    public function correspondentsFor(User $user): array
    {
        if (null !== $this->group) {
            if (!$user->isInGroup($this->group->getCode())) {
                return [$this->group->getName()];
            }
            $starter = $this->getStarter();

            return [null !== $starter ? $starter->getDisplayName() : 'Membre supprimé'];
        }
        $names = [];
        foreach ($this->participants as $participant) {
            if ($participant->getUser() !== $user) {
                $names[] = $participant->getUser()->getDisplayName();
            }
        }

        return [] !== $names ? $names : ['Membre supprimé'];
    }

    /** The author of the first message (null once their account is deleted). */
    public function getStarter(): ?User
    {
        $first = $this->messages->first();

        return false !== $first ? $first->getAuthor() : null;
    }
}
