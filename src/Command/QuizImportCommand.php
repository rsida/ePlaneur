<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Quiz;
use App\Repository\QuizRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Creates the quizzes of a file (config/training/quiz_drafts.yaml, doc/training-path.md#theory-quizzes)
 * in the back-office, titled "[Brouillon] …" and placed in no step: no pilot sees them until a
 * trainer has reviewed one, renamed it and added it to a step. A quiz whose title exists is left
 * alone, unless --replace (which overwrites what a trainer changed).
 */
#[AsCommand(name: 'app:quiz:import', description: 'Create the draft quizzes of a YAML file in the back-office')]
final readonly class QuizImportCommand
{
    public const string DRAFT = '[Brouillon] ';

    public function __construct(
        private QuizRepository $quizzes,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'YAML file of quizzes')] string $file,
        #[Option(description: 'Overwrite the quizzes that already exist with the same title')] bool $replace = false,
        #[Option(description: 'Check the file without saving')] bool $dryRun = false,
    ): int {
        if (!is_file($file)) {
            $io->error(\sprintf('No file %s.', $file));

            return Command::FAILURE;
        }
        /** @var array{quizzes?: list<array{step?: int, title: string, intro?: ?string, passMark?: int, review?: ?string, questions: list<array{question: string, choices: list<string>, answer: int, explanation?: ?string}>}>} $data */
        $data = Yaml::parseFile($file);
        $failed = false;
        foreach ($data['quizzes'] ?? [] as $entry) {
            $title = self::DRAFT.$entry['title'];
            $quiz = $this->quizzes->findOneBy(['title' => $title]);
            $exists = null !== $quiz;
            if ($exists && !$replace) {
                $io->writeln(\sprintf('Kept: %s (already there; --replace to overwrite)', $title));
                continue;
            }
            $quiz ??= new Quiz();
            $quiz->setTitle($title)->setIntro($entry['intro'] ?? null)->setPassMark($entry['passMark'] ?? 80)->setQuestions(array_map(
                // The file counts the answers from 1, as the back-office does
                static fn (array $question): array => ['answer' => $question['answer'] - 1] + $question,
                $entry['questions'],
            ));
            $violations = $this->validator->validate($quiz);
            if (\count($violations) > 0) {
                $failed = true;
                foreach ($violations as $violation) {
                    $io->error(\sprintf('%s: %s', $title, $violation->getMessage()));
                }
                continue;
            }
            if (!$dryRun) {
                $this->entityManager->persist($quiz);
            }
            $io->writeln(\sprintf('%s: %s (%d questions)%s', $exists ? 'Replaced' : 'Created', $title, $quiz->getQuestionCount(), null !== ($entry['review'] ?? null) ? "\n  To review: ".$entry['review'] : ''));
        }
        if (!$dryRun) {
            $this->entityManager->flush();
        }
        if ($failed) {
            return Command::FAILURE;
        }
        $io->success($dryRun ? 'File checked, nothing saved (dry run).' : 'Done: the quizzes wait in the back-office ("Quiz"), placed in no step.');

        return Command::SUCCESS;
    }
}
