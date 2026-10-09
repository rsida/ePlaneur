<?php

declare(strict_types=1);

namespace App\Session;

use App\Entity\FlightPlan;
use App\Repository\FlightPlanRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Finds or records the flight plans of the sessions: a code seen for the first time is recorded with
 * what is known of it, for the next sessions.
 */
final readonly class FlightPlans
{
    public function __construct(
        private FlightPlanRepository $repository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * The flight plan of a code, recorded if new; the missing details are filled in (the caller
     * flushes).
     */
    public function get(string $code, ?string $title = null, ?string $gliders = null, ?string $landscape = null, ?string $sheetUrl = null): FlightPlan
    {
        $plan = $this->pending($code) ?? $this->repository->findOneByCode($code);
        if (null === $plan) {
            // Every code of the library has its page on eplaneur.fr
            $plan = (new FlightPlan($code))->setSheetUrl(\sprintf(FlightPlan::SHEET_URL, $code));
            $this->entityManager->persist($plan);
        }
        if (null === $plan->getTitle() && null !== $title) {
            $plan->setTitle(mb_substr($title, 0, 160));
        }
        if (null === $plan->getGliders() && null !== $gliders) {
            $plan->setGliders(mb_substr($gliders, 0, 40));
        }
        if (null === $plan->getLandscape() && null !== $landscape) {
            $plan->setLandscape(mb_substr($landscape, 0, 40));
        }
        if (null !== $sheetUrl && '' !== $sheetUrl) {
            $plan->setSheetUrl($sheetUrl);
        }

        return $plan;
    }

    /** A flight plan recorded since the last flush (an import records many before flushing). */
    private function pending(string $code): ?FlightPlan
    {
        foreach ($this->entityManager->getUnitOfWork()->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof FlightPlan && $entity->getCode() === $code) {
                return $entity;
            }
        }

        return null;
    }
}
