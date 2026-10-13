<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\QuizRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * A theory quiz of "Mon parcours", written in the back-office: questions with several answers, one
 * of them right, and the explanation shown once answered. Passed with the pass mark (a share of
 * right answers).
 *
 * @phpstan-type Question array{question: string, choices: list<string>, answer: int, explanation: ?string}
 */
#[ORM\Entity(repositoryClass: QuizRepository::class)]
class Quiz
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $intro = null;

    /** Share of right answers to pass, in percent. */
    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: 1, max: 100)]
    private int $passMark = 80;

    /** @var list<Question> */
    #[ORM\Column(type: Types::JSON)]
    #[Assert\Count(min: 1, minMessage: 'Ajoutez au moins une question.')]
    private array $questions = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = trim((string) $title);

        return $this;
    }

    public function getIntro(): ?string
    {
        return $this->intro;
    }

    public function setIntro(?string $intro): static
    {
        $this->intro = null !== $intro && '' !== trim($intro) ? trim($intro) : null;

        return $this;
    }

    public function getPassMark(): int
    {
        return $this->passMark;
    }

    public function setPassMark(?int $passMark): static
    {
        $this->passMark = $passMark ?? 80;

        return $this;
    }

    /** @return list<Question> */
    public function getQuestions(): array
    {
        return $this->questions;
    }

    /**
     * @param iterable<array{question?: ?string, choices?: list<string>, answer?: ?int, explanation?: ?string}> $questions
     */
    public function setQuestions(iterable $questions): static
    {
        $this->questions = [];
        foreach ($questions as $question) {
            $this->questions[] = [
                'question' => trim((string) ($question['question'] ?? '')),
                'choices' => array_values(array_filter(array_map(trim(...), $question['choices'] ?? []), static fn (string $choice): bool => '' !== $choice)),
                'answer' => (int) ($question['answer'] ?? 0),
                'explanation' => null !== ($question['explanation'] ?? null) && '' !== trim((string) $question['explanation']) ? trim((string) $question['explanation']) : null,
            ];
        }

        return $this;
    }

    public function getQuestionCount(): int
    {
        return \count($this->questions);
    }

    /**
     * Score of a set of answers, in percent: $answers gives the index of the chosen answer of each
     * question, by its index.
     *
     * @param array<int, ?int> $answers
     */
    public function score(array $answers): int
    {
        if ([] === $this->questions) {
            return 0;
        }
        $right = 0;
        foreach ($this->questions as $i => $question) {
            if (($answers[$i] ?? null) === $question['answer']) {
                ++$right;
            }
        }

        return (int) floor(100 * $right / \count($this->questions));
    }

    public function isPassedWith(int $score): bool
    {
        return $score >= $this->passMark;
    }

    #[Assert\Callback]
    public function validateQuestions(ExecutionContextInterface $context): void
    {
        foreach ($this->questions as $i => $question) {
            if ('' === $question['question']) {
                $context->buildViolation('Question {{ n }} : écrivez la question.')->setParameter('{{ n }}', (string) ($i + 1))->atPath('questions')->addViolation();
            }
            if (\count($question['choices']) < 2) {
                $context->buildViolation('Question {{ n }} : donnez au moins deux réponses.')->setParameter('{{ n }}', (string) ($i + 1))->atPath('questions')->addViolation();
            } elseif ($question['answer'] < 0 || $question['answer'] >= \count($question['choices'])) {
                $context->buildViolation('Question {{ n }} : la bonne réponse doit être l’une des réponses proposées.')->setParameter('{{ n }}', (string) ($i + 1))->atPath('questions')->addViolation();
            }
        }
    }

    public function __toString(): string
    {
        return $this->title;
    }
}
