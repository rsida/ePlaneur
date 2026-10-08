# Membership

Roadmap step 8: the membership of the club on its own site instead of Yapla. Step 8a (seasons,
requests, validation, certificate, end of membership, reminders) is done; online payment (8b) and the
treasurer's books (8c) are described at the end.

| What | Where |
|---|---|
| Pages | `MembershipController`: `/adhesion` (fees of the open seasons for everyone; for an account, its memberships and the request form), `/adhesion/{id}/attestation` (certificate of a validated membership: its member, or `MEMBERSHIP_MANAGE`) |
| Model | `MembershipSeason` (a calendar year, open or not, payment instructions), `MembershipType` (name, price in cents, age limit, order), `Membership` (account, season, type, `MembershipStatus`, fee, support payment, the data of the membership form, payment, validation, note, reminder date) |
| Rules | `App\Membership\MembershipManager` (request, payment, validation, refusal, end, reminders), `MembershipMailer` (e-mails `templates/email/membership_*.html.twig`) |
| Back-office | "Adhésions" (`MembershipCrudController`) and "Saisons et tarifs" (`MembershipSeasonCrudController`), permission `MEMBERSHIP_MANAGE` (granted to the committee) |
| Commands | `app:membership:expire [--dry-run]`, `app:membership:remind` (daily, see [production](production.md)) |

## How it works

1. **Season**: the committee creates the season of a year in "Saisons et tarifs" with its types and
   prices (2026: "Moins de 25 ans" 10 € up to 24 years old on 1 January, "Adulte" 25 €) and opens it.
   From September, a new season defaults to the next year: opening it starts the renewal campaign.
2. **Request**: on `/adhesion`, a logged-in account picks a type and fills in the membership form of
   the internal rules (name, birth date, address, phone, acceptance of the statutes and rules), with an
   optional voluntary support payment (it gives no extra right and no tax receipt yet). The data of
   the previous membership is filled in again. One active request per season. A reduced rate checks
   the age on 1 January. An e-mail sums the request up, with the payment instructions of the season.
3. **Payment**: recorded by the treasurer with "Paiement reçu" (transfer by default; the reference
   can be edited), or online once 8b is done.
4. **Validation**: "Valider" puts the account in the "Membre" group and sends a welcome e-mail with
   the link to the certificate; "Refuser" closes the request. Requests to process (sent or paid) show
   as a badge in the menu of the back-office.
5. **Certificate**: a page to print or save as PDF (name, birth date, season, type, amounts,
   validation date, reference `ADH-<year>-<id>`).
6. **End**: memberships end on 31 December. `app:membership:expire` removes the "Membre" group from
   the accounts that have a membership on the site but none validated for the current year, after a
   grace month (January). Accounts made members by hand without any membership here (the Yapla
   years) are never touched.
7. **Reminders**: `app:membership:remind` e-mails, once, the members of the season before an open
   season who have not sent a request for it yet.
8. **Export**: "Exporter la saison (CSV)" (semicolons, UTF-8 for Excel): every membership of a season
   with the member's data, amounts, payment and validation, for the treasurer and the federation.

Memberships are kept when an account is deleted (name and e-mail are copied): they are part of the
books. A season and its types cannot be deleted once used.

## Next

- **8b, online payment**: HelloAsso Checkout (the association's HelloAsso account, no card handled
  by the site): the request redirects to the HelloAsso payment page, the payment notification marks
  the membership paid. Needs the club's HelloAsso API keys.
- **8c, treasurer's books**: income and expenses, receipts, budget against actual, exports for the
  general meeting, import of the Yapla history. Needs the treasurer's needs first (see
  [roadmap](roadmap.md)).
