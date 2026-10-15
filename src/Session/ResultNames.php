<?php

declare(strict_types=1);

namespace App\Session;

use App\Account\NameShown;
use App\Entity\NetworkSession;
use App\Entity\User;
use App\Repository\UserRepository;

/**
 * The results of the server of a session with the names the pilots chose (doc/accounts.md#name-shown):
 * the server writes the name set in Condor; a row is renamed when it is the track of a member who
 * hides their name (same competition id or pilot name), or carries the competition id or the Condor
 * pilot name of their profile.
 */
final class ResultNames
{
    /** @var list<User>|null */
    private ?array $hiding = null;

    public function __construct(
        private readonly UserRepository $users,
    ) {
    }

    /**
     * @return list<array{rank: int, status: string, player: string, competitionId: ?string, registration: ?string, glider: ?string, distance: ?int, time: ?int, speed: ?float, penalty: ?float, score: ?float}>
     */
    public function of(NetworkSession $session): array
    {
        $results = $session->getResults();
        $members = [];
        foreach ($session->getFlights() as $flight) {
            if (true === $flight->getOwner()?->hidesName()) {
                $members[] = [$flight->getOwner(), $flight->getCompetitionId(), $flight->getRecordedPilot()];
            }
        }
        if ([] === $results) {
            return $results;
        }
        foreach ($this->hiding() as $user) {
            $members[] = [$user, $user->getCondorCn(), $user->getCondorPilotName()];
        }
        if ([] === $members) {
            return $results;
        }
        $same = static fn (?string $a, ?string $b): bool => null !== $a && '' !== $a && null !== $b && 0 === strcasecmp(trim($a), trim($b));
        foreach ($results as &$row) {
            foreach ($members as [$user, $competitionId, $pilot]) {
                if ($same($row['competitionId'], $competitionId) || $same($row['player'], $pilot)) {
                    $row['player'] = $user->getPublicName();
                    break;
                }
            }
        }

        return $results;
    }

    /** @return list<User> */
    private function hiding(): array
    {
        return $this->hiding ??= array_values(array_filter(
            $this->users->findBy(['nameShown' => [NameShown::FirstName, NameShown::Pseudonym]]),
            static fn (User $user): bool => null !== $user->getCondorCn() || null !== $user->getCondorPilotName(),
        ));
    }
}
