<?php

declare(strict_types=1);

namespace App\Entity;

use App\Training\CriterionRow;
use App\Training\ExerciseCriterion;
use App\Training\PathItemType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Something to do in a step of "Mon parcours": read or watch, fly an exercise, pass a quiz, or be
 * validated by a trainer (PathItemType).
 */
#[ORM\Entity]
class PathItem
{
    /** An exercise shows its ranking once this many pilots finished it, unless set otherwise. */
    public const int RANKING_FROM = 5;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Null only while the item is being added in the back-office (PathStep::addItem() sets it) */
    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    // @phpstan-ignore doctrine.associationType
    private ?PathStep $step = null;

    #[ORM\Column]
    private int $position = 0;

    /** Null only in a form being filled (the column is not nullable) */
    #[ORM\Column(length: 20, enumType: PathItemType::class)]
    #[Assert\NotNull(message: 'Choisissez le type de l’élément.')]
    // @phpstan-ignore doctrine.columnType
    private ?PathItemType $type = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $title = '';

    /** What to do and why, shown with the item. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $text = null;

    /** Page, document or video to read or watch (an address of the site or of another one). */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Assert\AtLeastOneOf([new Assert\Url(requireTld: true), new Assert\Regex('#^/[^/]#')], message: 'Une adresse complète (https://…) ou une adresse du site commençant par /.')]
    private ?string $url = null;

    /** Code of the flight plan of an exercise (C2-d015...): a finished task on it completes the item. */
    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Regex('/^C[23]-[a-z]\d{3}[a-z]?$/', message: 'Un code de plan de vol comme C2-d015.')]
    private ?string $flightPlan = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Quiz $quiz = null;

    /**
     * What the analysis checks on a flight of an exercise (ExerciseCheck); none: a finished task.
     *
     * @var list<array{criterion: string, value: ?float}>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $criteria = [];

    /** Glider the exercise is flown with, as Condor names it (ASK21); null: any. */
    #[ORM\Column(length: 60, nullable: true)]
    #[Assert\Length(max: 60)]
    private ?string $glider = null;

    /** What ranks the flights that pass the exercise. */
    #[ORM\Column(length: 30, enumType: ExerciseCriterion::class)]
    private ExerciseCriterion $rankBy = ExerciseCriterion::TaskSpeed;

    /** Pilots who must have passed an exercise before its ranking shows. */
    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: 1, max: 100)]
    private int $rankingFrom = self::RANKING_FROM;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStep(): ?PathStep
    {
        return $this->step;
    }

    public function setStep(?PathStep $step): static
    {
        $this->step = $step;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(?int $position): static
    {
        $this->position = $position ?? 0;

        return $this;
    }

    public function getType(): ?PathItemType
    {
        return $this->type;
    }

    public function setType(?PathItemType $type): static
    {
        $this->type = $type;

        return $this;
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

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(?string $text): static
    {
        $this->text = null !== $text && '' !== trim($text) ? trim($text) : null;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = null !== $url && '' !== trim($url) ? trim($url) : null;

        return $this;
    }

    public function getFlightPlan(): ?string
    {
        return $this->flightPlan;
    }

    public function setFlightPlan(?string $flightPlan): static
    {
        $this->flightPlan = null !== $flightPlan && '' !== trim($flightPlan) ? FlightPlan::codeIn(trim($flightPlan)) ?? trim($flightPlan) : null;

        return $this;
    }

    public function getQuiz(): ?Quiz
    {
        return $this->quiz;
    }

    public function setQuiz(?Quiz $quiz): static
    {
        $this->quiz = $quiz;

        return $this;
    }

    public function getRankingFrom(): int
    {
        return $this->rankingFrom;
    }

    public function setRankingFrom(?int $rankingFrom): static
    {
        $this->rankingFrom = $rankingFrom ?? self::RANKING_FROM;

        return $this;
    }

    /**
     * The criteria of an exercise, a finished task when none is set.
     *
     * @return list<array{criterion: ExerciseCriterion, value: ?float}>
     */
    public function getCriteria(): array
    {
        $criteria = array_values(array_filter(array_map(
            static fn (array $stored): ?array => null !== ($criterion = ExerciseCriterion::tryFrom($stored['criterion'])) ? ['criterion' => $criterion, 'value' => $stored['value']] : null,
            $this->criteria,
        )));

        return [] !== $criteria ? $criteria : [['criterion' => ExerciseCriterion::TaskFinished, 'value' => null]];
    }

    /**
     * The criteria as the back-office edits them (none: a finished task).
     *
     * @return list<CriterionRow>
     */
    public function getCriteriaSet(): array
    {
        return [] !== $this->criteria ? array_map(static fn (array $row): CriterionRow => new CriterionRow($row['criterion'], $row['value']), $this->getCriteria()) : [];
    }

    /**
     * @param iterable<CriterionRow|array{criterion?: ExerciseCriterion|string|null, value?: int|float|string|null}> $criteria
     */
    public function setCriteriaSet(iterable $criteria): static
    {
        $this->criteria = [];
        foreach ($criteria as $row) {
            $row = $row instanceof CriterionRow ? ['criterion' => $row->criterion, 'value' => $row->value] : $row;
            $criterion = $row['criterion'] ?? null;
            $criterion = \is_string($criterion) ? ExerciseCriterion::tryFrom($criterion) : $criterion;
            if (null === $criterion) {
                continue;
            }
            $value = $row['value'] ?? null;
            $this->criteria[] = ['criterion' => $criterion->value, 'value' => $criterion->isNumeric() && null !== $value && '' !== $value ? (float) $value : null];
        }

        return $this;
    }

    public function getGlider(): ?string
    {
        return $this->glider;
    }

    public function setGlider(?string $glider): static
    {
        $this->glider = null !== $glider && '' !== trim($glider) ? trim($glider) : null;

        return $this;
    }

    public function getRankBy(): ExerciseCriterion
    {
        return $this->rankBy;
    }

    public function setRankBy(?ExerciseCriterion $rankBy): static
    {
        $this->rankBy = null !== $rankBy && $rankBy->isNumeric() ? $rankBy : ExerciseCriterion::TaskSpeed;

        return $this;
    }

    /** Done by the pilot's own word: a reading, or an exercise without flight plan. */
    public function isDeclared(): bool
    {
        return PathItemType::Reading === $this->type || (PathItemType::Exercise === $this->type && null === $this->flightPlan);
    }

    #[Assert\Callback]
    public function validateQuiz(ExecutionContextInterface $context): void
    {
        foreach ($this->getCriteria() as ['criterion' => $criterion, 'value' => $value]) {
            if ($criterion->isNumeric() && null === $value && [] !== $this->criteria) {
                $context->buildViolation('Donnez la valeur du critère « {{ label }} ».')->setParameter('{{ label }}', $criterion->label())->atPath('criteriaSet')->addViolation();
            }
        }
        if (PathItemType::Quiz === $this->type && null === $this->quiz) {
            $context->buildViolation('Choisissez le quiz.')->atPath('quiz')->addViolation();
        }
    }

    public function __toString(): string
    {
        return $this->title;
    }
}
