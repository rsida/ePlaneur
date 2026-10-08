<?php

declare(strict_types=1);

namespace App\Content;

use App\Security\RestrictedContentInterface;

/**
 * Content written in the block editor (posts and pages): a publication date (none for a draft, to
 * come when scheduled) and the date of its last change.
 */
interface EditableContentInterface extends RestrictedContentInterface
{
    public function getId(): ?int;

    public function getPublishedAt(): ?\DateTimeImmutable;

    public function isPublished(?\DateTimeImmutable $now = null): bool;

    public function getUpdatedAt(): \DateTimeImmutable;

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static;
}
