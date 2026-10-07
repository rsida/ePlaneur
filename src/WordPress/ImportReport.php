<?php

declare(strict_types=1);

namespace App\WordPress;

/**
 * What an import did: counts by kind ("Pages créées"...) and the warnings to review.
 */
final class ImportReport
{
    /** @var array<string, int> */
    private array $counts = [];

    /** @var list<string> */
    private array $warnings = [];

    public function count(string $what, int $by = 1): void
    {
        $this->counts[$what] = ($this->counts[$what] ?? 0) + $by;
    }

    public function warn(string ...$warnings): void
    {
        array_push($this->warnings, ...$warnings);
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        return $this->counts;
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }
}
