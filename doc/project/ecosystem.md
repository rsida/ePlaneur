# Ecosystem

The club site is one piece of a larger set of sites and tools. Knowing who does what avoids
rebuilding something that already exists elsewhere.

## The two ePlaneur sites

| | eplaneur.fr | club.eplaneur.fr (this project) |
|---|---|---|
| Role | Founding, **technical and documentary** resource site (national scope) | **Associative and organisational** site |
| Content | Condor documentation (install, settings, flight preparation, recording, MacCready, shortcuts), sceneries (FrSW...), FPL database and sheets, XCSoar, testimonials, videos, blog since 2021 | Presentation, training path, network flights organisation, membership, official documents, governance, news |
| Data | FPL and network sessions database, normalisation and integrity tools (hash, signature, metadata) | Users, members, pages, news |
| ChatBot | IA-ePlaneur (2024–2026, Chatbase) — to be replaced | Club ChatBot, self-hosted since April 2026 |

Both sites share the `eplaneur.fr` domain and link to each other heavily. The club site mostly
**describes** the ePlaneur tools and links to eplaneur.fr for the tools themselves.

## Simulator and Condor services

| Service | Use |
|---|---|
| **Condor** (Condor Soaring) | The simulator. Condor 2 (stable since Dec 2023) for initiation; Condor 3 (released 29 Oct 2024) for advanced pilots. Official site: licences, updates, hangar, manual (French PDF), forums, live server list |
| **Condor Club** (condor.club) | Free account + cheap Premium subscription: reserve a **CN**, download sceneries via Condor Updater, online logbook (the only trustworthy one), circuits with performances ranking, challenges, virtual FAI-like badges, online competitions |
| **CondorUTill** (condorutill.fr) | Utilities by Marc Till: VerifLocal (safety analysis), CoTASA (TAS alarm), CoTaCo (`.fpl` → XCSoar `.tsk`), FPL2V3 (Condor 2 → 3 flight plans), SceneryCheck3 |
| **XCSoar** | Free flight computer coupled with Condor (step 6) |
| **FFVP servers** | "AuvRAlpes" Condor servers used for network flights (+ backups) |
| **Cunimb-Condor** | Live flight tracking |
| **SportsTrackLive** | Post-flight trace analysis animations |

## Association tools

| Tool | Use |
|---|---|
| **Yapla** (club-eplaneur.s2.yapla.com) | Membership forms, card payments, invoices, renewals, member space |
| **WhatsApp** | Main written channel, flight announcements and results (see [club](club.md#communication-channels)) |
| **Discord** | Voice: briefings and in-flight radio |
| **Trello** | Action tracking (projects, membership campaigns, website work) |
| **Google Drive** | Progression tracking workbooks (experimental) |
| **Iperius Remote** | Remote assistance licence to help members install Condor |
| **Google Analytics / Site Kit** | Audience measurement |

## ChatBots

- April 2026: Chatbase raised prices by 130 % and divided usage by four with 15 days notice; the club
  stopped it and deployed a **self-hosted** ChatBot dedicated to the club site.
- Club ChatBot: **logged-in users only**, answers about training, flights and the club (not the
  technical eplaneur.fr content), thumbs up/down feedback with comment, copy answer, regenerate
  (shows "1/2"), reset conversation, conversation memory across sessions. Answer quality depends on
  the cleanliness and structure of the site content: write pages that are clear and well structured.
- eplaneur.fr ChatBot: to be rebuilt after the club one (Condor, sceneries, FPL, XCSoar, equipment,
  competitions...).
