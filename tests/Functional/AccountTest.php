<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Repository\GroupRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Registration, e-mail confirmation, login, logout and password reset, driven through the pages.
 */
final class AccountTest extends WebTestCase
{
    private const string PASSWORD = 'Un vol en planeur au-dessus des Alpes';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testRegistrationRequiresEmailConfirmationBeforeLogin(): void
    {
        $this->client->request('GET', '/inscription');
        $this->client->submitForm('Créer mon compte', [
            'registration_form[displayName]' => 'Jeanne Planeur',
            'registration_form[email]' => 'Jeanne@Example.org',
            'registration_form[plainPassword][first]' => self::PASSWORD,
            'registration_form[plainPassword][second]' => self::PASSWORD,
        ]);

        self::assertResponseRedirects('/inscription/verifiez-vos-e-mails');
        self::assertQueuedEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertEmailAddressContains($email, 'To', 'jeanne@example.org');
        $confirmationLink = $this->extractLink($email, '/inscription/confirmer');

        $user = $this->users()->findOneByEmail('jeanne@example.org');
        self::assertNotNull($user);
        self::assertFalse($user->isVerified());
        self::assertCount(0, $user->getGroups());

        // Not confirmed yet: login refused
        $this->login('jeanne@example.org', self::PASSWORD);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.c-alert--error', 'Confirmez d’abord votre adresse e-mail');

        $this->client->request('GET', $confirmationLink);
        self::assertResponseRedirects('/connexion');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.c-alert--success', 'adresse e-mail est confirmée');

        $this->login('jeanne@example.org', self::PASSWORD);
        self::assertResponseRedirects('/mon-compte');
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Bonjour Jeanne Planeur');
        self::assertSelectorTextContains('header', 'Mon compte');
    }

    public function testRegistrationRejectsAWeakPasswordAndAnEmailAlreadyUsed(): void
    {
        $this->createUser('taken@example.org');

        $this->client->request('GET', '/inscription');
        $this->client->submitForm('Créer mon compte', [
            'registration_form[displayName]' => 'Jo',
            'registration_form[email]' => 'taken@example.org',
            'registration_form[plainPassword][first]' => 'azerty',
            'registration_form[plainPassword][second]' => 'azerty',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.c-field--invalid', 'Un compte existe déjà avec cette adresse e-mail.');
        self::assertSelectorTextContains('body', 'au moins 10 caractères');
        self::assertQueuedEmailCount(0);
    }

    public function testInvalidConfirmationLinkIsRejected(): void
    {
        $user = $this->createUser('jane@example.org', verified: false);

        $this->client->request('GET', '/inscription/confirmer?id='.$user->getId().'&signature=forged&token=forged&expires=9999999999');

        self::assertResponseRedirects('/inscription/renvoyer-confirmation');
        self::assertFalse($this->users()->findOneByEmail('jane@example.org')?->isVerified());
    }

    public function testResendingTheConfirmationLinkDoesNotRevealAccounts(): void
    {
        $this->createUser('pending@example.org', verified: false);

        // Same answer for both addresses; only the pending account receives a link
        foreach (['pending@example.org' => 1, 'unknown@example.org' => 0] as $address => $sent) {
            $this->client->request('GET', '/inscription/renvoyer-confirmation');
            $this->client->submitForm('Envoyer le lien', ['account_email_form[email]' => $address]);
            self::assertResponseRedirects('/inscription/verifiez-vos-e-mails');
            self::assertQueuedEmailCount($sent);
        }
    }

    public function testLoginWithWrongPasswordFails(): void
    {
        $this->createUser('jane@example.org');

        $this->login('jane@example.org', 'not the right password');

        self::assertResponseRedirects('/connexion');
        $this->client->followRedirect();
        self::assertSelectorExists('.c-alert--error');
    }

    public function testAccountPageNeedsALoginAndShowsTheGroups(): void
    {
        $this->client->request('GET', '/mon-compte');
        self::assertResponseRedirects('/connexion');

        $user = $this->createUser('jane@example.org', groups: ['member', 'committee']);
        $this->client->loginUser($user);
        $this->client->request('GET', '/mon-compte');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.c-definition-list', 'Membre');
        self::assertSelectorTextContains('.c-definition-list', 'Comité');

        $this->client->clickLink('Se déconnecter');
        $this->client->request('GET', '/mon-compte');
        self::assertResponseRedirects('/connexion');
    }

    public function testPasswordReset(): void
    {
        $this->createUser('jane@example.org');

        $this->client->request('GET', '/mot-de-passe-oublie');
        $this->client->submitForm('Envoyer le lien', ['account_email_form[email]' => 'jane@example.org']);
        self::assertResponseRedirects('/mot-de-passe-oublie/verifiez-vos-e-mails');
        self::assertQueuedEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);

        // The token moves from the URL to the session
        $this->client->request('GET', $this->extractLink($email, '/mot-de-passe-oublie/nouveau/'));
        self::assertResponseRedirects('/mot-de-passe-oublie/nouveau');
        $this->client->followRedirect();

        $newPassword = 'Une nouvelle ascendance thermique';
        $this->client->submitForm('Enregistrer le mot de passe', [
            'change_password_form[plainPassword][first]' => $newPassword,
            'change_password_form[plainPassword][second]' => $newPassword,
        ]);
        self::assertResponseRedirects('/connexion');

        $this->login('jane@example.org', self::PASSWORD);
        self::assertResponseRedirects('/connexion');

        $this->login('jane@example.org', $newPassword);
        self::assertResponseRedirects('/mon-compte');
    }

    public function testPasswordResetForAnUnknownAddressLooksTheSame(): void
    {
        $this->client->request('GET', '/mot-de-passe-oublie');
        $this->client->submitForm('Envoyer le lien', ['account_email_form[email]' => 'nobody@example.org']);

        self::assertResponseRedirects('/mot-de-passe-oublie/verifiez-vos-e-mails');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertQueuedEmailCount(0);
    }

    private function login(string $email, string $password): void
    {
        $this->client->request('GET', '/connexion');
        // Stateless CSRF: without the Stimulus controller, browsers get "Jeton CSRF invalide"
        self::assertSelectorExists('input[name="_csrf_token"][data-controller="csrf-protection"]');
        $this->client->submitForm('Se connecter', ['email' => $email, 'password' => $password]);
    }

    /**
     * @param list<string> $groups
     */
    private function createUser(string $email, bool $verified = true, array $groups = []): User
    {
        $container = static::getContainer();
        $user = (new User())->setEmail($email)->setDisplayName('Jane Doe')->setVerified($verified);
        $user->setPassword($container->get(UserPasswordHasherInterface::class)->hashPassword($user, self::PASSWORD));
        foreach ($groups as $code) {
            $group = $container->get(GroupRepository::class)->findOneByCode($code);
            self::assertNotNull($group, \sprintf('Default group "%s" missing: run make test-db.', $code));
            $user->addGroup($group);
        }

        $entityManager = $container->get(EntityManagerInterface::class);
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function users(): UserRepository
    {
        $repository = static::getContainer()->get(UserRepository::class);
        static::getContainer()->get(EntityManagerInterface::class)->clear();

        return $repository;
    }

    private function extractLink(Email $email, string $path): string
    {
        self::assertMatchesRegularExpression('#href="(https?://[^"]*'.preg_quote($path, '#').'[^"]*)"#', (string) $email->getHtmlBody());
        preg_match('#href="(https?://[^"]*'.preg_quote($path, '#').'[^"]*)"#', (string) $email->getHtmlBody(), $matches);

        return html_entity_decode($matches[1]);
    }
}
