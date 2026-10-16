# Presentation of the site for the committee

`/comite/le-site` (asked on 2026-10-15): a page that explains every feature of the site to the
committee, the most striking ones first, so that it can judge what the new site brings before
replacing the WordPress site (see [roadmap](roadmap.md), steps 7 and 8).

## Access

- `SITE_TOUR` ("Lire la présentation des fonctionnalités du site (comité)"), granted to the committee by
  the migration `Version20261015200403` and `DefaultGroup`; administrators see it too.
- The same migration adds "Le nouveau site" at the end of the main menu, shown to the "Comité" group
  only (an administrator can move or rename it in "Menus"); the dev fixtures add it too.
- Not indexed (`noindex, nofollow`).

## Content

`SiteTourController`, `templates/site_tour/index.html.twig`, styles in
`assets/styles/pages/site-tour.css`.

- Key figures (flights, flight hours, sessions, accounts) and the latest public flight replayed in 3D
  (`flight/_replay_teaser.html.twig`, the replay of the home page).
- Six striking features, each with what it does, why it matters to the club and a live link: the 3D
  replay, the whole session replayed and told, the debrief, the lift that existed, the comparison
  with the winner, "Mon parcours". The links use the showcase flight (`FlightRepository::findShowcase()`)
  and the session with the most tracks (`FlightRepository::findRichestSession()`).
- Four useful features (notifications, activity for the general meeting, teacher space, online
  membership), with what they need to be turned on.
- A ten-minute demo path, each step with a link to its example (the advice of the showcase flight for the debrief), the catalogue by audience, and the decisions waiting for the committee.

The texts live in the template: update them when a feature is added (see [features](features.md)).
