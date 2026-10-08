<?php

declare(strict_types=1);

namespace App\Entity;

use App\Membership\MembershipStatus;
use App\Repository\MembershipRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The membership of an account for a season: what the member filled in (the data of the membership
 * form of the internal rules), the fee and the optional support payment, and its payment and
 * validation by the club. Kept when the account is deleted (the books need it): name and e-mail are
 * copied.
 */
#[ORM\Entity(repositoryClass: MembershipRepository::class)]
#[ORM\Index(columns: ['status'])]
class Membership
{
    public const string PAYMENT_ONLINE = 'online';
    public const string PAYMENT_TRANSFER = 'transfer';
    public const string PAYMENT_OTHER = 'other';

    /** @var array<string, string> label => value, for forms */
    public const array PAYMENT_METHODS = ['Paiement en ligne' => self::PAYMENT_ONLINE, 'Virement' => self::PAYMENT_TRANSFER, 'Autre (espèces, chèque…)' => self::PAYMENT_OTHER];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $user;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private MembershipSeason $season;

    /** Required (validation), nullable for the forms. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    #[Assert\NotNull(message: 'Choisissez un type d’adhésion.')]
    private ?MembershipType $type = null;

    #[ORM\Column(length: 20, enumType: MembershipStatus::class)]
    private MembershipStatus $status = MembershipStatus::Requested;

    /** Fee of the type when the request was sent, in cents. */
    #[ORM\Column]
    private int $fee = 0;

    /** Voluntary support payment on top of the fee, in cents. */
    #[ORM\Column]
    #[Assert\PositiveOrZero(message: 'Le soutien ne peut pas être négatif.')]
    private int $donation = 0;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Indiquez votre prénom.')]
    #[Assert\Length(max: 100)]
    private string $firstName = '';

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Indiquez votre nom.')]
    #[Assert\Length(max: 100)]
    private string $lastName = '';

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Assert\NotNull(message: 'Indiquez votre date de naissance.')]
    #[Assert\LessThan('today', message: 'Cette date de naissance n’est pas valable.')]
    private ?\DateTimeImmutable $birthDate = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Indiquez votre adresse.')]
    #[Assert\Length(max: 255)]
    private string $address = '';

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Indiquez votre code postal.')]
    #[Assert\Length(max: 20)]
    private string $postalCode = '';

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Indiquez votre ville.')]
    #[Assert\Length(max: 100)]
    private string $city = '';

    #[ORM\Column(length: 2)]
    #[Assert\Country]
    private string $country = 'FR';

    #[ORM\Column(length: 30)]
    #[Assert\NotBlank(message: 'Indiquez un numéro de téléphone (WhatsApp de préférence).')]
    #[Assert\Length(max: 30)]
    private string $phone = '';

    #[ORM\Column]
    #[Assert\IsTrue(message: 'L’adhésion suppose d’accepter les statuts et le règlement intérieur.')]
    private bool $rulesAccepted = false;

    #[ORM\Column]
    private \DateTimeImmutable $requestedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $paymentMethod = null;

    /** Reference of the payment: online payment id, transfer label... */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $paymentReference = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $validatedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $validatedBy = null;

    /** Note of the club, not shown to the member. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    /** When the renewal reminder for the next season was sent. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $reminderSentAt = null;

    public function __construct(User $user, MembershipSeason $season, \DateTimeImmutable $requestedAt)
    {
        $this->user = $user;
        $this->season = $season;
        $this->email = $user->getEmail();
        $this->requestedAt = $requestedAt;
    }

    public function __toString(): string
    {
        return \sprintf('%s · %s', $this->getFullName(), $this->season->getLabel());
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getSeason(): MembershipSeason
    {
        return $this->season;
    }

    public function getType(): ?MembershipType
    {
        return $this->type;
    }

    public function setType(?MembershipType $type): static
    {
        $this->type = $type;
        $this->fee = $type?->getPrice() ?? 0;

        return $this;
    }

    public function getStatus(): MembershipStatus
    {
        return $this->status;
    }

    public function getFee(): int
    {
        return $this->fee;
    }

    public function getDonation(): int
    {
        return $this->donation;
    }

    public function setDonation(?int $donation): static
    {
        $this->donation = (int) $donation;

        return $this;
    }

    /** Fee and support payment, in cents. */
    public function getTotal(): int
    {
        return $this->fee + $this->donation;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): static
    {
        $this->firstName = trim((string) $firstName);

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): static
    {
        $this->lastName = trim((string) $lastName);

        return $this;
    }

    public function getFullName(): string
    {
        return trim($this->firstName.' '.$this->lastName);
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getBirthDate(): ?\DateTimeImmutable
    {
        return $this->birthDate;
    }

    public function setBirthDate(?\DateTimeImmutable $birthDate): static
    {
        $this->birthDate = $birthDate;

        return $this;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = trim((string) $address);

        return $this;
    }

    public function getPostalCode(): string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): static
    {
        $this->postalCode = trim((string) $postalCode);

        return $this;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = trim((string) $city);

        return $this;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function setCountry(?string $country): static
    {
        $this->country = (string) $country;

        return $this;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = trim((string) $phone);

        return $this;
    }

    public function isRulesAccepted(): bool
    {
        return $this->rulesAccepted;
    }

    public function setRulesAccepted(bool $rulesAccepted): static
    {
        $this->rulesAccepted = $rulesAccepted;

        return $this;
    }

    public function getRequestedAt(): \DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function getPaymentMethodLabel(): ?string
    {
        return false !== ($label = array_search($this->paymentMethod, self::PAYMENT_METHODS, true)) ? $label : null;
    }

    public function getPaymentReference(): ?string
    {
        return $this->paymentReference;
    }

    public function setPaymentReference(?string $paymentReference): static
    {
        $this->paymentReference = $paymentReference;

        return $this;
    }

    public function getValidatedAt(): ?\DateTimeImmutable
    {
        return $this->validatedAt;
    }

    public function getValidatedBy(): ?User
    {
        return $this->validatedBy;
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

    public function getReminderSentAt(): ?\DateTimeImmutable
    {
        return $this->reminderSentAt;
    }

    public function markReminderSent(\DateTimeImmutable $at): void
    {
        $this->reminderSentAt = $at;
    }

    /** The payment was received (online, or recorded by the treasurer). */
    public function markPaid(string $method, ?string $reference, \DateTimeImmutable $at): void
    {
        $this->paymentMethod = $method;
        $this->paymentReference = $reference;
        $this->paidAt = $at;
        if (MembershipStatus::Requested === $this->status) {
            $this->status = MembershipStatus::Paid;
        }
    }

    public function validate(?User $by, \DateTimeImmutable $at): void
    {
        $this->status = MembershipStatus::Validated;
        $this->validatedBy = $by;
        $this->validatedAt = $at;
    }

    public function refuse(?User $by, \DateTimeImmutable $at): void
    {
        $this->status = MembershipStatus::Refused;
        $this->validatedBy = $by;
        $this->validatedAt = $at;
    }

    public function cancel(): void
    {
        $this->status = MembershipStatus::Cancelled;
    }
}
