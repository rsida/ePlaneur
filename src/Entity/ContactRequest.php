<?php

declare(strict_types=1);

namespace App\Entity;

use App\Contact\ContactSubject;
use App\Repository\ContactRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A message sent to the club with the contact form, followed up in the back-office.
 */
#[ORM\Entity(repositoryClass: ContactRequestRepository::class)]
class ContactRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Indiquez votre nom.')]
    #[Assert\Length(max: 100)]
    private string $name = '';

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'Indiquez votre adresse e-mail pour que le club puisse vous répondre.')]
    #[Assert\Email(message: 'Saisissez une adresse complète, par exemple prenom.nom@example.fr.')]
    #[Assert\Length(max: 180)]
    private string $email = '';

    #[ORM\Column(length: 20, nullable: true, enumType: ContactSubject::class)]
    #[Assert\NotNull(message: 'Choisissez l’objet de votre message.')]
    private ?ContactSubject $subject = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Écrivez votre message.')]
    #[Assert\Length(min: 10, max: 5000, minMessage: 'Votre message est un peu court : précisez votre demande.', maxMessage: 'Le message dépasse {{ limit }} caractères.')]
    private string $message = '';

    /** The account of the sender, when logged in. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $processedBy = null;

    /** Note of the club (answer given, who handles it). */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    public function __construct(\DateTimeImmutable $createdAt, ?User $user = null)
    {
        $this->createdAt = $createdAt;
        $this->user = $user;
        if (null !== $user) {
            $this->name = $user->getDisplayName();
            $this->email = $user->getEmail();
        }
    }

    public function __toString(): string
    {
        return \sprintf('%s · %s', $this->name, $this->subject?->label() ?? '');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = trim((string) $name);

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = mb_strtolower(trim((string) $email));

        return $this;
    }

    public function getSubject(): ?ContactSubject
    {
        return $this->subject;
    }

    public function setSubject(?ContactSubject $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = trim((string) $message);

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getProcessedAt(): ?\DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function getProcessedBy(): ?User
    {
        return $this->processedBy;
    }

    public function isProcessed(): bool
    {
        return null !== $this->processedAt;
    }

    public function markProcessed(?User $by, \DateTimeImmutable $at): void
    {
        $this->processedAt = $at;
        $this->processedBy = $by;
    }

    public function reopen(): void
    {
        $this->processedAt = null;
        $this->processedBy = null;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }
}
