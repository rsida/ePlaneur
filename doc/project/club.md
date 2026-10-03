# The Club ePlaneur association

## Legal identity

| Item | Value |
|---|---|
| Form | Association loi 1901 |
| Constitutive general assembly | 15 December 2025 (statutes V23) |
| Declared in prefecture | 26 January 2026 (RNA W9R1011072) |
| Published in the JOAFE | 3 February 2026 (announcement n°3024) |
| SIREN / APE | 100726348 / 93.12Z (sports club activities) |
| Head office | Saint-Denis, La Réunion |
| Contact | club-eplaneur@outlook.fr, Facebook page "ePlaneur" |
| Federal affiliation | FFVP affiliation in progress |
| Website hosting | o2switch (Clermont-Ferrand) |

History: network flights started in spring 2020 (COVID lockdown) with three pilots; site "Planeurs
Virtuels 63" in 2021, renamed eplaneur.fr end of 2022; club website opened on 16 January 2025;
association formally created in 2025–2026.

## Purpose (simplified article 2 of the statutes)

- Develop and structure the digital practice of gliding with simulators, within the eSport activities
  recognised by the FFVP.
- Prepare eSport pilots for regional, national and international competitions.
- Support young people (notably BIA students) and their teachers in La Réunion.
- Spread aeronautical culture, train and improve practitioners and instructors, promote aeronautical
  careers.
- Respect the sport ethics of the CNOSF; no political or religious content, no discrimination.

## Five objectives

1. **Train** pilots in ePlaneur (structured progression, Condor 2 then Condor 3).
2. **Recognise progression** with internal qualifications (see [training](training.md)).
3. **Prepare for competition** (French ePlaneur Championship).
4. **Provide technical and documentary assistance** (both websites, ChatBot, procedures).
5. **Be an innovation lab for the FFVP**: distance learning, simulation, network flights, trace
   analysis.

The club explicitly does **not** own gliders, operate an airfield or deliver real flight training.

## Governance

- **Comité Directeur (CD)**: governance and orientations. Current roles: President, General
  Secretary, Treasurer, Administrator & head of the ePlaneur commission, head of the IT commission
  (maintains the website and digital tools), coordinator of competition flights, coordinator of
  initiation flights. Names and bios are on the live site (`/le-club/listes-membres/comite-directeur/`).
- **Bureau**: President, General Secretary, Treasurer, elected within the CD; executive body.
- **Comité des membres fondateurs**: set the 2026 fees (meeting of 1 October 2025).
- Reference documents: statutes, internal rules (règlement intérieur V13, written with the FFVP legal
  department), JOAFE publication, prefecture receipt. All published as PDF downloads.
- Activities: CD meetings (minutes published as restricted posts, roughly every 1–3 weeks), action
  tracking on Trello, workshops with schools (e.g. a project with 8 EPITECH La Réunion students).

## Member categories

From the members pages and the statutes:

| Category | Notes |
|---|---|
| Registered user | Free website account, **not** a member |
| Member (adhérent) | Paid the yearly fee, validated by the club |
| Volunteer | Helps run activities |
| Benefactor (bienfaiteur) | Supports the club with a donation accepted by the CD; **no vote**, not eligible |
| Board / Committee member | Elected roles above |
| Founding member | Members of the founding committee |

The previous prototype of this application modelled these as roles: `ROLE_REGISTERED`,
`ROLE_CLUB_MEMBER`, `ROLE_HONORARY_MEMBER`, `ROLE_BOARD_MEMBER`, `ROLE_FOUNDING_MEMBER`, plus
`ROLE_ADMIN` / `ROLE_SUPERADMIN`.

## Membership and fees

Membership relies on **two separate accounts**:

1. A **club website account** (free, self-service registration) — prerequisite, gives access to
   registered-only content.
2. A **Yapla** account (Crédit Agricole partner platform) created during the first membership: online
   form, administrative data (partly forwarded to the FFVP), card payment, invoices, renewal.

First membership flow (today, on Yapla): choose a membership type → fill the membership form (same
fields as the internal rules form) → billing form → card payment → "request registered" e-mail →
**manual validation by the club** → validation e-mail with invoice (+ personal WhatsApp message) →
member sets their Yapla password. Renewal happens at year end from the Yapla account.

2026 fees (decided on 1 October 2025):

| Type | Price |
|---|---|
| Under 25 | 10 € |
| Adult | 25 € |
| Voluntary support payment | Free amount on top of the fee, gives no extra right; no tax receipt yet (rescrit pending) |

Fees fund web hosting, ChatBot/AI subscriptions, software licences, training and network flights.

## Access levels on the current site

| Level | Sees |
|---|---|
| Visitor | Public pages; in the membership section only "registration" and "fees" |
| Registered (logged in) | + "first membership" page, ChatBot, restricted pages |
| Member (validated) | + ePlaneur tools and additional "Le Club" sections |
| Committee | + meeting minutes, progression tracking draft, 4th-level menu pages |

## Communication channels

- **WhatsApp** is the main written channel; groups are opt-in (ask a board member on WhatsApp):
  `ePlaneur` (broadcast, auto-joined), `ePlaneur-Général`, `Amicale-ePlaneur`, flight groups
  (`ePlaneur Initiation`, `ePlaneur-C2`, `ePlaneur-C3`, `ePlaneur Compétition`), technical groups
  (`ePlaneur-XCSoar`, `ePlaneur-LX`).
- **Discord** (server "ePlaneur") is the voice channel: briefings and in-flight "radio".
- **Website**: news, official documents, contact form.
- External communication material: 2-minute presentation video (V12), letter to teachers (targets BIA
  teachers, especially in La Réunion), one-page objectives PDF.
