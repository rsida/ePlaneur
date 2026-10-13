<?php

declare(strict_types=1);

namespace App\Session;

use App\Entity\NetworkSession;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Network sessions as iCalendar (RFC 5545), for "Ajouter à mon agenda" and the subscription to every
 * session: times are given in UTC, so that every calendar shows them in its own time zone (La
 * Réunion); a session without time is a whole-day event.
 */
final readonly class SessionCalendar
{
    /** Briefing, connection during the stop join and a task of about two hours. */
    public const string DURATION = 'PT2H30M';

    public function __construct(
        private UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * @param list<NetworkSession> $sessions
     */
    public function calendar(array $sessions, string $name, \DateTimeImmutable $now): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Club ePlaneur//Sessions//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.self::text($name),
            'X-PUBLISHED-TTL:PT6H',
            'REFRESH-INTERVAL;VALUE=DURATION:PT6H',
        ];
        foreach ($sessions as $session) {
            array_push($lines, ...$this->event($session, $now));
        }
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(self::fold(...), $lines))."\r\n";
    }

    /**
     * @return list<string>
     */
    private function event(NetworkSession $session, \DateTimeImmutable $now): array
    {
        $plan = $session->getFlightPlan();
        $url = $this->urls->generate('app_session_show', ['id' => $session->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        $utc = new \DateTimeZone('UTC');
        $when = null !== $session->getStartTime()
            ? ['DTSTART:'.$session->getStartsAt()->setTimezone($utc)->format('Ymd\THis\Z'), 'DURATION:'.self::DURATION]
            : ['DTSTART;VALUE=DATE:'.$session->getDate()->format('Ymd'), 'DTEND;VALUE=DATE:'.$session->getDate()->modify('+1 day')->format('Ymd')];
        $details = array_filter([
            'Condor '.$plan->getCondorVersion().' · '.$plan->getCategory().(null !== $session->getDedicatedTo() ? ' · session dédiée '.$session->getDedicatedTo() : ''),
            $plan->getGliders() ? 'Planeurs : '.$plan->getGliders() : null,
            $plan->getLandscape() ? 'Scène : '.$plan->getLandscape() : null,
            null !== $session->getStartTime() ? 'Briefing sur Discord à '.$session->getStartTime()->format('H\hi').' (heure de Paris)' : null,
            $session->getNotes(),
            $url,
        ]);

        return [
            'BEGIN:VEVENT',
            'UID:session-'.$session->getId().'@club.eplaneur.fr',
            'DTSTAMP:'.$now->setTimezone($utc)->format('Ymd\THis\Z'),
            ...$when,
            'SUMMARY:'.self::text('Vol en réseau ePlaneur · '.$plan->getLabel()),
            'DESCRIPTION:'.self::text(implode("\n", $details)),
            'URL:'.$url,
            'END:VEVENT',
        ];
    }

    /** Escapes a text value: backslash, semicolon, comma and line breaks. */
    private static function text(string $value): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $value);
    }

    /** Lines longer than 75 octets go on, after a space, on the next line (without cutting a character). */
    private static function fold(string $line): string
    {
        $parts = [];
        while (\strlen($line) > 75) {
            $cut = 75;
            while ($cut > 0 && 0x80 === (\ord($line[$cut]) & 0xC0)) {
                --$cut;
            }
            $parts[] = substr($line, 0, $cut);
            $line = ' '.substr($line, $cut);
        }
        $parts[] = $line;

        return implode("\r\n", $parts);
    }
}
