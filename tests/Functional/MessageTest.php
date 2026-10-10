<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Account\AccountDeleter;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use App\Repository\GroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;

/**
 * Messages (roadmap 9c): members write to each other and to the committee; the readers get an
 * e-mail; nobody else reads a conversation.
 */
final class MessageTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testTwoMembersWriteToEachOther(): void
    {
        $jeanne = $this->user('jeanne@example.org', 'Jeanne Pilote', 'member');
        $paul = $this->user('paul@example.org', 'Paul Planeur', 'member');
        $this->client->loginUser($jeanne);

        $crawler = $this->client->request('GET', '/mon-compte/messages');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Aucun message pour l’instant.');
        $crawler = $this->client->click($crawler->selectLink('Nouveau message')->link());
        $this->client->submit($crawler->selectButton('Envoyer')->form([
            'conversation[recipient]' => 'user:'.$paul->getId(),
            'conversation[subject]' => 'Vol de jeudi',
            'conversation[body]' => 'Tu voles jeudi soir sur la session C3-c046a ?',
        ]));
        $conversation = $this->entityManager->getRepository(Conversation::class)->findOneBy([]);
        self::assertNotNull($conversation);
        self::assertResponseRedirects('/mon-compte/messages/'.$conversation->getId());
        self::assertQueuedEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertEmailAddressContains($email, 'To', 'paul@example.org');
        self::assertEmailHtmlBodyContains($email, 'Tu voles jeudi soir');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.p-thread', 'Tu voles jeudi soir sur la session C3-c046a ?');

        // Paul sees one unread conversation, reads it and answers
        $this->client->loginUser($paul);
        $crawler = $this->client->request('GET', '/mon-compte/messages');
        self::assertSelectorTextContains('.p-account__count', '1');
        self::assertSelectorTextContains('.p-messages__item--unread', 'Jeanne Pilote');
        $crawler = $this->client->click($crawler->selectLink('Vol de jeudi')->link());
        self::assertSelectorNotExists('.p-account__count');
        $this->client->submit($crawler->selectButton('Répondre')->form(['message[body]' => 'Oui, à 20 h 30.']));
        self::assertResponseRedirects('/mon-compte/messages/'.$conversation->getId().'#dernier');

        $this->client->loginUser($jeanne);
        $this->client->request('GET', '/mon-compte/messages/'.$conversation->getId());
        self::assertSelectorTextContains('.p-thread__message:last-child', 'Oui, à 20 h 30.');
        self::assertSelectorTextContains('.p-thread__message:last-child', 'Paul Planeur');

        // Someone else cannot read it
        $this->client->loginUser($this->user('autre@example.org', 'Autre Membre', 'member'));
        $this->client->request('GET', '/mon-compte/messages/'.$conversation->getId());
        self::assertResponseStatusCodeSame(403);
    }

    public function testAMemberWritesToTheCommittee(): void
    {
        $jeanne = $this->user('jeanne@example.org', 'Jeanne Pilote', 'member');
        $president = $this->user('president@example.org', 'Anne Présidente', 'committee');
        $member = $this->user('paul@example.org', 'Paul Planeur', 'member');
        $this->client->loginUser($jeanne);

        $crawler = $this->client->request('GET', '/mon-compte/messages/nouveau');
        $this->client->submit($crawler->selectButton('Envoyer')->form([
            'conversation[recipient]' => 'group:committee',
            'conversation[subject]' => 'Adhésion',
            'conversation[body]' => 'Puis-je payer en deux fois ?',
        ]));
        $conversation = $this->entityManager->getRepository(Conversation::class)->findOneBy([]);
        self::assertNotNull($conversation);
        self::assertSame('committee', $conversation->getGroup()?->getCode());
        // The committee member is told, not the other member
        self::assertQueuedEmailCount(1);
        self::assertEmailAddressContains(self::getMailerMessage() ?? throw new \LogicException(), 'To', 'president@example.org');

        $this->client->loginUser($president);
        $crawler = $this->client->request('GET', '/mon-compte/messages');
        self::assertSelectorTextContains('.p-messages__item--unread', 'Jeanne Pilote');
        $crawler = $this->client->click($crawler->selectLink('Adhésion')->link());
        self::assertSelectorTextContains('main', 'Conversation avec le comité directeur, ouverte par Jeanne Pilote.');
        $this->client->submit($crawler->selectButton('Répondre')->form(['message[body]' => 'Oui, écrivez au trésorier.']));

        $this->client->loginUser($member);
        $this->client->request('GET', '/mon-compte/messages/'.$conversation->getId());
        self::assertResponseStatusCodeSame(403);
    }

    public function testOnlyMembersWrite(): void
    {
        $this->client->loginUser($this->user('inscrit@example.org', 'Inscrit Seul'));

        $this->client->request('GET', '/mon-compte/messages');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'La messagerie est réservée aux membres du club');
        $this->client->request('GET', '/mon-compte/messages/nouveau');
        self::assertResponseStatusCodeSame(403);
    }

    public function testTheMessagesGoWithTheAccount(): void
    {
        $jeanne = $this->user('jeanne@example.org', 'Jeanne Pilote', 'member');
        $paul = $this->user('paul@example.org', 'Paul Planeur', 'member');
        $this->client->loginUser($jeanne);
        $crawler = $this->client->request('GET', '/mon-compte/messages/nouveau?a='.$paul->getId());
        self::assertSame('user:'.$paul->getId(), $crawler->filter('#conversation_recipient option[selected]')->attr('value'));
        $this->client->submit($crawler->selectButton('Envoyer')->form(['conversation[subject]' => 'Bonjour', 'conversation[body]' => 'Premier message.']));

        $this->entityManager->clear();
        $jeanne = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'jeanne@example.org']) ?? throw new \LogicException();
        static::getContainer()->get(AccountDeleter::class)->delete($jeanne, keepFlights: false);
        $this->entityManager->clear();

        // Her only message gone, the conversation goes too
        self::assertSame(0, $this->entityManager->getRepository(Message::class)->count([]));
        self::assertSame(0, $this->entityManager->getRepository(Conversation::class)->count([]));
    }

    private function user(string $email, string $name, ?string $group = null): User
    {
        $user = (new User())->setEmail($email)->setDisplayName($name)->setVerified(true)->setPassword('x');
        if (null !== $group) {
            $user->addGroup(static::getContainer()->get(GroupRepository::class)->findOneByCode($group) ?? throw new \LogicException($group));
        }
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
