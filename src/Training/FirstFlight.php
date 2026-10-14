<?php

declare(strict_types=1);

namespace App\Training;

use App\Entity\NetworkSession;
use App\Entity\User;
use App\Repository\FlightRepository;
use App\Repository\NetworkSessionRepository;
use Symfony\Component\Clock\ClockInterface;

/**
 * "Mon premier vol" of a pilot (FirstFlightStep): which steps are done, and the next initiation
 * flight to join.
 */
final readonly class FirstFlight
{
    /** Coming sessions looked through for the next initiation flight. */
    private const int LOOKAHEAD = 20;

    public function __construct(
        private FlightRepository $flights,
        private NetworkSessionRepository $sessions,
        private PilotPath $path,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Every step, done or not; a visitor has none done.
     *
     * @return array<string, bool> by FirstFlightStep value, in order
     */
    public function of(?User $pilot): array
    {
        $done = array_fill_keys(array_map(static fn (FirstFlightStep $step): string => $step->value, FirstFlightStep::cases()), false);
        if (null === $pilot) {
            return $done;
        }
        $logbook = $this->flights->findLogbook($pilot);
        $done[FirstFlightStep::Account->value] = true;
        $done[FirstFlightStep::Condor->value] = [] !== $pilot->getCondorVersions() || [] !== $logbook;
        $done[FirstFlightStep::Discord->value] = $pilot->hasDeclared(FirstFlightStep::Discord);
        $done[FirstFlightStep::SoloFlight->value] = [] !== $logbook;
        $done[FirstFlightStep::NetworkFlight->value] = array_any($logbook, static fn ($flight): bool => null !== $flight->getSession());
        $done[FirstFlightStep::FirstStep->value] = StepState::Done === ($this->path->of($pilot)[0]->state ?? null);

        return $done;
    }

    /** The next network session of the initiation library, null when none is planned. */
    public function nextInitiation(): ?NetworkSession
    {
        return array_find($this->sessions->findUpcoming($this->clock->now(), self::LOOKAHEAD), static fn (NetworkSession $session): bool => 'Initiation' === $session->getFlightPlan()->getCategory());
    }
}
