# Activity of the club

`/activite` (roadmap 11i) shows what the members do on the site, month by month, to the committee and
for the general meeting: what the site brings, in figures. Nothing new is recorded: the page reads the
dates the site already stores (`App\Activity\ActivityReport`). It is linked from the back-office
menu and from the member space ("Activité du club"), for whoever holds `ACTIVITY_VIEW`.

## Access

`ACTIVITY_VIEW` ("Voir l'activité du club mois par mois et l'exporter"), granted to the committee
(migration `Version20261014200000`, `DefaultGroup`) and to administrators. The back-office links to
the page from the "Comptes" section of its menu and from a card of its home.

## Period

The last twelve months, the current one included, by default; or a calendar year (`?annee=`, the
years from the first account to now), up to the current month.

## Indicators

| Indicator | Counted in the month of |
|---|---|
| Comptes créés | The creation of the account |
| Adhésions validées | The validation of the membership by the committee |
| Vols déposés | The flight: date of the track, else of its session, else the day of the upload |
| Heures de vol | Same, the durations added up (to the tenth of an hour) |
| Pilotes actifs | Accounts with at least one flight in the month; over the period, each pilot counts once |
| Sessions organisées | The date of the session (planned, imported from eplaneur.fr or proposed on the site) |
| Participations aux sessions (traces déposées) | The flight: tracks joined to a session |
| Étapes validées | The last change of a step of the training path validated by a trainer |
| Éléments de Mon parcours faits | The item done (reading, quiz, exercise said done) |
| Commentaires des formateurs | The comment on a flight |

Flights flown without uploading the track do not appear: the figures show the use of the site, a
floor of the activity of the club.

## Page

Key figures of the period, four charts (one point per month, a legend for several curves, the exact
values under the cursor: `assets/controllers/activity_chart_controller.js`), then the table month by
month, the latest first, with the total.

## Export

`/activite.csv` (same `?annee=`): one row per month and the total, semicolons and UTF-8 with its byte
order mark for Excel in French, decimal comma for the hours. File `activite-club-eplaneur-2026.csv`,
or named after the last month for the last twelve months.
