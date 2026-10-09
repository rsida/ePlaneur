<?php

declare(strict_types=1);

namespace App\Session;

use App\Entity\FlightPlan;
use App\Entity\NetworkSession;
use App\Repository\NetworkSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * Brings the sessions published on eplaneur.fr to the club site: their flight plan (with the
 * address of its sheet), date, dedicated group and results, and joins the uploaded tracks of their
 * race. Sessions already imported keep their data; their missing results are read again.
 */
final readonly class SessionImporter
{
    public function __construct(
        private EplaneurSessions $source,
        private NetworkSessionRepository $sessions,
        private FlightPlans $flightPlans,
        private SessionLinker $linker,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param ?\DateTimeImmutable                     $since  only the sessions from this day
     * @param float                                   $delay  seconds between two pages of eplaneur.fr
     * @param ?callable(NetworkSession, string): void $onEach told about each session ("created", "updated", "kept")
     *
     * @return array{created: int, updated: int, kept: int, failed: list<string>}
     */
    public function import(?\DateTimeImmutable $since, bool $withResults, float $delay = 0.5, ?callable $onEach = null): array
    {
        $dedicated = [];
        foreach (EplaneurSessions::DEDICATED as $value => $label) {
            foreach ($this->source->sessions($value) as $row) {
                $dedicated[$row['file']] = $label;
            }
            $this->clock->sleep($delay);
        }

        $report = ['created' => 0, 'updated' => 0, 'kept' => 0, 'failed' => []];
        foreach (array_reverse($this->source->sessions()) as $row) {
            if (null !== $since && $row['date'] < $since) {
                continue;
            }
            $code = FlightPlan::codeIn($row['code']);
            if (null === $code) {
                $report['failed'][] = $row['file'].': code '.$row['code'];
                continue;
            }
            $session = $this->sessions->findOneBy(['resultsFile' => $row['file']]);
            $state = null === $session ? 'created' : 'kept';
            if (null === $session) {
                $plan = $this->flightPlans->get($code, $row['title'], $row['gliders'], $row['landscape']);
                $session = new NetworkSession($plan, $row['date'], $this->clock->now());
                $session->setDedicatedTo($dedicated[$row['file']] ?? null);
                $session->setResults($row['file'], []);
                $this->entityManager->persist($session);
            }
            if ($withResults && [] === $session->getResults()) {
                try {
                    $session->setResults($row['file'], $this->source->results($row['file'], $row['folder']));
                    $state = 'created' === $state ? $state : 'updated';
                } catch (\Throwable $exception) {
                    $report['failed'][] = $row['file'].': '.$exception->getMessage();
                }
                $this->clock->sleep($delay);
            }
            $this->linker->gather($session);
            $this->entityManager->flush();
            ++$report[$state];
            if (null !== $onEach) {
                $onEach($session, $state);
            }
        }

        return $report;
    }
}
