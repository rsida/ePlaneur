<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Controller\Admin\DashboardController;
use App\Controller\Admin\QuizCrudController;
use App\Entity\Quiz;
use EasyCorp\Bundle\EasyAdminBundle\Test\AbstractCrudTestCase;

/**
 * The quiz builder: questions with their answers one per line and the number of the right one.
 *
 * @extends AbstractCrudTestCase<QuizCrudController>
 */
final class QuizCrudControllerTest extends AbstractCrudTestCase
{
    use AdminUsers;

    protected function getControllerFqcn(): string
    {
        return QuizCrudController::class;
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

    public function testAQuizIsWrittenWithItsQuestions(): void
    {
        $crawler = $this->client->request('GET', $this->generateNewFormUrl());
        $form = $crawler->filter('form[name="Quiz"]')->form();
        $values = $form->getPhpValues();
        $values['Quiz']['title'] = 'Les instruments';
        $values['Quiz']['passMark'] = '75';
        $values['Quiz']['questions'] = [
            ['question' => 'Que mesure le variomètre ?', 'choices' => "La vitesse\n\nLa vitesse verticale\nL’altitude", 'answer' => '2', 'explanation' => 'Il montre si l’on monte ou descend.'],
            ['question' => 'Une seule réponse', 'choices' => 'Oui', 'answer' => '1'],
        ];
        $this->client->request('POST', (string) $form->getUri(), $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Question 2 : donnez au moins deux réponses.');

        $values['Quiz']['questions'][1] = ['question' => 'L’altimètre donne…', 'choices' => "La hauteur\nL’altitude", 'answer' => '3'];
        $this->client->request('POST', (string) $form->getUri(), $values);
        self::assertSelectorTextContains('body', 'Question 2 : la bonne réponse doit être l’une des réponses proposées.');

        $values['Quiz']['questions'][1]['answer'] = '2';
        $this->client->request('POST', (string) $form->getUri(), $values);
        self::assertResponseRedirects();

        $quiz = $this->entityManager->getRepository(Quiz::class)->findOneBy(['title' => 'Les instruments']);
        self::assertSame(75, $quiz?->getPassMark());
        self::assertSame(['question' => 'Que mesure le variomètre ?', 'choices' => ['La vitesse', 'La vitesse verticale', 'L’altitude'], 'answer' => 1, 'explanation' => 'Il montre si l’on monte ou descend.'], $quiz->getQuestions()[0], 'Empty lines are left out, the right answer counts from 0');
        self::assertSame(50, $quiz->score([1, 0]));

        $crawler = $this->client->request('GET', $this->generateEditFormUrl((int) $quiz->getId()));
        self::assertSame("La vitesse\nLa vitesse verticale\nL’altitude", $crawler->filter('textarea[name="Quiz[questions][0][choices]"]')->text(null, false));
        self::assertSame('2', $crawler->filter('input[name="Quiz[questions][0][answer]"]')->attr('value'));
    }
}
