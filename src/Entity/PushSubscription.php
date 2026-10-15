<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PushSubscriptionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A device that receives the notifications of the site (roadmap 11l, doc/installable-site.md#notifications):
 * the address and keys given by the browser when the member turned them on. Deleted with the account,
 * or when the browser says the subscription is over.
 */
#[ORM\Entity(repositoryClass: PushSubscriptionRepository::class)]
class PushSubscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 700, unique: true)]
    private string $endpoint;

    /** Public key of the browser (p256dh), base64url. */
    #[ORM\Column(length: 255)]
    private string $publicKey;

    /** Authentication secret of the browser, base64url. */
    #[ORM\Column(length: 255)]
    private string $authToken;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $user, string $endpoint, string $publicKey, string $authToken, \DateTimeImmutable $createdAt)
    {
        $this->user = $user;
        $this->endpoint = $endpoint;
        $this->publicKey = $publicKey;
        $this->authToken = $authToken;
        $this->createdAt = $createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    public function getAuthToken(): string
    {
        return $this->authToken;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** The same browser subscribes again, possibly for another account or with new keys. */
    public function renew(User $user, string $publicKey, string $authToken): void
    {
        $this->user = $user;
        $this->publicKey = $publicKey;
        $this->authToken = $authToken;
    }
}
