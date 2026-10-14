<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Airfield;
use App\Entity\Flight;
use App\Entity\PathCompletion;
use App\Entity\PathItem;
use App\Entity\PathStep;
use App\Entity\Quiz;
use App\Entity\StepProgress;
use App\Entity\User;
use App\Flight\Analysis\FlightAnalyzer;
use App\Flight\Fix;
use App\Flight\Track;
use App\Flight\Turnpoint;
use App\Repository\PathStepRepository;
use App\Tests\Unit\Flight\FlightSamples;
use App\Training\ExerciseCriterion;
use App\Training\PathItemType;
use App\Training\ProgressStatus;
use App\Training\TrainingStep;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * "Mon parcours": the steps as a path, items done by reading, quizzes and exercises, steps opened
 * one after the other, rankings of an exercise once enough pilots finished it.
 */
final class PathTest extends WebTestCase
{
    use ClockSensitiveTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testVisitorsDiscoverThePath(): void
    {
        $this->configure();

        $this->client->request('GET', '/parcours');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Le parcours de formation');
        self::assertSelectorCount(8, '.p-path__stop');
        self::assertSelectorCount(6, '.p-path__stop--closed', 'Steps 1 and 2 are open');
        self::assertSelectorExists('a[href="/inscription"]');

        $this->client->request('GET', '/parcours/etape/1');
        self::assertSelectorCount(4, '.p-path-item');
        self::assertSelectorNotExists('.p-path-item form', 'Visitors read, pilots do');
        $this->client->request('GET', '/parcours/etape/9');
        self::assertResponseStatusCodeSame(404);
    }

    public function testCompletingEveryItemOfAStepOpensTheNextOne(): void
    {
        [$reading, $quiz, $exercise] = $this->configure();
        $pilot = $this->user('lea@example.org');
        $this->client->loginUser($pilot);

        $this->client->request('GET', '/parcours');
        self::assertSelectorTextContains('h1', 'Mon parcours');
        self::assertSelectorExists('.p-path__stop--current a[href="/parcours/etape/1"]');
        self::assertSelectorExists('.p-path__stop--locked a[href="/parcours/etape/2"]');

        // A step not reached yet cannot be done
        $locked = $this->entityManager->getRepository(PathStep::class)->findOneBy(['step' => TrainingStep::Solo])?->getItems()->first();
        self::assertInstanceOf(PathItem::class, $locked);
        $this->client->request('POST', '/parcours/element/'.$locked->getId().'/fait', ['_token' => 'x']);
        self::assertResponseStatusCodeSame(403);

        // Reading
        $this->client->request('GET', '/parcours/etape/1');
        $this->client->submit($this->client->getCrawler()->filter('#element-'.$reading->getId())->selectButton('C’est fait')->form());
        self::assertResponseRedirects('/parcours/etape/1#element-'.$reading->getId());
        $this->client->followRedirect();
        self::assertSelectorExists('#element-'.$reading->getId().'.p-path-item--done');

        // Quiz: a wrong answer, then the right one; the best score is kept
        $this->client->request('GET', '/parcours/quiz/'.$quiz->getId());
        $this->client->submitForm('Valider mes réponses');
        self::assertResponseStatusCodeSame(422, 'Every question needs an answer');
        $this->client->submitForm('Valider mes réponses', ['form[q0]' => '0']);
        self::assertResponseRedirects('/parcours/quiz/'.$quiz->getId().'?resultat=1', 303, 'Turbo shows the result of a submission only after a redirect');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.c-alert--error', 'Réponses envoyées : 0 bonne réponse sur 1 (0 %).');
        self::assertSelectorTextContains('.p-quiz__question--wrong .p-quiz__verdict', 'Erreur');
        self::assertSelectorTextContains('.p-quiz__choice--wrong', 'Rien votre réponse');
        self::assertSelectorTextContains('.p-quiz__choice--right', 'Le lacet inverse bonne réponse');
        self::assertSelectorTextContains('.p-quiz__explanation', 'L’aile haute traîne plus.');
        $this->client->request('GET', '/parcours/quiz/'.$quiz->getId());
        self::assertSelectorExists('form input[name="form[q0]"]', 'Without ?resultat, the quiz again');
        $this->client->submitForm('Valider mes réponses', ['form[q0]' => '1']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.c-alert--success', 'Quiz réussi');
        self::assertSelectorTextContains('.p-quiz__choice--right', 'Le lacet inverse votre réponse');
        $completion = $this->entityManager->getRepository(PathCompletion::class)->findOneBy(['pilot' => $pilot->getId(), 'item' => $quiz->getId()]);
        self::assertSame([100, 2], [$completion?->getScore(), $completion?->getAttempts()]);

        // Exercise: a finished task on its flight plan
        $this->client->request('GET', '/parcours/exercice/'.$exercise->getId());
        self::assertSelectorTextContains('main', 'Pas encore de vol sur ce plan de vol');
        $this->flight($pilot, 'C2-d015_Issoire-Brioude_ASK21_FrSW ePlaneur');
        $this->client->request('GET', '/parcours/exercice/'.$exercise->getId());
        self::assertSelectorTextContains('.p-path__records', 'Record personnel');
        self::assertSelectorTextContains('main', 'Le classement s’affiche à partir de 2 pilotes.');

        // The trainer's validation completes the step and opens the next one
        $this->client->request('GET', '/parcours');
        self::assertSelectorExists('.p-path__stop--current a[href="/parcours/etape/1"]');
        $progress = new StepProgress($this->entityManager->getReference(User::class, $pilot->getId()), TrainingStep::Discover, ProgressStatus::Validated, new \DateTimeImmutable());
        $this->entityManager->persist($progress);
        $this->entityManager->flush();
        $this->client->request('GET', '/parcours');
        self::assertSelectorExists('.p-path__stop--done a[href="/parcours/etape/1"]');
        self::assertSelectorExists('.p-path__stop--current a[href="/parcours/etape/2"]');
        self::assertSelectorTextContains('.p-path__stop--done', 'Élève ePilote');
    }

    public function testTheRankingOfAnExerciseShowsOnceEnoughPilotsFinishedIt(): void
    {
        [, , $exercise] = $this->configure();
        $lea = $this->user('lea@example.org');
        $this->flight($lea, 'C2-d015_Issoire-Brioude_ASK21_FrSW ePlaneur', slower: 1.2);
        $this->flight($this->user('max@example.org'), 'C2-d015_Issoire-Brioude_ASK21_FrSW ePlaneur');
        $this->flight($this->user('hidden@example.org')->setInRanking(false), 'C2-d015 ePlaneur');
        $this->client->loginUser($lea);

        $this->client->request('GET', '/parcours/exercice/'.$exercise->getId());
        self::assertSelectorCount(2, '.c-data-table tbody tr', 'Pilots who left the rankings are not listed');
        self::assertSelectorTextContains('tbody tr:first-child', 'Test max@example.org');
        self::assertSelectorTextContains('tbody tr[aria-current]', 'Test lea@example.org');
        self::assertSelectorTextContains('.p-path__records', '2e sur 2');
        self::assertSelectorExists('a[href^="/vols/"][href*="/comparer/"]', 'Compare one’s best with the best flight');
    }

    public function testTheRankingIsBySeasonAndLevel(): void
    {
        static::mockTime('2026-10-14 12:00');
        [, , $exercise] = $this->configure();
        $lea = $this->user('lea@example.org');
        $max = $this->user('max@example.org');
        $zoe = $this->user('zoe@example.org');
        $this->flight($lea, 'C2-d015 ePlaneur', slower: 1.2, on: '2026-03-01');
        $this->flight($max, 'C2-d015 ePlaneur', on: '2026-04-01');
        $this->flight($zoe, 'C2-d015 ePlaneur', slower: 1.1, on: '2025-06-01');
        $this->entityManager->persist(new StepProgress($this->entityManager->getReference(User::class, $max->getId()), TrainingStep::Network, ProgressStatus::Validated, new \DateTimeImmutable('2026-01-01')));
        $this->entityManager->flush();
        $this->client->loginUser($lea);

        $this->client->request('GET', '/parcours/exercice/'.$exercise->getId());
        self::assertSelectorExists('#ranking-season option[value="2026"][selected]', 'The current season by default');
        self::assertSelectorExists('#ranking-season option[value="2025"]');
        self::assertSelectorCount(2, '.c-data-table tbody tr', 'Zoé flew in 2025');

        $this->client->request('GET', '/parcours/exercice/'.$exercise->getId().'?saison=tout');
        self::assertSelectorCount(3, '.c-data-table tbody tr');
        self::assertSelectorTextContains('.p-path__records', '3e sur 3');

        $this->client->request('GET', '/parcours/exercice/'.$exercise->getId().'?saison=tout&niveau=eleves');
        self::assertSelectorCount(2, '.c-data-table tbody tr', 'Max has the network step validated');
        self::assertSelectorTextContains('tbody tr:first-child', 'Test zoe@example.org');

        $this->client->request('GET', '/parcours/exercice/'.$exercise->getId().'?niveau=epilotes');
        self::assertSelectorNotExists('.c-data-table');
        self::assertSelectorTextContains('.p-path__ranking-head + p', '1 pilote a réussi cet exercice en 2026 à ce niveau');
        self::assertSelectorExists('a[href$="saison=tout&niveau=epilotes"]');
    }

    public function testExercisesAreCheckedByTheAnalysisOfTheFlight(): void
    {
        $steps = static::getContainer()->get(PathStepRepository::class)->findPath();
        $exercise = (new PathItem())->setType(PathItemType::Exercise)->setTitle('Circuit propre')->setFlightPlan('C2-d015')->setGlider('ASK21')
            ->setCriteriaSet([
                ['criterion' => ExerciseCriterion::TaskFinished],
                ['criterion' => ExerciseCriterion::LandedOnAirfield, 'value' => 12],
                ['criterion' => 'task_speed', 'value' => '55'],
            ])
            ->setRankBy(ExerciseCriterion::Duration);
        $steps[0]->addItem($exercise);
        [$latitude, $longitude] = array_values(FlightSamples::toLatLon(7000, 0));
        $this->entityManager->persist(new Airfield('AA2', 'Brioude', $latitude, $longitude, 430.0, 800));
        $this->entityManager->flush();
        self::assertNull($exercise->getCriteriaSet()[1]->value, 'A fact has no value');

        $pilot = $this->user('lea@example.org');
        $this->flight($pilot, 'C2-d015 ePlaneur', slower: 1.2, glider: 'ASK21', landed: true);
        $this->flight($pilot, 'C2-d015 ePlaneur', glider: 'Discus2b', landed: true);
        $this->flight($pilot, 'C2-d015 ePlaneur', glider: 'ASK21');
        $this->client->loginUser($pilot);

        $this->client->request('GET', '/parcours/exercice/'.$exercise->getId());
        self::assertSelectorTextContains('.p-path-check', 'Planeur : ASK21');
        self::assertSelectorTextContains('.p-path-check', 'Vitesse circuit : au moins 55 km/h');
        self::assertSelectorTextContains('.p-path-check', 'classés par durée de vol');
        self::assertSelectorCount(3, '.p-path-attempt--missed');
        self::assertSelectorTextContains('.p-path-attempt:nth-child(3)', '3 sur 4');
        self::assertSelectorTextContains('.p-path-attempt:nth-child(3) .p-path-attempt__line--missed', 'Vitesse circuit : au moins 55 km/h · 51,1 km/h');
        self::assertSelectorTextContains('.p-path-attempt:nth-child(2) .p-path-attempt__line--missed', 'Planeur : ASK21 · Discus2b');
        self::assertSelectorTextContains('.p-path-attempt:nth-child(1) .p-path-attempt__line--missed', 'Posé sur un terrain');
        $this->client->request('GET', '/parcours/etape/1');
        self::assertSelectorNotExists('#element-'.$exercise->getId().'.p-path-item--done');

        $this->flight($pilot, 'C2-d015 ePlaneur', glider: 'ask21', landed: true);
        $this->client->request('GET', '/parcours/exercice/'.$exercise->getId());
        self::assertSelectorCount(1, '.p-path-attempt--passed');
        self::assertSelectorTextContains('.p-path__records', 'Record personnel · durée de vol');
        $this->client->request('GET', '/parcours/etape/1');
        self::assertSelectorExists('#element-'.$exercise->getId().'.p-path-item--done');
        $this->client->request('GET', '/mon-compte/carnet');
        self::assertSelectorTextContains('.p-training', 'exercices réussis : Circuit propre', 'Trainers see the passed exercises with the logbook');
    }

    public function testBadgesRewardWhatThePilotDoesAgainAndAgain(): void
    {
        [, , $exercise] = $this->configure();
        [$latitude, $longitude] = array_values(FlightSamples::toLatLon(7000, 0));
        $this->entityManager->persist(new Airfield('AA2', 'Brioude', $latitude, $longitude, 430.0, 800));
        $lea = $this->user('lea@example.org');
        $this->flight($lea, 'C2-d015 ePlaneur', slower: 1.2, landed: true);
        $this->flight($lea, 'C2-d015 ePlaneur', landed: true);
        $this->flight($this->user('max@example.org'), 'C2-d015 ePlaneur', slower: 1.1);

        $this->client->request('GET', '/parcours');
        self::assertSelectorTextContains('#badges-title', 'Des badges à gagner');
        self::assertSelectorCount(8, '.p-path-badge');
        self::assertSelectorNotExists('.p-path-badge--bronze');
        self::assertSelectorTextContains('.p-path-badge:nth-child(4)', 'Bronze à 1, argent à 10, or à 50.');

        $this->client->loginUser($lea);
        $this->client->request('GET', '/parcours');
        self::assertSelectorTextContains('#badges-title', 'Mes badges');
        self::assertSelectorCount(3, '.p-path-badge--bronze', 'An exercise passed, landings, a podium');
        self::assertSelectorTextContains('.p-path-badge--bronze:nth-child(2)', 'Appliqué');
        self::assertSelectorTextContains('.p-path-badge:nth-child(4) .c-meter', '2 atterrissages sur un terrain');
        self::assertSelectorTextContains('.p-path-badge:nth-child(4) .c-meter', 'Argent à 10');
        self::assertSelectorTextContains('.p-path-badge:nth-child(7) .c-meter', '1 semaine de vol');
        self::assertSelectorExists('.p-path-badge--bronze:nth-child(8)', 'First of the ranking of '.$exercise->getTitle());

        $this->client->request('GET', '/mon-compte');
        self::assertSelectorTextContains('section[aria-labelledby="path-title"]', '3 badges sur 8 : Appliqué (bronze), Retour au terrain (bronze), Podium (bronze).');
    }

    /**
     * Steps 1 and 2 open; step 1 with a reading, a quiz, an exercise and the trainer's validation.
     *
     * @return array{PathItem, PathItem, PathItem}
     */
    private function configure(): array
    {
        $steps = static::getContainer()->get(PathStepRepository::class)->findPath();
        $quiz = (new Quiz())->setTitle('Les axes')->setQuestions([
            ['question' => 'Que provoque le gauchissement ?', 'choices' => ['Rien', 'Le lacet inverse'], 'answer' => 1, 'explanation' => 'L’aile haute traîne plus.'],
        ]);
        $this->entityManager->persist($quiz);
        $reading = (new PathItem())->setType(PathItemType::Reading)->setTitle('Installer Condor')->setUrl('/formation/bien-demarrer/installer-condor');
        $quizItem = (new PathItem())->setType(PathItemType::Quiz)->setTitle('Quiz des axes')->setQuiz($quiz)->setPosition(1);
        $exercise = (new PathItem())->setType(PathItemType::Exercise)->setTitle('Issoire – Brioude')->setFlightPlan('C2-d015')->setRankingFrom(2)->setPosition(2);
        $steps[0]->addItem($reading)->addItem($quizItem)->addItem($exercise)
            ->addItem((new PathItem())->setType(PathItemType::Validation)->setTitle('Validation')->setPosition(3));
        $steps[1]->setOpen(true)->addItem((new PathItem())->setType(PathItemType::Reading)->setTitle('Le carnet de vols'));
        $this->entityManager->flush();

        return [$reading, $quizItem, $exercise];
    }

    private function flight(User $owner, string $plan, float $slower = 1.0, ?string $glider = null, bool $landed = false, string $on = '2026-03-01'): Flight
    {
        $fixes = array_map(static fn (Fix $fix): Fix => new Fix($fix->time * $slower, $fix->latitude, $fix->longitude, $fix->altitude), FlightSamples::glideThermalGlide());
        if ($landed) {
            // Rolls to a stop at the finish
            $last = end($fixes);
            [$latitude, $longitude] = array_values(FlightSamples::toLatLon(7000, 0));
            for ($t = 1; $t <= 30; ++$t) {
                $fixes[] = new Fix($last->time + $t, $latitude, $longitude, $last->altitude);
            }
        }
        $point = static fn (string $name, float $x, float $radius): Turnpoint => new Turnpoint($name, ...FlightSamples::toLatLon($x, 0), altitude: 0.0, radius: $radius);
        $track = new Track('igc', 'Condor 2', 'Pilote', null, $glider, 'AA2', null, $plan, [$point('Terrain', 0, 3000), $point('Départ', 0, 500), $point('Arrivée', 7000, 500)], $fixes, true, new \DateTimeImmutable($on));
        $owner = $this->entityManager->getReference(User::class, $owner->getId());
        $flight = new Flight((new FlightAnalyzer())->analyze($track), 'file.igc', 'file.igc', $owner, new \DateTimeImmutable($on));
        $this->entityManager->persist($flight);
        $this->entityManager->flush();

        return $flight;
    }

    private function user(string $email): User
    {
        $user = (new User())->setEmail($email)->setDisplayName('Test '.$email)->setVerified(true)->setPassword('x');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
