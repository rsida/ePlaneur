# ePlaneur project — functional overview

This folder describes **what the Club ePlaneur website is for**: the association, its activities, its
users and the features of the current site. It is the functional reference for every development on
this repository.

Source: the live site https://club.eplaneur.fr (WordPress), fully reviewed on 2026-10-03. Facts that
change often (schedules, versions, fees) are dated: check the live site before relying on them.

| Document | Content |
|---|---|
| [Club](club.md) | The association, governance, membership, fees, accounts and access levels |
| [Training](training.md) | The 8-step guided training path, levels, internal qualifications, Brevet project |
| [Network flights](flights.md) | Multiplayer flights: levels, schedule, how to take part, rules, organising a flight |
| [Ecosystem](ecosystem.md) | eplaneur.fr, Condor, Condor Club, Yapla, WhatsApp, Discord, ChatBot... |
| [Current site](current-site.md) | Site map, visibility of each section, WordPress features to replace |
| [Glossary](glossary.md) | Domain vocabulary (FPL, CN, Stop Join, ePilote...) |

## In one paragraph

**ePlaneur** is the practice of gliding on a computer with the **Condor** soaring simulator, officially
recognised by the French gliding federation (**FFVP**) since 26 October 2022. The **Club ePlaneur** is
a French non-profit association (loi 1901, published in the Journal Officiel on 3 February 2026, head
office in Saint-Denis, La Réunion) that runs this activity **entirely online**: structured training
from first steps to competition, regular **network (multiplayer) flights**, technical assistance and
documentation. It has about thirty members and produced the 2025 French ePlaneur champion.

## What the website is for

The club has no physical location: the website **is** the club. It must:

1. **Present** ePlaneur and the club to visitors, teachers (BIA), gliding clubs and the FFVP.
2. **Train**: publish the guided training path, its resources (videos, documents) and, eventually,
   track each pilot's progression and qualifications.
3. **Organise flights**: publish the network flight schedule, the rules and the procedures, and give
   access to flight plans and results.
4. **Run the association**: membership, official documents, members lists, committee meetings
   (restricted), news.
5. **Assist**: a ChatBot answering questions from the site content (logged-in users only).

## Who uses it

| Audience | Typical needs |
|---|---|
| Visitors | Understand ePlaneur, see the presentation video, find how to start |
| Registered users (free account) | Access restricted pages, use the ChatBot, take part in network flights |
| Members (paid membership) | Members-only content, ePlaneur tools, club section pages |
| Committee (Comité Directeur) | Meeting minutes, action tracking, internal pages |
| Administrators | Content, users and memberships management |

Target public (from the club objectives): BIA teachers and students, people discovering gliding,
student and active glider pilots (off-season training), competitors, former pilots who can no longer
fly, people far from an airfield, people with disabilities practising from home. Many members are in
La Réunion, where real gliding does not exist; all schedules are published in **metropolitan France
time**.

## Guiding principles (to respect in the product)

- **Simulator, not game**: the club insists on the difference between an *ePilote* (structured
  learning) and a *gamer*. Content and features should support progression, not just entertainment.
- **Complementary to real gliding**: the club does not replace real flight training and has no real
  glider.
- **Two sites, two roles**: eplaneur.fr is the technical/resource site (flight plan database, Condor
  documentation); club.eplaneur.fr is the associative/organisational site. Do not duplicate eplaneur.fr
  features without a decision (see [ecosystem](ecosystem.md)).
- **French-speaking audience**: all user-facing content is in French; times in metropolitan France time.
- **Neutral and non-discriminatory** framework (statutes): no political or religious content.
