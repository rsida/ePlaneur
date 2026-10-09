<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Contact\ContactSubject;
use App\Entity\ContactRequest;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Contact form: sent by a visitor or from the member space, kept for the back-office and e-mailed
 * to the club; robots are turned away.
 */
final class ContactTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testAVisitorWritesToTheClub(): void
    {
        $crawler = $this->client->request('GET', '/contact');
        $this->client->submit($crawler->filter('form[name="contact"]')->form([
            'contact[name]' => 'Paul Visiteur',
            'contact[email]' => 'paul@example.org',
            'contact[subject]' => 'training',
            'contact[message]' => 'Bonjour, comment commencer la formation sur Condor 2 ?',
        ]));

        self::assertResponseRedirects('/contact?envoye=1');
        self::assertQueuedEmailCount(1);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailAddressContains($email, 'To', 'club-eplaneur@outlook.fr');
        self::assertEmailAddressContains($email, 'Reply-To', 'paul@example.org');
        self::assertEmailHtmlBodyContains($email, 'comment commencer la formation');

        $request = $this->entityManager->getRepository(ContactRequest::class)->findOneBy([]);
        self::assertSame(ContactSubject::Training, $request?->getSubject());
        self::assertNull($request->getUser());

        $this->client->followRedirect();
        self::assertSelectorTextContains('.p-contact__sent', 'Message envoyé');
    }

    public function testAnAccountWritesFromItsSpaceWithItsDetails(): void
    {
        $user = (new User())->setEmail('jeanne@example.org')->setDisplayName('Jeanne Pilote')->setVerified(true)->setPassword('x');
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->client->loginUser($user);

        $this->client->request('GET', '/contact');
        self::assertResponseRedirects('/mon-compte/contact');
        $crawler = $this->client->followRedirect();
        self::assertSame('Jeanne Pilote', $crawler->filter('#contact_name')->attr('value'));
        $this->client->submit($crawler->filter('form[name="contact"]')->form(['contact[subject]' => 'network_flights', 'contact[message]' => 'Le plan C3-c060 est-il le bon pour mercredi ?']));

        self::assertResponseRedirects('/mon-compte/contact?envoye=1');
        self::assertSame('jeanne@example.org', $this->entityManager->getRepository(ContactRequest::class)->findOneBy([])?->getUser()?->getEmail());
    }

    public function testRobotsAreTurnedAway(): void
    {
        $crawler = $this->client->request('GET', '/contact');
        $this->client->submit($crawler->filter('form[name="contact"]')->form([
            'contact[name]' => 'Robot',
            'contact[email]' => 'robot@example.org',
            'contact[subject]' => 'other',
            'contact[message]' => 'Achetez nos produits miracles dès maintenant.',
            'contact[website]' => 'https://spam.example',
        ]));

        self::assertResponseRedirects('/contact?envoye=1', null, 'It looks sent');
        self::assertQueuedEmailCount(0);
        self::assertSame(0, $this->entityManager->getRepository(ContactRequest::class)->count([]));
    }

    public function testFieldsAreChecked(): void
    {
        $crawler = $this->client->request('GET', '/contact');
        $this->client->submit($crawler->filter('form[name="contact"]')->form(['contact[name]' => '', 'contact[email]' => 'pas-une-adresse', 'contact[message]' => 'Court']));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Saisissez une adresse complète');
        self::assertSelectorTextContains('body', 'Choisissez l’objet de votre message.');
    }
}
