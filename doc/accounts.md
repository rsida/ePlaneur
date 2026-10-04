# Accounts, groups and permissions

How users sign up, log in, and what decides what they can see and do. Functional background (access
levels of the club) is in [project/club.md](project/club.md).

## Model

| Concept | Where | Edited by |
|---|---|---|
| **User** (`App\Entity\User`) | Table `app_user`: e-mail (login), display name, password hash, `verified`, creation date | The user (registration, password) and administrators |
| **Group** (`App\Entity\Group`) | Table `app_group`: code, name, description, permissions, "all permissions" flag, "system" flag | Administrators (admin screens come with roadmap step 3; console commands meanwhile) |
| **Membership** | Table `app_user_group`: a user belongs to **any number** of groups; rights add up | Administrators |
| **Permission** (`App\Security\Permission`) | PHP enum: the fixed catalogue of actions (`ADMIN_ACCESS`, `USER_MANAGE`, `GROUP_MANAGE`, post permissions listed in [content](content.md#permissions)...) | Developers: add a case when a feature needs a new right |
| **Visibility** (`App\Security\Visibility`) | On each restricted content: `public`, `authenticated` or `groups` (+ the allowed groups) | Content editors |

Default groups, created by the first migration and by the dev fixtures (`App\Security\DefaultGroup`):

| Code | Name | Permissions |
|---|---|---|
| `member` | Membre | none yet |
| `committee` | Comité | `POST_CREATE`, `POST_EDIT`, `POST_PUBLISH`, `CATEGORY_MANAGE`, `MEDIA_MANAGE` |
| `admin` | Administrateur | all (`all_permissions` flag: includes permissions added later, and sees every content) |

Administrators can create other groups (Rédacteur, Bienfaiteur, Bénévole...) and change which
permissions each group holds. A registered user without any group is a free account ("inscrit").

Every account has the single Symfony role `ROLE_USER`. Rights are **not** stored in the security
token: voters read the user's groups on each request, so a change of groups or permissions applies
immediately, without logging the user out.

## Checking rights in code

| Need | PHP | Twig |
|---|---|---|
| Logged in | `#[IsGranted('ROLE_USER')]` | `{% if app.user %}` |
| A permission | `#[IsGranted('USER_MANAGE')]`, `$this->denyAccessUnlessGranted(Permission::UserManage->value)` | `{% if is_granted('USER_MANAGE') %}` |
| See a content (post, page, menu link, document) | `$this->denyAccessUnlessGranted(ContentVoter::VIEW, $post)` | `{% if is_granted('CONTENT_VIEW', link) %}` |

Restricted content implements `App\Security\RestrictedContentInterface` (`getVisibility()`,
`getAllowedGroups()`); `App\Security\Voter\ContentVoter` then decides:

- `public`: everyone;
- `authenticated`: any logged-in user;
- `groups`: members of at least one allowed group;
- users in a group with "all permissions" see everything.

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

Until the admin screens exist (roadmap step 3), accounts and groups are managed from the console:

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
| `comite@eplaneur.test` | Membre, Comité |
| `membre@eplaneur.test` | Membre |
| `inscrit@eplaneur.test` | none (free account) |
| `camille@eplaneur.test` | Comité (author of the demo posts) |
