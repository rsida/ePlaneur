<?php

declare(strict_types=1);

namespace App\Session;

use App\Entity\Flight;
use App\Entity\FlightPlan;
use App\Entity\NetworkSession;
use App\Repository\FlightRepository;
use App\Repository\NetworkSessionRepository;

/**
 * Joins the uploaded tracks to their network session.
 *
 * Every pilot of a race on the dedicated server gets the same race id in their .ftr file, and the
 * results file of the server is named after it ("DSRace-<race id>-on-<date>"): a session with
 * results takes the tracks of its race and only them. A session without results yet (just flown)
 * takes the tracks on its flight plan uploaded within three days; they are sorted out when its
 * results arrive. IGC files have no race id: they join by flight plan and date.
 */
final readonly class SessionLinker
{
    /** Days after a session during which a track on its flight plan joins it. */
    public const int UPLOAD_DAYS = 3;

    public function __construct(
        private NetworkSessionRepository $sessions,
        private FlightRepository $flights,
        private FlightPlans $flightPlans,
    ) {
    }

    /**
     * Finds the session of a flight, and records its flight plan when it is one of the library, so
     * that the flight plan has its page (the caller flushes).
     */
    public function link(Flight $flight): ?NetworkSession
    {
        $session = null;
        if (null !== $flight->getRaceId()) {
            $session = $this->sessions->findOneByRaceId($flight->getRaceId());
        }
        $code = FlightPlan::codeIn($flight->getFlightPlan());
        if (null !== $code) {
            $described = FlightPlan::describe((string) $flight->getFlightPlan());
            $this->flightPlans->get($code, $described['title'], $described['gliders'], $described['landscape']);
        }
        if (null === $session && null !== $code) {
            $session = $this->sessions->findOpenFor($code, $flight->getDate());
        }
        $flight->setSession($session);

        return $session;
    }

    /**
     * Gathers the tracks of a session after it was created or got its results: the tracks of its
     * race, or without results the free tracks on its flight plan of the following days; tracks of
     * another race leave it (the caller flushes).
     */
    public function gather(NetworkSession $session): void
    {
        $raceId = $session->getRaceId();
        foreach ($session->getFlights() as $flight) {
            if (null !== $raceId && null !== $flight->getRaceId() && $flight->getRaceId() !== $raceId) {
                $flight->setSession(null);
                $session->getFlights()->removeElement($flight);
            }
        }
        $candidates = null !== $raceId
            ? $this->flights->findBy(['raceId' => $raceId])
            : $this->flights->findOnFlightPlan($session->getFlightPlan()->getCode());
        $from = $session->getDate();
        $to = $from->modify('+'.self::UPLOAD_DAYS.' days')->setTime(23, 59, 59);
        foreach ($candidates as $flight) {
            if ($flight->getSession() === $session) {
                continue;
            }
            $joins = null !== $raceId
                ? null === $flight->getSession() || null === $flight->getSession()->getRaceId()
                : null === $flight->getSession() && $flight->getDate() >= $from && $flight->getDate() <= $to;
            if ($joins) {
                $flight->getSession()?->getFlights()->removeElement($flight);
                $flight->setSession($session);
                $session->getFlights()->add($flight);
            }
        }
    }
}
