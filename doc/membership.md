# Membership

Roadmap step 8: the membership of the club on its own site instead of Yapla. Steps 8a (seasons,
requests, validation, certificate, end of membership, reminders) and 8b (online payment with
HelloAsso) are done; the treasurer's books (8c) are described at the end.

| What | Where |
|---|---|
| Pages | `MembershipController`: `/adhesion` (fees of the open seasons for everyone; for an account, its memberships and the request form), `/adhesion/{id}/attestation` (certificate of a validated membership: its member, or `MEMBERSHIP_MANAGE`) |
| Model | `MembershipSeason` (a calendar year, open or not, payment instructions), `MembershipType` (name, price in cents, age limit, order), `Membership` (account, season, type, `MembershipStatus`, fee, support payment, the data of the membership form, payment, validation, note, reminder date) |
| Rules | `App\Membership\MembershipManager` (request, payment, validation, refusal, end, reminders), `MembershipMailer` (e-mails `templates/email/membership_*.html.twig`) |
| Back-office | "Adhésions" (`MembershipCrudController`) and "Saisons et tarifs" (`MembershipSeasonCrudController`), permission `MEMBERSHIP_MANAGE` (granted to the committee) |
| Online payment | `App\Membership\Payment\HelloAssoClient`, routes `/adhesion/{id}/payer`, `/adhesion/paiement/retour`, `/adhesion/paiement/annule`, `/adhesion/paiement/notification` |
| Commands | `app:membership:expire [--dry-run]`, `app:membership:remind` (daily, see [production](production.md)) |

## How it works

1. **Season**: the committee creates the season of a year in "Saisons et tarifs" with its types and
   prices (2026: "Moins de 25 ans" 10 € up to 24 years old on 1 January, "Adulte" 25 €) and opens it.
   From September, a new season defaults to the next year: opening it starts the renewal campaign.
2. **Request**: on `/mon-compte/adhesion` (`/adhesion` shows the fees to everyone), a logged-in account picks a type and fills in the membership form of
   the internal rules (name, birth date, address, phone, acceptance of the statutes and rules), with an
   optional voluntary support payment (it gives no extra right and no tax receipt yet). The data of
   the previous membership is filled in again. One active request per season. A reduced rate checks
   the age on 1 January. An e-mail sums the request up, with the payment instructions of the season.
3. **Payment**: online on HelloAsso when it is configured (below), or recorded by the treasurer
   with "Paiement reçu" (transfer by default; the reference can be edited).
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

Memberships are kept when an account is deleted, anonymised (name, type and amounts stay; e-mail,
birth date, address and phone are emptied): they are part of the books. A season and its types cannot be deleted once used.

## Online payment

HelloAsso Checkout (API v5), the free payment platform of French associations: the member pays on
HelloAsso's page, the site never handles a card. HelloAsso offers the payer an optional contribution
to its running costs; the club receives the whole amount.

1. Sending the request (or "Payer … en ligne" on `/adhesion` later) opens a checkout on HelloAsso
   (`POST /v5/organizations/{organization}/checkout-intents`: amounts in cents, the payer, the
   membership id in the metadata, `containsDonation` when there is a support payment) and sends the
   member to its page; the checkout id is kept on the membership.
2. Back on `/adhesion/paiement/retour`, or when HelloAsso calls `/adhesion/paiement/notification`,
   the site asks HelloAsso for the checkout: once it has an order with an authorised payment, the
   membership is marked paid online (reference `HelloAsso <order>`). Neither the query of the return
   page nor the content of the notification is trusted.
3. The club then validates it as any other.

Setting it up:

1. In the HelloAsso back-office of the association: "Mon compte" > "Intégrations et API": create the
   API keys, and set the notification URL to `https://<site>/adhesion/paiement/notification`.
2. Set `HELLOASSO_ORGANIZATION`, `HELLOASSO_CLIENT_ID` and `HELLOASSO_CLIENT_SECRET` (the secret in
   `.env.local` or the secrets vault, see [configuration](configuration.md)). Try first with the
   sandbox (`HELLOASSO_API_URL=https://api.helloasso-sandbox.com` and keys of a sandbox account).

Without these variables nothing changes: members pay as the payment instructions of the season say.

## Next

- **8c, treasurer's books**: income and expenses, receipts, budget against actual, exports for the
  general meeting, import of the Yapla history. Needs the treasurer's needs first (see
  [roadmap](roadmap.md)).
