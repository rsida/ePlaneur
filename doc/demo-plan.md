# Script of the demonstration

`/plan-de-demo` (asked on 2026-10-19): the script of an end-to-end demonstration of the site, for
the administrator who presents it. It reads from top to bottom and visits every feature in the order
of a pilot's journey, from the visitor to the committee. Each step says where to click, what to
show and what to say, with the stress on what the feature brings to learning: the flight analysis,
for instance, does not try to replace Condor Club; it helps pilots improve on their own, with a
trainer, or by debriefing a session together.

## Access

- `DEMO_PLAN` ("Lire le plan de la démonstration complète du site (administrateurs)"), held by no
  group: only administrators (all permissions) open it.
- Linked from the back-office (last section of the menu and a card of the dashboard) and from the
  demo of the [presentation for the committee](site-tour.md), for whoever holds the permission.
- Not indexed (`noindex, nofollow`).

## Content

`DemoPlanController`, `templates/demo_plan/index.html.twig`, styles in
`assets/styles/pages/demo-plan.css`.

- Duration (the sum of the acts), the showcase flight (`FlightRepository::findShowcase()`) and the
  session with the most tracks (`FlightRepository::findRichestSession()`).
- The preparation: the accounts to open, each in its own window (the "Formateur" and "Enseignant"
  groups to create first), the data to check (showcase flight and session, an exercise on a flight
  plan and a quiz placed in a step, with a warning when one is missing), the screen and the phone.
- The common thread (fly, understand, correct, fly again), what the site is next to Condor, Condor
  Club, eplaneur.fr, Discord and WhatsApp, and the contents of the acts.
- Nine acts: the visitor, the pilot's space, understanding one's own flight, the network session,
  the trainer, "Mon parcours", the BIA classes, the life of the club, the committee and the
  administration. Each step has its duration, "À montrer" (each point with "À quoi ça sert"), "À dire", sometimes a warning, and a
  button to its page on the club's own data.
- The likely questions and their answers.

The texts live in the template: update them when a feature is added (see [features](features.md)).
`EveryPageIsLinkedTest` and `DemoPlanTest` check that the page is linked and that every step opens
a page.
