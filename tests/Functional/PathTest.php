<?php

declare(strict_types=1);

namespace App\Tests\Functional;

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
use App\Training\PathItemType;
use App\Training\ProgressStatus;
use App\Training\TrainingStep;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * "Mon parcours": the steps as a path, items done by reading, quizzes and exercises, steps opened
 * one after the other, rankings of an exercise once enough pilots finished it.
 */
final class PathTest extends WebTestCase
{
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
        $this->client->submitForm('Valider mes réponses', ['form[q0]' => '0']);
        self::assertSelectorTextContains('.c-alert--error', '0 % de bonnes réponses');
        self::assertSelectorTextContains('.p-quiz__review', 'Bonne réponse : Le lacet inverse');
        $this->client->request('GET', '/parcours/quiz/'.$quiz->getId());
        $this->client->submitForm('Valider mes réponses', ['form[q0]' => '1']);
        self::assertSelectorTextContains('.c-alert--success', 'Quiz réussi');
        $completion = $this->entityManager->getRepository(PathCompletion::class)->findOneBy(['pilot' => $pilot->getId(), 'item' => $quiz->getId()]);
        self::assertSame([100, 2], [$completion?->getScore(), $completion?->getAttempts()]);

        // Exercise: a finished task on its flight plan
        $this->client->request('GET', '/parcours/exercice/'.$exercise->getId());
        self::assertSelectorTextContains('main', 'Pas encore de circuit terminé');
        $this->flight($pilot, 'C2-d015_Issoire-Brioude_ASK21_FrSW ePlaneur');
        $this->client->request('GET', '/parcours/exercice/'.$exercise->getId());
        self::assertSelectorTextContains('.p-path__records', 'Record personnel');
        self::assertSelectorTextContains('main', '1 pilote a terminé cet exercice. Le classement s’affiche à partir de 2 pilotes.');

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

    private function flight(User $owner, string $plan, float $slower = 1.0): Flight
    {
        $fixes = array_map(static fn (Fix $fix): Fix => new Fix($fix->time * $slower, $fix->latitude, $fix->longitude, $fix->altitude), FlightSamples::glideThermalGlide());
        $point = static fn (string $name, float $x, float $radius): Turnpoint => new Turnpoint($name, ...FlightSamples::toLatLon($x, 0), altitude: 0.0, radius: $radius);
        $track = new Track('igc', 'Condor 2', 'Pilote', null, null, null, null, $plan, [$point('Terrain', 0, 3000), $point('Départ', 0, 500), $point('Arrivée', 7000, 500)], $fixes, true, new \DateTimeImmutable('2026-03-01'));
        $owner = $this->entityManager->getReference(User::class, $owner->getId());
        $flight = new Flight((new FlightAnalyzer())->analyze($track), 'file.igc', 'file.igc', $owner, new \DateTimeImmutable('2026-03-01'));
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
