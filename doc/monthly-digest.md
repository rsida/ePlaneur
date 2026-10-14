# Monthly digest

Once a month, an e-mail to the accounts that asked for it: the month of the club (roadmap 11h). It
is the preference "La lettre mensuelle du club" of "Mon profil" > "Préférences e-mail"
(`UserNotification::News`), off by default.

## Content

`App\Digest\MonthlyDigest`, template `templates/email/monthly_digest.html.twig`:

| Part | Source |
|---|---|
| Le mois en chiffres | `ActivityReport::month()` ([activity](activity.md)): sessions, flights uploaded and hours, active pilots, steps validated, new accounts |
| Records du club | Fastest finish of a flight plan (`ClubRecords::byTask()`) flown in the month, by a pilot who appears in rankings |
| Prochains vols en réseau | The next six sessions from the day it is sent, time of Paris |
| Actualités | Posts published in the month that the reader may see (`PostRepository::paginateNews()`, with their rights) |

Then "Continuer mon parcours" and how to stop it. A month with no session, flight, record or step
validated, and no coming session, sends nothing.

## Sending

```bash
make console c="app:digest:send"                      # the last month
make console c="app:digest:send --month=2026-09"      # a given month
make console c="app:digest:send --dry-run"            # who would get it
```

Only verified accounts get it. Run it once a month, on the first day (cron in
[production](production.md)): a second run sends it again. In production the e-mails go through the
messenger worker.
