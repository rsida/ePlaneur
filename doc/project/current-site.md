# Current site (WordPress)

club.eplaneur.fr runs on **WordPress 7.1** (OceanWP theme) hosted by o2switch. This application is
meant to rebuild it with Symfony. This page lists what exists today so that nothing is forgotten.

## Content volume (2026-10-03)

- 79 pages organised in a 3-level menu (a 4th level exists for committee-only pages).
- 34 posts: about 10 public articles, the rest are committee meeting minutes (restricted).
- Post categories: Club, ePlaneur, Événement, Formation, Sites Web, Voler (+ Uncategorized).
- Many downloadable PDFs (statutes, internal rules, fees decision, training path, objectives) and an
  embedded presentation video (MP4) plus YouTube videos.

## Site map (main menu)

| Level 1 | Level 2 / 3 | Visibility |
|---|---|---|
| **Accueil** (home: presentation video, champion highlight, short pitch) | Le ePlaneur · Site du Club (visitor guide) / Menu du site · Le site ePlaneur.fr · ChatBots ePlaneur · Installer Condor | Public (menu page: committee) |
| **Formation** | Parcours de formation: Vue d'ensemble, Parcours type / Étape 1 (2–8 to come) · Vidéos pédagogiques · Suivi de la progression · Bonnes pratiques | Public (tracking: committee) |
| **Voler** | Vols en réseau: Organisation, WhatsApp des vols, Outils des vols, Prérequis, Participer, Règles, Proposer un vol, Pause estivale · Enregistrer un vol · Carnet de vols · Vol au Mac Cready · Outils ePlaneur: Rechercher FPL, Afficher FPL, Afficher sessions, Résultats session · Sites indispensables: Condor Club, Condor Soaring, Utilitaires Condor | Public; tools for members |
| **Le Club** | Présentation · Objectifs / Projet de Brevet · Textes officiels: Règlement intérieur, Statuts, Autres documents · Communication: interne, Vidéo, Lettre aux enseignants · Adhésions: Inscription, Cotisations, Première adhésion · Listes des membres: Comité Directeur, Bureau, Bienfaiteurs, Tous les membres · Activités: Réunions CD, Trello, Ateliers Epitech · Contact | Mixed (see below) |
| **Actualités** | À la une (selected articles: 2025 champion, FFVP championship, 2026 wishes) · Articles par catégorie · Articles par dates | Public list, some posts restricted |
| **Comptes** | Mon compte Club · Mon compte Yapla · Tous les comptes WP · Connexion · Déconnexion · Mot de passe · Inscription · Contact | Depends on login state |

Restricted pages show "Contenu restreint" with an invitation to log in or register.

## Features and the WordPress plugins behind them

| Feature | Today | Notes for the rebuild |
|---|---|---|
| Accounts: register, login, logout, password reset, profile (avatar, cover photo, about, e-mail, password, delete account), members directory | Ultimate Member–style pages (`/register`, `/login`, `/user`, `/members`, honeypot field) | Username = CN recommended; name format "Michel Rouleau"; password: lower + upper + digit |
| Content restriction by login/role | Restriction plugin | Visitor / registered / member / committee |
| Pages with multi-level menu, downloads, embedded videos, collapsible sections, tables | Gutenberg + OceanWP | Needs an editorial back-office |
| News with categories, "featured" selection, chronological list | Posts | Committee minutes are restricted posts |
| Contact form (first name, last name, e-mail, subject, message) | WPForms | |
| Forum | wpForo (`/community`, apparently unused) | Probably not needed |
| ChatBot widget (bottom-right icon) | Self-hosted ChatBot, logged-in only | Keep the integration point |
| SEO, redirections, view counter, broken link checker, Akismet | SEOPress, Redirection, Post Views Counter, WPMU DEV, Akismet | Keep SEO metadata and legacy URL redirects |
| Analytics | Google Site Kit / Analytics | Cookie consent needed (GDPR) |
| Membership and payments | **External**: Yapla | Integration or replacement to decide |
| FPL / sessions tools | **External**: eplaneur.fr | Link, embed or rebuild to decide |
| Summer break banner on home page | Manual | Configurable announcement banner |

## Legal pages

"Mentions légales" covers the publisher (Club ePlaneur, Saint-Denis), the publication director (the
President), site administrators, hosting (o2switch), intellectual property, liability and a privacy
policy (data collected through contact form, member accounts and Google Analytics; GDPR rights by
e-mail to the club). The rebuild must keep equivalent legal and privacy pages and a cookie banner.
