# Tests and quality

Tests run **inside the dev PHP container**, with `APP_ENV=test`, next to the running dev environment:
no need to stop it. The test environment has its own cache (`var/cache/test`) and its own database
(`eplaneur_test`), so dev data is never touched.

## Commands

| Command | Description |
|---|---|
| `make test` | PHPUnit then Behat |
| `make test-unit` | PHPUnit (suites `unit` and `functional`) |
| `make test-unit f=HomeControllerTest` | Filter by test class or method |
| `make test-behat` | Every Behat feature |
| `make test-behat f=features/home.feature` | One feature (append `:12` for one scenario line) |
| `make test-db` | Drop and recreate `eplaneur_test` with the migrations |

Run `make test-db` after adding migrations.

To run a single PHPUnit suite:

```bash
docker compose exec -e APP_ENV=test php php bin/phpunit --testsuite unit
```

## Test database

- Doctrine appends `_test` to the database name in the test environment
  (`config/packages/doctrine.yaml`, `dbname_suffix`).
- The application user is allowed to create it thanks to `docker/mariadb/init/01-test-database.sh`,
  executed when the dev database volume is created.
- [DAMA DoctrineTestBundle](https://github.com/dmaksimov/DoctrineTestBundle) wraps every PHPUnit test
  in a transaction rolled back at the end: tests do not leak data into each other.

## PHPUnit

`phpunit.dist.xml`, two suites:

| Suite | Directory | Base class |
|---|---|---|
| `unit` | `tests/Unit` | `PHPUnit\Framework\TestCase` (no kernel) |
| `functional` | `tests/Functional` | `WebTestCase` (HTTP) / `KernelTestCase` (services) |

Back-office tests live in `tests/Functional/Admin/`: one EasyAdmin `AbstractCrudTestCase` per screen
(`generateIndexUrl()`, `generateEditFormUrl($id)`...) plus `AdminAccessTest` for permissions. See
[admin](admin.md#adding-a-screen).

The configuration fails on deprecations, notices and warnings.

## Behat

Behat 4 with [FriendsOfBehat SymfonyExtension](https://github.com/FriendsOfBehat/SymfonyExtension):
scenarios run against the Symfony kernel through `KernelBrowser` (no web server, no browser, no
JavaScript).

| Path | Content |
|---|---|
| `behat.dist.php` | Configuration (Behat 4 reads PHP config only). A git-ignored `behat.php` can override it locally |
| `features/` | `.feature` files |
| `tests/Behat/` | Contexts, registered as services by `config/services_test.yaml` (autowiring available) |

`tests/Behat/WebContext.php` provides:

```gherkin
Given I am on "/path"
When I go to "/path"
Then the response status code should be 200
Then I should see "text"
```

Steps are declared with attributes (`#[Given]`, `#[When]`, `#[Then]` from `Behat\Step`).

> The Mink extension (`friends-of-behat/mink-extension`) does not support Symfony 8 yet, which is why
> the contexts use `KernelBrowser` directly.

## Quality

| Command | Tool |
|---|---|
| `make cs` / `make cs-fix` | PHP-CS-Fixer, `@Symfony` rules (`.php-cs-fixer.dist.php`) |
| `make phpstan` | PHPStan level 6 with the Symfony and Doctrine extensions (`phpstan.dist.neon`) |
| `make lint` | `lint:container`, `lint:twig`, `lint:yaml`, Doctrine mapping validation |
| `make qa` | `lint` + `cs` + `phpstan` + `test`, what a CI should run |

PHPStan reads the dev container dump (`var/cache/dev`): run `make cc` first if it reports unknown services.
