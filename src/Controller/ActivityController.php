<?php

declare(strict_types=1);

namespace App\Controller;

use App\Activity\ActivityMonth;
use App\Activity\ActivityReport;
use App\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Activity of the club (roadmap 11i, doc/activity.md): month by month, for the committee and the
 * general meeting, the last twelve months or a calendar year (`?annee=`), and its CSV export.
 */
#[IsGranted(Permission::ActivityView->value)]
final class ActivityController extends AbstractController
{
    public function __construct(
        private readonly ActivityReport $report,
    ) {
    }

    #[Route('/activite', name: 'app_activity', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $year = $this->year($request);
        $report = $this->report->of($year);

        return $this->render('activity/index.html.twig', $report + [
            'year' => $year,
            'years' => $this->report->years(),
            'labels' => ActivityMonth::LABELS,
        ]);
    }

    #[Route('/activite.csv', name: 'app_activity_csv', methods: ['GET'])]
    public function csv(Request $request): Response
    {
        $year = $this->year($request);
        $report = $this->report->of($year);
        $response = new StreamedResponse(static function () use ($report): void {
            $out = fopen('php://output', 'w');
            \assert(false !== $out);
            // Excel reads UTF-8 with its byte order mark; semicolons for the French locale
            fwrite($out, "\u{FEFF}");
            $number = static fn (int|float $value): string => \is_float($value) ? number_format($value, 1, ',', '') : (string) $value;
            fputcsv($out, ['Mois', ...array_values(ActivityMonth::LABELS)], ';', '"', '');
            foreach ($report['months'] as $month) {
                fputcsv($out, [$month->month?->format('m/Y'), ...array_map($number, array_values($month->values()))], ';', '"', '');
            }
            fputcsv($out, ['Total', ...array_map($number, array_values($report['total']->values()))], ';', '"', '');
            fclose($out);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', \sprintf('attachment; filename="activite-club-eplaneur-%s.csv"', $year ?? $report['to']->format('Y-m')));

        return $response;
    }

    /** The calendar year asked, null for the last twelve months. */
    private function year(Request $request): ?int
    {
        $year = $request->query->getInt('annee');

        return \in_array($year, $this->report->years(), true) ? $year : null;
    }
}
