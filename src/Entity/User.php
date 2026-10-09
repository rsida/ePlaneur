<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use App\Security\Permission;
use App\Security\UserNotification;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Website account. Rights come from the groups the user belongs to, checked by voters on every
 * request: changing a user's groups takes effect immediately, without logging them out.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
#[UniqueEntity(fields: ['email'], message: 'Un compte existe déjà avec cette adresse e-mail.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private string $email = '';

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 50)]
    private string $displayName = '';

    /** Hashed password. */
    #[ORM\Column]
    private string $password = '';

    #[ORM\Column(options: ['default' => false])]
    private bool $verified = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** Author profile: role shown under the name, e.g. "Rédactrice". */
    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $jobTitle = null;

    /** Author profile: short presentation shown under articles. */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bio = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Media $avatar = null;

    /** Competition id in Condor ("RS2"), to recognise the pilot's tracks. */
    #[ORM\Column(length: 10, nullable: true)]
    #[Assert\Length(max: 10)]
    private ?string $condorCn = null;

    /** Pilot name in Condor, as written in the tracks. */
    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $condorPilotName = null;

    /** @var list<int> versions of Condor the pilot flies (2, 3) */
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $condorVersions = [];

    /** @var list<string> e-mails the account accepts (UserNotification values) */
    #[ORM\Column(type: 'json', options: ['default' => '["flight_comment","message","renewal"]'])]
    private array $notifications = [UserNotification::FlightComment->value, UserNotification::Message->value, UserNotification::Renewal->value];

    /** New flights are public (shown to visitors) unless the pilot changes them. */
    #[ORM\Column(options: ['default' => false])]
    private bool $flightsPublicByDefault = false;

    /** The display name appears in the season ranking of the club. */
    #[ORM\Column(options: ['default' => true])]
    private bool $inRanking = true;

    /** @var Collection<int, Group> */
    #[ORM\ManyToMany(targetEntity: Group::class, inversedBy: 'users')]
    #[ORM\JoinTable(name: 'app_user_group')]
    private Collection $groups;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->groups = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getUserIdentifier(): string
    {
        if ('' === $this->email) {
            throw new \LogicException('A user without e-mail cannot be authenticated.');
        }

        return $this->email;
    }

    public function getCondorCn(): ?string
    {
        return $this->condorCn;
    }

    public function setCondorCn(?string $condorCn): static
    {
        $this->condorCn = null !== $condorCn && '' !== trim($condorCn) ? mb_strtoupper(trim($condorCn)) : null;

        return $this;
    }

    public function getCondorPilotName(): ?string
    {
        return $this->condorPilotName;
    }

    public function setCondorPilotName(?string $condorPilotName): static
    {
        $this->condorPilotName = null !== $condorPilotName && '' !== trim($condorPilotName) ? trim($condorPilotName) : null;

        return $this;
    }

    /**
     * @return list<int>
     */
    public function getCondorVersions(): array
    {
        return $this->condorVersions;
    }

    /**
     * @param iterable<int|string> $versions
     */
    public function setCondorVersions(iterable $versions): static
    {
        $list = array_values(array_unique(array_map(intval(...), [...$versions])));
        sort($list);
        $this->condorVersions = $list;

        return $this;
    }

    /**
     * @return list<UserNotification>
     */
    public function getNotifications(): array
    {
        return array_values(array_filter(array_map(UserNotification::tryFrom(...), $this->notifications)));
    }

    /**
     * @param iterable<UserNotification> $notifications
     */
    public function setNotifications(iterable $notifications): static
    {
        $this->notifications = array_values(array_unique(array_map(static fn (UserNotification $notification): string => $notification->value, [...$notifications])));

        return $this;
    }

    public function wants(UserNotification $notification): bool
    {
        return \in_array($notification->value, $this->notifications, true);
    }

    public function isFlightsPublicByDefault(): bool
    {
        return $this->flightsPublicByDefault;
    }

    public function setFlightsPublicByDefault(bool $flightsPublicByDefault): static
    {
        $this->flightsPublicByDefault = $flightsPublicByDefault;

        return $this;
    }

    public function isInRanking(): bool
    {
        return $this->inRanking;
    }

    public function setInRanking(bool $inRanking): static
    {
        $this->inRanking = $inRanking;

        return $this;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): static
    {
        $this->displayName = trim($displayName);

        return $this;
    }

    /**
     * Every account has the same Symfony role: fine-grained rights are permissions held by groups
     * (`is_granted('USER_MANAGE')`), so they are not frozen in the security token.
     */
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->verified;
    }

    public function setVerified(bool $verified): static
    {
        $this->verified = $verified;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getJobTitle(): ?string
    {
        return $this->jobTitle;
    }

    public function setJobTitle(?string $jobTitle): static
    {
        $this->jobTitle = $jobTitle;

        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): static
    {
        $this->bio = $bio;

        return $this;
    }

    public function getAvatar(): ?Media
    {
        return $this->avatar;
    }

    public function setAvatar(?Media $avatar): static
    {
        $this->avatar = $avatar;

        return $this;
    }

    /** Two-letter initials, used when there is no avatar. */
    public function getInitials(): string
    {
        $words = preg_split('/[\s\-]+/u', $this->displayName, -1, \PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = '';
        foreach (\array_slice($words, 0, 2) as $word) {
            $initials .= mb_strtoupper(mb_substr($word, 0, 1));
        }

        return $initials;
    }

    /**
     * @return Collection<int, Group>
     */
    public function getGroups(): Collection
    {
        return $this->groups;
    }

    public function addGroup(Group $group): static
    {
        if (!$this->groups->contains($group)) {
            $this->groups->add($group);
        }

        return $this;
    }

    public function removeGroup(Group $group): static
    {
        $this->groups->removeElement($group);

        return $this;
    }

    /**
     * The user's groups and the groups they include (Group::getIncludedGroups()): what decides the
     * user's permissions and the content they may see.
     *
     * @return list<Group>
     */
    public function getEffectiveGroups(): array
    {
        $groups = [];
        foreach ($this->groups as $group) {
            foreach ($group->getEffectiveGroups() as $effective) {
                if (!\in_array($effective, $groups, true)) {
                    $groups[] = $effective;
                }
            }
        }

        return $groups;
    }

    public function isInGroup(string $code): bool
    {
        return array_any($this->getEffectiveGroups(), static fn (Group $group): bool => $group->getCode() === $code);
    }

    public function hasPermission(Permission $permission): bool
    {
        return array_any($this->getEffectiveGroups(), static fn (Group $group): bool => $group->hasPermission($permission));
    }

    /** True when one of the user's groups grants every permission (administrators). */
    public function hasAllPermissions(): bool
    {
        return array_any($this->getEffectiveGroups(), static fn (Group $group): bool => $group->hasAllPermissions());
    }

    /**
     * Keep the hash out of the session (Symfony 7.3+): the token only needs a fingerprint of it to
     * detect a password change.
     *
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    public function __toString(): string
    {
        return $this->displayName;
    }
}
