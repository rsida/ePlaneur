<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\FlightPlanRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A flight plan (FPL) the club has flown: its code in the eplaneur.fr library ("C3-c046a") and the
 * address of its sheet there. The library itself stays on eplaneur.fr; this record only names the
 * flight plan of the sessions and joins the tracks flown on it (their Condor task name starts with
 * the code: "C3-c060_Laragne-Ventoux-Fayence_D2_AA3 ePlaneur").
 */
#[ORM\Entity(repositoryClass: FlightPlanRepository::class)]
#[UniqueEntity('code', message: 'Ce plan de vol est déjà enregistré.')]
class FlightPlan
{
    /** Code of a flight plan of the library: Condor version, category letter, number, variant. */
    public const string CODE_PATTERN = '/(?<![A-Za-z0-9])(C[23]-[a-z]\d{3}[a-z]?)(?![A-Za-z0-9])/i';

    /** Sheet of a flight plan on eplaneur.fr, suggested for a new code. */
    public const string SHEET_URL = 'https://eplaneur.fr/afficher-fpl/?mode=simple&fpl=%s';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Regex('/^C[23]-[a-z]\d{3}[a-z]?$/', message: 'Le code suit la forme de la bibliothèque eplaneur.fr, par exemple C3-c046a.')]
    private string $code;

    /** Route, e.g. "CdF2025V2-J1R1-StAuban-Challes". */
    #[ORM\Column(length: 160, nullable: true)]
    #[Assert\Length(max: 160)]
    private ?string $title = null;

    /** Glider class, e.g. "D2", "15m". */
    #[ORM\Column(length: 40, nullable: true)]
    #[Assert\Length(max: 40)]
    private ?string $gliders = null;

    /** Condor landscape, e.g. "AA3". */
    #[ORM\Column(length: 40, nullable: true)]
    #[Assert\Length(max: 40)]
    private ?string $landscape = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url(requireTld: true)]
    #[Assert\Length(max: 255)]
    private ?string $sheetUrl = null;

    public function __construct(string $code)
    {
        $this->code = $code;
    }

    /**
     * The code of the flight plan in a text (a Condor task name, what someone typed), normalised:
     * "c3-C046A" gives "C3-c046a".
     */
    public static function codeIn(?string $text): ?string
    {
        if (null === $text || !preg_match(self::CODE_PATTERN, $text, $match)) {
            return null;
        }

        return strtoupper(substr($match[1], 0, 2)).'-'.strtolower(substr($match[1], 3));
    }

    /**
     * What a Condor task name of the club tells beyond the code:
     * "C3-c060_Laragne-Ventoux-Fayence_D2_AA3 ePlaneur" gives the route, the gliders and the landscape.
     *
     * @return array{title: ?string, gliders: ?string, landscape: ?string}
     */
    public static function describe(string $taskName): array
    {
        $name = trim((string) preg_replace('/\s+ePlaneur$/i', '', trim($taskName)));
        $parts = explode('_', $name);
        if (null === self::codeIn($parts[0]) || \count($parts) < 2) {
            return ['title' => null, 'gliders' => null, 'landscape' => null];
        }

        return [
            'title' => '' !== $parts[1] ? $parts[1] : null,
            'gliders' => $parts[2] ?? null,
            'landscape' => isset($parts[3]) && '' !== trim($parts[3]) ? trim($parts[3]) : null,
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    /** Condor version of the flight plan: 2 or 3. */
    public function getCondorVersion(): int
    {
        return (int) $this->code[1];
    }

    /** Category of the library, from the letter of the code: d, s or c. */
    public function getCategory(): string
    {
        return match ($this->code[3] ?? '') {
            'd' => 'Initiation',
            'c' => 'Compétition',
            default => 'Standard',
        };
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getGliders(): ?string
    {
        return $this->gliders;
    }

    public function setGliders(?string $gliders): static
    {
        $this->gliders = $gliders;

        return $this;
    }

    public function getLandscape(): ?string
    {
        return $this->landscape;
    }

    public function setLandscape(?string $landscape): static
    {
        $this->landscape = $landscape;

        return $this;
    }

    public function getSheetUrl(): ?string
    {
        return $this->sheetUrl;
    }

    public function setSheetUrl(?string $sheetUrl): static
    {
        $this->sheetUrl = $sheetUrl;

        return $this;
    }

    /** "C3-c046a · CdF2025V2-J1R1-StAuban-Challes". */
    public function getLabel(): string
    {
        return null !== $this->title ? $this->code.' · '.$this->title : $this->code;
    }

    public function __toString(): string
    {
        return $this->getLabel();
    }
}
