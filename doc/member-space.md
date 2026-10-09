# Member space and contact

The member space (`/mon-compte`, roadmap step 9a) gathers what a logged-in account does on the site;
the contact form (step 9b) is open to everyone. Design: Figma page "Espace membre" (node `85-2`,
desktop frames `90:7089` to `90:9390`; the mobile frames were not finished when it was built).

Every page extends `templates/account/_layout.html.twig`: a side column (name, Condor CN and versions,
role, membership state, navigation, contact, back-office for `ADMIN_ACCESS`, logout) and the page
header. Styles: `assets/styles/pages/member-space.css`.

## Pages

| URL | Route | Content |
|---|---|---|
| `/mon-compte` | `app_account` | Dashboard: membership state and its next action, the 3 latest flights, task speed of the last finished tasks, latest badges, the latest advice, flights to comment (trainers, `FLIGHT_REVIEW`), latest comments on one's flights, shortcuts |
| `/mon-compte/vols` | `app_account_flights` | Figures of the season; filters (season, landscape, flight plan, task finished, visibility) and sort (date, task speed, distance); 10 flights per page with "Voir", "Rendre public / privé", "Supprimer" |
| `/mon-compte/carnet` | `app_account_logbook` | The logbook (`App\Flight\Logbook`, see [flights](flights.md#progression)) |
| `/mon-compte/adhesion` | `app_account_membership` | Membership request and its follow-up (see [membership](membership.md)) |
| `/mon-compte/profil` | `app_account_profile` | One form per panel (`App\Form\Account\ProfileForms`): display name; e-mail (change confirmed by a signed link sent to the new address, `app_account_email_confirm`, throttled); password (current one required); Condor (CN, pilot name, versions); e-mail preferences (`UserNotification`) |
| `/mon-compte/confidentialite` | `app_account_privacy` | New flights public by default or not, appear in the season ranking or not; export of the data; link to the deletion |
| `/mon-compte/donnees` | `app_account_export` | POST: ZIP of the account's data (`App\Account\DataExporter`): account, memberships, flights and their track files, comments, messages to the club, a read-me |
| `/mon-compte/suppression`, `.../confirmer` | `app_account_delete`, `app_account_delete_confirm` | Deletion in two steps (below) |
| `/compte-supprime` | `app_account_deleted` | Page shown after the deletion |
| `/contact`, `/mon-compte/contact` | `app_contact`, `app_account_contact` | Contact form (below); a logged-in user is sent to the second one |

`/vols/carnet` redirects (301) to `/mon-compte/carnet`; `/adhesion` stays the public page of the fees
and sends a logged-in user to `/mon-compte/adhesion`.

## E-mail preferences

`User::$notifications` holds the `UserNotification` values the member wants: a comment on one of
their flights (`FlightMailer`), a new message (for the messages of step 9c), the renewal reminder
(`app:membership:remind` skips the others) and club news (not sent yet). Security and confirmation
e-mails are always sent. New accounts get everything but the news.

## Account deletion (GDPR)

1. The member chooses what becomes of their flights: deleted, or kept anonymised (pilot
   "Pilote anonyme", private, track file removed, so club records keep their figures).
2. They confirm with their password and a checkbox; `App\Account\AccountDeleter` then removes their
   comments, their contact messages and the track files, anonymises or deletes the flights,
   anonymises their memberships (kept for the books, with name and amounts but without contact data)
   and deletes the account, then logs them out.

The last account holding every permission cannot delete itself (the club would lose its
administration).

## Contact form

Fields: name, e-mail (prefilled when logged in), subject (`ContactSubject`), message (10 to 5,000
characters), plus a hidden honeypot field (`website`): a filled one looks sent but is dropped. Five
messages per hour per IP (`contact` rate limiter). Each message is stored (`ContactRequest`, linked to
the account when logged in) and e-mailed to `CONTACT_EMAIL` with the sender as reply-to
(`ContactMailer`). The committee reads and processes them in the back-office, "Messages de contact"
(`CONTACT_MANAGE`, see [back-office](admin.md)).
