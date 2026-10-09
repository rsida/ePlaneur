<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Membership;
use App\Entity\MembershipSeason;
use App\Entity\MembershipType;
use App\Entity\User;
use App\Membership\MembershipStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\DomCrawler\Form;

/**
 * Membership on the site: the fees for everyone, the request of a logged-in account, its follow-up
 * and the certificate of a validated membership.
 */
final class MembershipTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private MembershipSeason $season;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->season = (new MembershipSeason(2026))->setOpen(true)->setPaymentInstructions('Virement au club, libellé « Adhésion 2026 ».');
        $this->season->addType((new MembershipType())->setName('Moins de 25 ans')->setPrice(1000)->setMaxAge(24)->setPosition(1));
        $this->season->addType((new MembershipType())->setName('Adulte')->setPrice(2500)->setPosition(2));
        $this->entityManager->persist($this->season);
        $this->entityManager->flush();
    }

    public function testVisitorsSeeTheFeesAndAreAskedToLogIn(): void
    {
        $this->client->request('GET', '/adhesion');
        self::assertResponseIsSuccessful();
        $this->client->loginUser($this->user('jeanne@example.org'));
        $this->client->request('GET', '/adhesion');
        self::assertResponseRedirects('/mon-compte/adhesion', null, 'An account goes to its space');
        $this->client->restart();
        $this->client->request('GET', '/adhesion');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.p-membership__fees', 'Moins de 25 ans');
        self::assertSelectorTextContains('.p-membership__fees', "25\u{A0}€");
        self::assertSelectorTextContains('.c-callout', 'Créer mon compte');
        self::assertSelectorNotExists('form[name="membership_request"]');
    }

    public function testAnAccountSendsItsRequest(): void
    {
        $this->client->loginUser($this->user('jeanne@example.org'));
        $crawler = $this->client->request('GET', '/mon-compte/adhesion');
        $form = $crawler->filter('form[name="membership_request"]')->form($this->data(['membership_request[donation]' => '5']));
        self::choice($form, 'membership_request[type]')->select((string) $this->type('Adulte')->getId());
        $this->client->submit($form);

        self::assertResponseRedirects('/mon-compte/adhesion');
        self::assertQueuedEmailCount(1);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailHtmlBodyContains($email, 'Total : 30,00 €');
        self::assertEmailHtmlBodyContains($email, 'Adhésion 2026');

        $membership = $this->entityManager->getRepository(Membership::class)->findOneBy([]);
        self::assertSame(MembershipStatus::Requested, $membership?->getStatus());
        self::assertSame(2500, $membership->getFee());
        self::assertSame(500, $membership->getDonation());

        $this->client->followRedirect();
        self::assertSelectorTextContains('.p-membership__mine', 'Demande envoyée, paiement attendu');
        self::assertSelectorTextContains('.p-membership__instructions', 'Virement au club');
        self::assertSelectorNotExists('form[name="membership_request"]', 'One request per season');
    }

    public function testTheReducedRateChecksTheAge(): void
    {
        $this->client->loginUser($this->user('jeanne@example.org'));
        $crawler = $this->client->request('GET', '/mon-compte/adhesion');
        $form = $crawler->filter('form[name="membership_request"]')->form($this->data(['membership_request[birthDate]' => '1980-04-01']));
        self::choice($form, 'membership_request[type]')->select((string) $this->type('Moins de 25 ans')->getId());
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.c-alert--error', 'réservé aux adhérents de 24 ans au plus');
    }

    public function testTheRulesMustBeAccepted(): void
    {
        $this->client->loginUser($this->user('jeanne@example.org'));
        $crawler = $this->client->request('GET', '/mon-compte/adhesion');
        $form = $crawler->filter('form[name="membership_request"]')->form($this->data());
        self::choice($form, 'membership_request[type]')->select((string) $this->type('Adulte')->getId());
        self::choice($form, 'membership_request[rulesAccepted]')->untick();
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'accepter les statuts et le règlement intérieur');
    }

    public function testTheCertificateIsForTheMemberOnly(): void
    {
        $member = $this->user('jeanne@example.org');
        $membership = (new Membership($member, $this->season, new \DateTimeImmutable('2026-01-10')))
            ->setType($this->type('Adulte'))->setFirstName('Jeanne')->setLastName('Pilote')->setBirthDate(new \DateTimeImmutable('1990-01-01'))
            ->setAddress('1 rue')->setPostalCode('97400')->setCity('Saint-Denis')->setPhone('0692')->setRulesAccepted(true);
        $this->entityManager->persist($membership);
        $this->entityManager->flush();

        $this->client->loginUser($member);
        $this->client->request('GET', '/adhesion/'.$membership->getId().'/attestation');
        self::assertResponseStatusCodeSame(404, 'Not validated yet');

        $membership->validate(null, new \DateTimeImmutable('2026-01-12'));
        $this->entityManager->flush();
        $this->client->request('GET', '/adhesion/'.$membership->getId().'/attestation');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.p-certificate__sheet', 'Jeanne Pilote');
        self::assertSelectorTextContains('.p-certificate__sheet', 'ADH-2026-');

        $this->client->request('GET', '/mon-compte');
        self::assertSelectorTextContains('body', 'Saison 2026 · Validée');

        $this->client->loginUser($this->user('autre@example.org'));
        $this->client->request('GET', '/adhesion/'.$membership->getId().'/attestation');
        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @param array<string, string> $overrides
     *
     * @return array<string, string>
     */
    private function data(array $overrides = []): array
    {
        return $overrides + [
            'membership_request[firstName]' => 'Jeanne',
            'membership_request[lastName]' => 'Pilote',
            'membership_request[birthDate]' => '1990-04-01',
            'membership_request[address]' => '12 rue des Thermiques',
            'membership_request[postalCode]' => '97400',
            'membership_request[city]' => 'Saint-Denis',
            'membership_request[country]' => 'FR',
            'membership_request[phone]' => '+262 692 12 34 56',
            'membership_request[rulesAccepted]' => '1',
        ];
    }

    private static function choice(Form $form, string $name): ChoiceFormField
    {
        $field = $form[$name];
        \assert($field instanceof ChoiceFormField);

        return $field;
    }

    private function type(string $name): MembershipType
    {
        foreach ($this->season->getTypes() as $type) {
            if ($type->getName() === $name) {
                return $type;
            }
        }
        throw new \LogicException($name);
    }

    private function user(string $email): User
    {
        $user = (new User())->setEmail($email)->setDisplayName($email)->setVerified(true)->setPassword('x');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
