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
`eplaneur-v2`, and "Offline" to see the offline page.

## Notifications

A member turns the notifications on for each device in "Mon profil", panel "Notifications sur cet
appareil" (`#push`), and chooses in the same panel what all their devices receive
(`User::$pushNotifications`, `UserNotification::PUSH`, everything by default):

| Notification | Sent by | Opens |
|---|---|---|
| "Vol en réseau demain à 16h30", then "Résultats du vol en réseau" with the podium (tag `session-{id}`: the results replace the reminder) | `SessionMailer`, `app:sessions:mail` in the evening ([reminders and results](sessions.md#reminders-and-results)) | The session |
| "Message de …", the subject and the start of the message (tag `conversation-{id}`) | `MessageMailer`, at each message | The conversation |
| "… a commenté votre vol", the start of the comment (tag `flight-{id}`) | `FlightMailer`, at each comment by someone else | The comments of the flight |

These choices are apart from the e-mail preferences: a member may want the e-mail, the notification
or both. The panel is shown only when the site has its keys.

| Piece | Where | Content |
|---|---|---|
| Keys | `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY` ([configuration](configuration.md#mail-and-messages)), made with `make console c="app:push:keys"` | Sign every notification (VAPID, subject `mailto:` + `CONTACT_EMAIL`). New keys end every subscription: the members turn the notifications on again |
| Panel | `templates/account/profile.html.twig`, `assets/controllers/push_controller.js` | Asks the browser for the permission, subscribes with the public key and gives the subscription to the site; "Envoyer un essai", "Désactiver sur cet appareil". Says when the permission is blocked, and on iPhone and iPad that the site must first be installed on the home screen (iOS 16.4 or later) |
| Subscriptions | `PushSubscription` (address and keys of the browser, the account), `PushController` (`/notifications/appareils`, `/retrait`, `/essai`, JSON with the token `push` in `X-CSRF-Token`) | One row per device; the same browser subscribing again for another account moves to it. Deleted with the account |
| Sending | `App\Push\PushNotifier` (library `minishlink/web-push`) | Only to the devices of verified accounts that want that kind. Messages and comments go through Messenger (`PushNotification`, transport `async`: the worker in production, at once in dev), so that the page does not wait for the push services; the session reminders are sent by the command itself. Payload `{title, body, url, tag}`, kept one day by the push service for a device that is off. The addresses come from the browsers, so requests to private networks are refused (`NoPrivateNetworkHttpClient`). A device whose push service answers 404 or 410 is forgotten |
| Display | `templates/app/service_worker.js.twig` | Shows the notification with the icon of the site; a click opens its page, or brings forward a window already on it. A notification replaces the earlier one of the same tag |

The PHP image has the `gmp` extension, which the library uses for its calculations (without it, they
are slower and a warning is logged).

Testing: notifications need the same trusted certificate as the service worker, and a real browser:
the Chromium of Playwright refuses the permission. `PushTest` and `SessionMailTest` check the
subscriptions, the choices, the notifications queued for a message and a comment, and the requests
sent to a fake push service.

## Not done

The subscription renewed by the browser (`pushsubscriptionchange`) is not handled: the member turns
the notifications on again.
