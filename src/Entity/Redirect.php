<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RedirectRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Permanent redirect of an address of the old site (WordPress) to the new one, followed when the
 * address is not found (LegacyRedirectListener). The source is stored without trailing slash.
 */
#[ORM\Entity(repositoryClass: RedirectRepository::class)]
#[ORM\Table(name: 'redirect')]
class Redirect
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        /** Path of the old address, e.g. "/le-club/documents-officiels/statuts" */
        #[ORM\Column(length: 500, unique: true)]
        private string $source,
        /** Path or URL to redirect to */
        #[ORM\Column(length: 500)]
        private string $target,
    ) {
        $this->source = self::normalize($source);
        $this->createdAt = new \DateTimeImmutable();
    }

    /** "/le-club/statuts/" → "/le-club/statuts"; the home stays "/". */
    public static function normalize(string $path): string
    {
        $path = '/'.trim(rawurldecode((string) parse_url($path, \PHP_URL_PATH)), '/');

        return mb_strtolower($path);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getTarget(): string
    {
        return $this->target;
    }

    public function setTarget(string $target): static
    {
        $this->target = $target;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
