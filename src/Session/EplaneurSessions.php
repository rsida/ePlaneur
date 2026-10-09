<?php

declare(strict_types=1);

namespace App\Session;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads the network sessions published on eplaneur.fr (pages of its "Afficher sessions" and
 * "Résultats session" tools), to bring their history to the club site.
 */
final readonly class EplaneurSessions
{
    public const string BASE_URL = 'https://eplaneur.fr/tools/';

    /** Values of the "Session dédiée" filter of eplaneur.fr and their names. */
    public const array DEDICATED = [
        'Acph' => 'ACPH',
        'Compétition' => 'Compétition',
        'Démonstration' => 'Démonstration',
        'Epitech' => 'Epitech',
        'FDU' => 'Frédéric Dupin',
        'JC6' => 'Jacques Causin',
    ];

    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    /**
     * Every session of the list, the latest first; with a value of DEDICATED, only those sessions.
     *
     * @return list<array{file: string, folder: string, date: \DateTimeImmutable, code: string, participants: int, title: ?string, gliders: ?string, landscape: ?string}>
     */
    public function sessions(?string $dedicated = null): array
    {
        $html = $this->httpClient->request('GET', self::BASE_URL.'afficher-sessions.php', [
            'query' => null !== $dedicated ? ['session_dediee' => $dedicated] : [],
            'headers' => ['User-Agent' => CondorClubTasks::USER_AGENT],
        ])->getContent();

        return self::parseSessions($html);
    }

    /**
     * Results of a session, from its file name ("DSRace-971793592-on-2026-10-08") and folder ("C3").
     *
     * @return list<array{rank: int, status: string, player: string, competitionId: ?string, registration: ?string, glider: ?string, distance: ?int, time: ?int, speed: ?float, penalty: ?float, score: ?float}>
     */
    public function results(string $file, string $folder): array
    {
        return SessionResults::fromHtml($this->httpClient->request('GET', self::BASE_URL.'lire-tableau-csv-resultats-session.php', [
            'query' => ['file' => $file, 'folder' => $folder],
            'headers' => ['User-Agent' => CondorClubTasks::USER_AGENT],
        ])->getContent());
    }

    /**
     * @return list<array{file: string, folder: string, date: \DateTimeImmutable, code: string, participants: int, title: ?string, gliders: ?string, landscape: ?string}>
     */
    public static function parseSessions(string $html): array
    {
        $sessions = [];
        preg_match_all('#<tr[^>]*>(.*?)</tr>#si', $html, $rows);
        foreach ($rows[1] as $row) {
            if (!preg_match('#file=([A-Za-z]*Race-\d+-on-\d{4}-\d{2}-\d{2})&(?:amp;)?folder=(C[23])#', $row, $file)) {
                continue;
            }
            preg_match_all('#<td[^>]*>(.*?)</td>#si', $row, $cells);
            $text = array_map(static fn (string $cell): string => trim(html_entity_decode(strip_tags($cell), \ENT_QUOTES | \ENT_HTML5, 'UTF-8')), $cells[1]);
            $date = \DateTimeImmutable::createFromFormat('!d/m/Y', $text[1] ?? '');
            if (false === $date || '' === ($text[2] ?? '')) {
                continue;
            }
            $sessions[] = [
                'file' => $file[1],
                'folder' => $file[2],
                'date' => $date,
                'code' => $text[2],
                'participants' => (int) ($text[3] ?? 0),
                'title' => ($text[4] ?? '') ?: null,
                'gliders' => ($text[5] ?? '') ?: null,
                'landscape' => ($text[6] ?? '') ?: null,
            ];
        }

        return $sessions;
    }
}
