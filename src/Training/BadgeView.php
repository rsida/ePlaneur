<?php

declare(strict_types=1);

namespace App\Training;

/**
 * A badge of "Mon parcours" for a pilot: what they counted, the tier reached and the next one.
 */
final readonly class BadgeView
{
    public function __construct(
        public PathBadge $badge,
        public int $count,
    ) {
    }

    /** The highest tier reached, null before the bronze. */
    public function tier(): ?BadgeTier
    {
        $tier = null;
        foreach ($this->badge->thresholds() as $i => $threshold) {
            if ($this->count >= $threshold) {
                $tier = BadgeTier::cases()[$i];
            }
        }

        return $tier;
    }

    /** The next tier to reach, null once the gold is reached. */
    public function nextTier(): ?BadgeTier
    {
        $i = null === ($tier = $this->tier()) ? 0 : array_search($tier, BadgeTier::cases(), true) + 1;

        return BadgeTier::cases()[$i] ?? null;
    }

    /** Count that earns the next tier. */
    public function nextThreshold(): ?int
    {
        $next = $this->nextTier();

        return null === $next ? null : $this->badge->thresholds()[array_search($next, BadgeTier::cases(), true)];
    }

    /** Way to the next tier, in percent, from the tier reached. */
    public function percent(): int
    {
        $next = $this->nextThreshold();
        if (null === $next) {
            return 100;
        }
        $tier = $this->tier();
        $from = null === $tier ? 0 : $this->badge->thresholds()[array_search($tier, BadgeTier::cases(), true)];

        return (int) floor(100 * ($this->count - $from) / ($next - $from));
    }
}
