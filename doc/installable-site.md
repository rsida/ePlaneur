# Installable site

The site installs on a phone or a computer like an application (roadmap 11l): its own icon on the
home screen, a window without the bar of the browser, shortcuts to the main pages. It stays the
same site: nothing to publish in an application store, every update of the site is the update of
the application.

## Pieces

| Piece | Where | Content |
|---|---|---|
| Manifest | `/manifest.webmanifest` (`AppController::manifest()`), linked from `base.html.twig` | Name "Club ePlaneur" ("ePlaneur" under the icon), start on the home page, `standalone` window, colours of the palette (cream, ink), icons, shortcuts: Mon parcours, Mes vols, Vols en réseau, Mon compte |
| Icons | `assets/images/brand/` | `icon-192.png` and `logo-512.png` (the round logo), `icon-maskable-512.png` (the logo inside the safe zone on white: Android cuts it to its own shape), `apple-touch-icon.png` (180 px on white, for iOS); made from `logo-512.png` with GD |
| Service worker | `/sw.js` (`templates/app/service_worker.js.twig`), served from the root to control every page, `Cache-Control: no-cache` so that browsers see a new version at once; registered by `assets/app.js` | Pages always from the network (personal data, always fresh); without network, the page "Hors ligne" kept at installation. The files of `/assets/` (versioned names) kept once loaded and served from the cache. Nothing else cached. A new `AppController::CACHE` empties the old cache |
| Offline page | `/hors-ligne` (`app_offline`, `noindex`) | "Vous êtes hors ligne", with the layout of the site (its styles come from the cache) |
| Install button | Footer, `assets/controllers/install_controller.js` | "Installer l'application" when the browser offers it (Chrome, Edge, Android); on iPhone and iPad the hint "Partager, puis « Sur l'écran d'accueil »"; hidden once installed |

## Testing

A service worker needs a secure origin: the browser must trust the certificate of
`https://eplaneur.local` (mkcert), else the registration fails with a `SecurityError` and the rest of
the site works as before. Chrome DevTools, Application tab: manifest, service worker, cache
`eplaneur-v1`, and "Offline" to see the offline page.

## Not done

Notifications (Web Push: a session tomorrow, a comment of a trainer) need VAPID keys, a library to
send them and the messenger worker of production; to decide with the e-mails of 11g and 11h.
