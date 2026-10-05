<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\GroupRepository;
use App\Security\Permission;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A set of users sharing the same rights (Membre, Comité, Rédacteur...).
 *
 * A user can belong to several groups; their rights add up. Groups and their permissions are data,
 * editable by an administrator. "System" groups are referenced by the application (default groups)
 * and cannot be deleted or renamed by code.
 */
#[ORM\Entity(repositoryClass: GroupRepository::class)]
#[ORM\Table(name: 'app_group')]
#[UniqueEntity(fields: ['code'], message: 'Ce code de groupe est déjà utilisé.')]
class Group
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** @var list<string> Permission values, see {@see Permission} */
    #[ORM\Column(type: 'json')]
    private array $permissions = [];

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /** Grants every permission, including those added later, and lets members see all content. */
    #[ORM\Column(options: ['default' => false])]
    private bool $allPermissions = false;

    #[ORM\Column(name: 'is_system', options: ['default' => false])]
    private bool $system = false;

    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'groups')]
    private Collection $users;

    public function __construct(
        #[ORM\Column(length: 50, unique: true)]
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^[a-z0-9][a-z0-9_-]*$/', message: 'Lettres minuscules, chiffres, - et _ uniquement.')]
        #[Assert\Length(max: 50)]
        private string $code,
        #[ORM\Column(length: 100)]
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        private string $name,
    ) {
        $this->users = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    /** Codes are referenced by code (DefaultGroup): only set before the group is first saved. */
    public function setCode(string $code): static
    {
        if (null !== $this->id) {
            throw new \LogicException('The code of a saved group cannot change.');
        }
        $this->code = $code;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Permissions explicitly granted to the group. Values no longer in the catalogue are ignored.
     *
     * @return list<Permission>
     */
    public function getPermissions(): array
    {
        return array_values(array_filter(array_map(Permission::tryFrom(...), $this->permissions)));
    }

    /**
     * @param iterable<Permission> $permissions
     */
    public function setPermissions(iterable $permissions): static
    {
        $values = [];
        foreach ($permissions as $permission) {
            $values[] = $permission->value;
        }
        $this->permissions = array_values(array_unique($values));

        return $this;
    }

    public function grant(Permission $permission): static
    {
        if (!\in_array($permission->value, $this->permissions, true)) {
            $this->permissions[] = $permission->value;
        }

        return $this;
    }

    public function revoke(Permission $permission): static
    {
        $this->permissions = array_values(array_diff($this->permissions, [$permission->value]));

        return $this;
    }

    public function hasPermission(Permission $permission): bool
    {
        return $this->allPermissions || \in_array($permission->value, $this->permissions, true);
    }

    public function hasAllPermissions(): bool
    {
        return $this->allPermissions;
    }

    public function setAllPermissions(bool $allPermissions): static
    {
        $this->allPermissions = $allPermissions;

        return $this;
    }

    public function isSystem(): bool
    {
        return $this->system;
    }

    public function setSystem(bool $system): static
    {
        $this->system = $system;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
