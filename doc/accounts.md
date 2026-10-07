# Accounts, groups and permissions

How users sign up, log in, and what decides what they can see and do. Functional background (access
levels of the club) is in [project/club.md](project/club.md).

## Model

| Concept | Where | Edited by |
|---|---|---|
| **User** (`App\Entity\User`) | Table `app_user`: e-mail (login), display name, password hash, `verified`, creation date | The user (registration, password) and administrators |
| **Group** (`App\Entity\Group`) | Table `app_group`: code, name, description, permissions, "all permissions" flag, "system" flag; groups it **includes** (table `group_inclusion`) | Administrators, in the back-office ("Groupes et droits", see [admin](admin.md)) or from the console |
| **Membership** | Table `app_user_group`: a user belongs to **any number** of groups; rights add up, with those of the groups they include | Administrators |
| **Permission** (`App\Security\Permission`) | PHP enum: the fixed catalogue of actions (`ADMIN_ACCESS`, `USER_MANAGE`, `GROUP_MANAGE`, post permissions listed in [content](content.md#permissions)...) | Developers: add a case when a feature needs a new right |
| **Access** | On each restricted content (post, page, document, media): who may open it, `visibility` = `public`, `authenticated` or `groups` (+ the allowed groups), and what the others get, `announced` (see below) | Content editors |

Default groups, created by the first migration and by the dev fixtures (`App\Security\DefaultGroup`):

| Code | Name | Permissions |
|---|---|---|
| `member` | Membre | none yet |
| `committee` | Comité, includes Membre | `ADMIN_ACCESS`, `POST_CREATE`, `POST_EDIT`, `POST_PUBLISH`, `CATEGORY_MANAGE`, `MEDIA_MANAGE`, `PAGE_MANAGE`, `DOCUMENT_MANAGE` |
| `admin` | Administrateur | all (`all_permissions` flag: includes permissions added later, and sees every content) |

Administrators can create other groups (Rédacteur, Bienfaiteur, Bénévole...) and change which
permissions each group holds. A registered user without any group is a free account ("inscrit").

**Group inclusion**: a group can include other groups ("Inclut les groupes" in the back-office).
Its members then count as members of the included groups, for content access and permissions, and
inclusions are followed transitively (cycles are ignored): the committee includes the members, so a
committee member does not need the "Membre" group too (`User::getEffectiveGroups()`).

Every account has the single Symfony role `ROLE_USER`. Rights are **not** stored in the security
token: voters read the user's groups on each request, so a change of groups or permissions applies
immediately, without logging the user out.

## Checking rights in code

| Need | PHP | Twig |
|---|---|---|
| Logged in | `#[IsGranted('ROLE_USER')]` | `{% if app.user %}` |
| A permission | `#[IsGranted('USER_MANAGE')]`, `$this->denyAccessUnlessGranted(Permission::UserManage->value)` | `{% if is_granted('USER_MANAGE') %}` |
| Open a content (post, page, document, media) | `$this->isGranted(ContentVoter::VIEW, $post)` | `{% if is_granted('CONTENT_VIEW', post) %}` |
| List it (lists, menus, cards) | `$this->isGranted(ContentVoter::LIST, $page)` | `{% if is_granted('CONTENT_LIST', page) %}` |

### Access to content

Restricted content implements `App\Security\RestrictedContentInterface` (`getVisibility()`,
`getAllowedGroups()`, `isAnnounced()`). Who may **open** it (`CONTENT_VIEW`):

- `public`: everyone;
- `authenticated`: any logged-in user;
- `groups`: members of at least one allowed group, or of a group including it;
- users in a group with "all permissions" (administrators) see everything.

What the **others** get depends on `announced` ("Pour les autres" in the back-office):

| Mode | Lists, menus, cards (`CONTENT_LIST`) | Its address |
|---|---|---|
| **Annoncé** (default) | Shown with a padlock and the audience ("Comité", "Connectés") | "Contenu réservé" page (403): title, then login and registration for visitors, or "réservé au groupe…" |
| **Privé** | Absent | Not found (404): the content does not reveal it exists |

Files follow the same rule (an announced file asks to log in, a private one is not found), and a
document's file takes the access of its document. See
[content](content.md#pages-menus-and-restricted-content).

To add a right: add a case to `Permission` (with its French label), protect the action with
`#[IsGranted('NEW_PERMISSION')]`, then grant it to the relevant groups. Hide links to actions the
user cannot perform with `is_granted()` in templates as well.

## Pages

| URL | Route | Purpose |
|---|---|---|
| `/inscription` | `app_register` | Free registration: display name, e-mail, password (twice) |
| `/inscription/confirmer` | `app_verify_email` | Signed link from the confirmation e-mail (valid 24 h) |
| `/inscription/renvoyer-confirmation` | `app_register_resend` | New confirmation link (3 requests per 15 min per IP) |
| `/connexion`, `/deconnexion` | `app_login`, `app_logout` | Login (with "stay logged in" for 30 days), logout |
| `/mot-de-passe-oublie` | `app_forgot_password_request` | Reset link by e-mail (valid 1 h, one request per 15 min) |
| `/mon-compte` | `app_account` | Account details and groups |

Rules:

- An account cannot log in before its e-mail address is confirmed (`App\Security\UserChecker`).
  Resetting the password also confirms the address.
- Passwords: at least 10 characters and a medium strength score (`PasswordStrength`).
- Login is throttled: 5 failures per minute per e-mail and IP.
- Pages that send e-mails answer the same way whether the address exists or not, so they cannot be
  used to find out who is registered.
- Forms use stateless CSRF tokens: a hand-written form must carry
  `data-controller="csrf-protection"` on its token field (see `templates/security/login.html.twig`).

E-mails use the sender `MAILER_FROM` (see [configuration](configuration.md)); their templates are in
`templates/email/`.

## Console commands

Accounts and groups are managed in the back-office ("Comptes", "Groupes et droits", see
[admin](admin.md)) or from the console, for instance to create the first administrator:

```bash
# First administrator (asks the password)
make console c="app:user:create admin@example.org 'Prénom Nom' --group=admin"

# Show / change the groups of an account
make console c="app:user:groups jane@example.org --add=member --add=committee --remove=admin"

# Groups, their permissions and the permission catalogue
make console c="app:group:list"

# Grant or revoke permissions of a group
make console c="app:group:permissions committee --grant=USER_MANAGE --revoke=GROUP_MANAGE"
```

On the production server, `make console c="..."` runs them the same way (see [production](production.md)).

## Development accounts

`make fixtures` creates the default groups and these accounts, all with the password
`ePlaneur-dev-2026`:

| E-mail | Groups |
|---|---|
| `admin@eplaneur.test` | Administrateur |
| `comite@eplaneur.test` | Comité (includes Membre) |
| `membre@eplaneur.test` | Membre |
| `inscrit@eplaneur.test` | none (free account) |
| `camille@eplaneur.test` | Comité (author of the demo posts) |
