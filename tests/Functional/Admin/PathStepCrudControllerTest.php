<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Controller\Admin\DashboardController;
use App\Controller\Admin\PathStepCrudController;
use App\Entity\PathStep;
use App\Entity\Quiz;
use App\Training\ExerciseCriterion;
use App\Training\PathItemType;
use App\Training\TrainingStep;
use EasyCorp\Bundle\EasyAdminBundle\Test\AbstractCrudTestCase;

/**
 * "Mon parcours" in the back-office: the eight steps listed, a step opened and given its items.
 *
 * @extends AbstractCrudTestCase<PathStepCrudController>
 */
final class PathStepCrudControllerTest extends AbstractCrudTestCase
{
    use AdminUsers;

    protected function getControllerFqcn(): string
    {
        return PathStepCrudController::class;
    }

    protected function getDashboardFqcn(): string
    {
        return DashboardController::class;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->client->loginUser($this->userIn($this->entityManager, 'admin@example.org', 'admin'));
    }

    public function testAStepIsOpenedWithItsItems(): void
    {
        $this->client->request('GET', $this->generateIndexUrl());
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(8, 'tbody tr', 'The eight steps exist from the start');
        self::assertSelectorTextContains('tbody', 'Devenir autonome en solo');

        $quiz = (new Quiz())->setTitle('Les axes')->setQuestions([['question' => 'Q ?', 'choices' => ['A', 'B'], 'answer' => 0]]);
        $this->entityManager->persist($quiz);
        $this->entityManager->flush();
        $step = $this->entityManager->getRepository(PathStep::class)->findOneBy(['step' => TrainingStep::Solo]);
        self::assertNotNull($step);
        self::assertFalse($step->isOpen());

        $crawler = $this->client->request('GET', $this->generateEditFormUrl((int) $step->getId()));
        $form = $crawler->filter('form[name="PathStep"]')->form();
        $values = $form->getPhpValues();
        $values['PathStep']['open'] = '1';
        $values['PathStep']['intro'] = 'Voler seul un circuit court.';
        $values['PathStep']['items'] = [
            ['position' => '2', 'type' => 'quiz', 'title' => 'Quiz du circuit', 'quiz' => (string) $quiz->getId(), 'rankBy' => 'task_speed', 'rankingFrom' => '5'],
            ['position' => '1', 'type' => 'exercise', 'title' => 'Issoire – Brioude', 'flightPlan' => 'c2-D015', 'glider' => ' ASK21 ', 'rankBy' => 'penalties', 'rankingFrom' => '3', 'criteriaSet' => [
                ['criterion' => 'landed_on_airfield', 'value' => ''],
                ['criterion' => 'turn_balance', 'value' => ''],
            ]],
            ['position' => '3', 'type' => 'quiz', 'title' => 'Sans quiz', 'rankBy' => 'task_speed', 'rankingFrom' => '5'],
        ];
        $this->client->request('POST', (string) $form->getUri(), $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Choisissez le quiz.');
        self::assertSelectorTextContains('body', 'Donnez la valeur du critère « Spirales du côté le moins pratiqué ».');

        unset($values['PathStep']['items'][2]);
        $values['PathStep']['items'][1]['criteriaSet'][1]['value'] = '30';
        $this->client->request('POST', (string) $form->getUri(), $values);
        self::assertResponseRedirects();

        $this->entityManager->clear();
        $step = $this->entityManager->getRepository(PathStep::class)->findOneBy(['step' => TrainingStep::Solo]);
        self::assertTrue($step?->isOpen());
        self::assertSame('Voler seul un circuit court.', $step->getIntro());
        $items = $step->getItems()->toArray();
        self::assertSame([PathItemType::Exercise, PathItemType::Quiz], array_map(static fn ($item) => $item->getType(), $items), 'Items in their order');
        self::assertSame('C2-d015', $items[0]->getFlightPlan());
        self::assertSame(3, $items[0]->getRankingFrom());
        self::assertSame('ASK21', $items[0]->getGlider());
        self::assertSame([[ExerciseCriterion::LandedOnAirfield, null], [ExerciseCriterion::TurnBalance, 30.0]], array_map(static fn (array $row): array => [$row['criterion'], $row['value']], $items[0]->getCriteria()));
        self::assertSame(ExerciseCriterion::Penalties, $items[0]->getRankBy(), 'Fewest penalties first');
        self::assertSame([[ExerciseCriterion::TaskFinished, null]], array_map(static fn (array $row): array => [$row['criterion'], $row['value']], $items[1]->getCriteria()), 'Without criteria, a finished task');
    }
}
