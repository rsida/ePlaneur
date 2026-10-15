<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Command\QuizImportCommand;
use App\Entity\PathItem;
use App\Entity\Quiz;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The draft theory quizzes of the project file: valid, created in no step, kept once a trainer edits
 * them.
 */
final class QuizImportTest extends KernelTestCase
{
    private const string FILE = 'config/training/quiz_drafts.yaml';

    public function testTheDraftQuizzesWaitInTheBackOffice(): void
    {
        self::bootKernel();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $command = new CommandTester((new Application(self::$kernel ?? throw new \LogicException()))->find('app:quiz:import'));

        $command->execute(['file' => self::FILE, '--dry-run' => true]);
        $command->assertCommandIsSuccessful();
        self::assertSame(0, $entityManager->getRepository(Quiz::class)->count([]));

        $command->execute(['file' => self::FILE]);
        $command->assertCommandIsSuccessful();
        $quizzes = $entityManager->getRepository(Quiz::class)->findBy([], ['id' => 'ASC']);
        self::assertCount(8, $quizzes, 'One per step');
        self::assertSame(QuizImportCommand::DRAFT.'Étape 1 · Les commandes et les instruments', $quizzes[0]->getTitle());
        $first = $quizzes[0]->getQuestions()[0];
        self::assertSame('L\'axe de tangage', $first['choices'][$first['answer']], 'Answers counted from 1 in the file');
        self::assertSame(0, $entityManager->getRepository(PathItem::class)->count([]), 'Placed in no step');

        $quizzes[0]->setPassMark(90);
        $entityManager->flush();
        $command->execute(['file' => self::FILE]);
        self::assertStringContainsString('Kept: '.QuizImportCommand::DRAFT.'Étape 1', $command->getDisplay());
        $entityManager->refresh($quizzes[0]);
        self::assertSame(90, $quizzes[0]->getPassMark(), 'What a trainer changed stays');
        self::assertSame(8, $entityManager->getRepository(Quiz::class)->count([]));
    }
}
