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
| `/mon-compte` | `app_account` | Dashboard: membership state and its next action, the 3 latest flights, task speed of the last finished tasks, latest badges, the step of "Mon parcours" to work on and its badges earned, the latest advice, flights to comment (trainers, `FLIGHT_REVIEW`), latest comments on one's flights, shortcuts |
| `/mon-compte/vols` | `app_account_flights` | Figures of the season; filters (season, landscape, flight plan, task finished, visibility) and sort (date, task speed, distance); 10 flights per page with "Voir", "Rendre public / privé", "Supprimer" |
| `/mon-compte/carnet` | `app_account_logbook` | The training path and the logbook (`App\Flight\Logbook`, see [flights](flights.md#training-path)) |
| `/mon-compte/messages`, `.../nouveau`, `.../{id}` | `app_account_messages`, `app_account_message_new`, `app_account_message_show` | Messages (below): the conversations, a new one, a conversation and its answer form |
| `/mon-compte/adhesion` | `app_account_membership` | Membership request and its follow-up (see [membership](membership.md)) |
| `/mon-compte/profil` | `app_account_profile` | One form per panel (`App\Form\Account\ProfileForms`): display name; e-mail (change confirmed by a signed link sent to the new address, `app_account_email_confirm`, throttled); password (current one required); Condor (CN, pilot name, versions); e-mail preferences (`UserNotification`) |
| `/mon-compte/classes` | `app_account_classes` | Shown to teachers and to pupils in a class: the classes of the teacher and their progression, the classes joined and "Quitter la classe" (see [teacher space](teacher-space.md)) |
| `/mon-compte/confidentialite` | `app_account_privacy` | New flights public by default or not, appear in the season ranking or not; export of the data; link to the deletion |
| `/mon-compte/donnees` | `app_account_export` | POST: ZIP of the account's data (`App\Account\DataExporter`): account, memberships, flights and their track files, comments, messages to the club, messages to members, training path, what was done in "Mon parcours", a read-me |
| `/mon-compte/suppression`, `.../confirmer` | `app_account_delete`, `app_account_delete_confirm` | Deletion in two steps (below) |
| `/compte-supprime` | `app_account_deleted` | Page shown after the deletion |
| `/contact`, `/mon-compte/contact` | `app_contact`, `app_account_contact` | Contact form (below); a logged-in user is sent to the second one |

`/vols/carnet` redirects (301) to `/mon-compte/carnet`; `/adhesion` stays the public page of the fees
and sends a logged-in user to `/mon-compte/adhesion`.

## Messages

Roadmap step 9c (decision of 2026-10-10): members write to each other, to the committee or to the
administrators of the site.

- **Who writes**: the accounts holding `MESSAGE_SEND` (the `member` group, so the committee too);
  others see their former conversations but cannot write or answer. Twenty messages an hour per
  account (`message` rate limiter).
- **To whom** (`ConversationType`): a member (every other account holding `MESSAGE_SEND`, by name
  and CN), "Comité directeur" (group `committee`) or "Administrateurs du site" (group `admin`). A
  conversation with a group is read and answered by every account in that group (with the groups it
  includes); an administrator does not read the conversations of others (`ConversationVoter`).
- **About a flight**: "Écrire au pilote" on the page of a flight of another member opens a new
  message to them, about that flight (`?a=<user id>&vol=<flight id>`).
- **Model**: `Conversation` (subject, group written to, flight, date of the last message),
  `ConversationParticipant` (who takes part, when they last read it; a member of the group joins
  when they open it), `Message` (author, text up to 5,000 characters). `App\Messaging\Conversations`
  starts and answers them.
- **Unread**: a conversation is unread when a message from someone else came after the reader last
  opened it; the navigation of the member space shows how many (`unread_messages()`).
- **E-mail**: each reader who accepts "Un nouveau message" gets the message by e-mail
  (`MessageMailer`), with a link to answer on the site.
- **GDPR**: the export holds the messages written (`messages.json`); deleting an account deletes its
  messages, and the conversations left without any.

No moderation yet: nobody reads the conversations of others, and there is no way to report a
message (open question).

## E-mail preferences

`User::$notifications` holds the `UserNotification` values the member wants: a comment on one of
their flights (`FlightMailer`), a new message (`MessageMailer`), the renewal reminder
(`app:membership:remind` skips the others) "La lettre mensuelle du club" (`News`, the
[monthly digest](monthly-digest.md)) and "Les vols en réseau" (`Sessions`, the
[reminders and results](sessions.md#reminders-and-results)). Security and confirmation e-mails are always sent. New accounts
get everything but the monthly digest and the session e-mails.

The notifications on a phone or a computer (network sessions, new messages, comments on a flight)
are turned on per device and chosen apart from the e-mails, in the panel "Notifications sur cet
appareil" of the profile (see [installable site](installable-site.md#notifications)).

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
