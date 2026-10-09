<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Flight;
use App\Entity\FlightComment;
use App\Entity\Membership;
use App\Entity\MembershipSeason;
use App\Entity\MembershipType;
use App\Entity\User;
use App\Flight\FlightImporter;
use App\Repository\GroupRepository;
use App\Security\UserNotification;
use App\Tests\Unit\Flight\FlightSamples;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Member space: dashboard, my flights, profile, personal data (export, deletion with the choice for
 * the flights), and the choices that apply elsewhere (public flights, ranking, e-mails).
 */
final class MemberSpaceTest extends WebTestCase
{
    private const string PASSWORD = 'une phrase de passe solide';

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private User $pilot;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->pilot = $this->user('jeanne@example.org', 'Jeanne Pilote');
        $this->client->loginUser($this->pilot);
    }

    public function testTheDashboardOfANewAccount(): void
    {
        $season = (new MembershipSeason(2026))->setOpen(true);
        $season->addType((new MembershipType())->setName('Adulte')->setPrice(2500));
        $this->entityManager->persist($season);
        $this->entityManager->flush();

        $this->client->request('GET', '/mon-compte');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Bonjour Jeanne.');
        self::assertSelectorTextContains('.p-account-membership', 'Rejoignez le club');
        self::assertSelectorTextContains('.p-account-membership', "Adulte 25\u{A0}€");
        self::assertSelectorTextContains('body', 'Votre premier vol vous attend');
        self::assertSelectorExists('.p-account__nav a[aria-current="page"][href="/mon-compte"]');
    }

    public function testTheDashboardOfAMemberWithFlightsAndComments(): void
    {
        $season = (new MembershipSeason(2026))->setOpen(true);
        $season->addType($adult = (new MembershipType())->setName('Adulte')->setPrice(2500));
        $this->entityManager->persist($season);
        $membership = (new Membership($this->pilot, $season, new \DateTimeImmutable('2026-01-10')))->setType($adult)
            ->setFirstName('Jeanne')->setLastName('Pilote')->setAddress('x')->setPostalCode('1')->setCity('c')->setPhone('0')->setRulesAccepted(true);
        $membership->validate(null, new \DateTimeImmutable('2026-01-12'));
        $this->entityManager->persist($membership);
        $flight = $this->upload();
        $trainer = $this->user('camille@example.org', 'Camille Formatrice');
        $comment = (new FlightComment($flight, $trainer, new \DateTimeImmutable()))->setBody('Belle spirale, élargis un peu côté vent.')->setAt(130);
        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        $this->client->request('GET', '/mon-compte');

        self::assertSelectorTextContains('.p-account-membership', 'Le ciel se partage.');
        self::assertSelectorTextContains('.p-account__side', 'Adhésion 2026 validée');
        self::assertSelectorTextContains('.p-account-flights', 'Tâche test');
        self::assertSelectorTextContains('body', 'Camille Formatrice a commenté votre vol Tâche test à 2:10');
    }

    public function testMyFlightsAreFilteredAndMadePublicFromTheList(): void
    {
        $flight = $this->upload();

        $crawler = $this->client->request('GET', '/mon-compte/vols?saison=tout');
        self::assertSelectorTextContains('.p-account-flights', 'Tâche test');
        self::assertSelectorTextContains('.p-account-flights', 'Privé');

        $this->client->submit($crawler->selectButton('Rendre public')->form());
        self::assertResponseRedirects('/mon-compte/vols?saison=tout');
        $this->entityManager->clear();
        self::assertTrue($this->entityManager->find(Flight::class, $flight->getId())?->isPublic());

        $this->client->request('GET', '/mon-compte/vols?saison=tout&visibilite=prive');
        self::assertSelectorTextContains('body', 'Aucun vol ne correspond à ces filtres.');
    }

    public function testTheProfileIsChangedPanelByPanel(): void
    {
        $crawler = $this->client->request('GET', '/mon-compte/profil');
        $this->client->submit($crawler->filter('form[name="condor"]')->form(['condor[condorCn]' => 'jp1', 'condor[condorPilotName]' => 'Jeanne Pilote']));
        self::assertResponseRedirects('/mon-compte/profil#condor');

        $crawler = $this->client->followRedirect();
        $form = $crawler->filter('form[name="notifications"]')->form();
        $news = $form['notifications[notifications]'];
        \assert(\is_array($news));
        foreach ($news as $box) {
            \assert($box instanceof ChoiceFormField);
            'news' === $box->availableOptionValues()[0] ? $box->tick() : $box->untick();
        }
        $this->client->submit($form);

        $crawler = $this->client->request('GET', '/mon-compte/profil');
        $this->client->submit($crawler->filter('form[name="password_change"]')->form([
            'password_change[currentPassword]' => 'pas le bon',
            'password_change[plainPassword][first]' => 'nouvelle phrase de passe 2026',
            'password_change[plainPassword][second]' => 'nouvelle phrase de passe 2026',
        ]));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#password_change', 'Ce n’est pas votre mot de passe actuel.');

        $this->entityManager->clear();
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'jeanne@example.org']);
        self::assertSame('JP1', $user?->getCondorCn());
        self::assertSame([UserNotification::News], $user->getNotifications());
        self::assertSelectorTextContains('.p-account__side', 'CN JP1');
    }

    public function testTheEmailChangesOnceTheNewAddressIsConfirmed(): void
    {
        $crawler = $this->client->request('GET', '/mon-compte/profil');
        $this->client->submit($crawler->filter('form[name="email_change"]')->form(['email_change[email]' => 'jeanne.new@example.org']));
        self::assertResponseRedirects('/mon-compte/profil#email_change');
        self::assertQueuedEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertEmailAddressContains($email, 'To', 'jeanne.new@example.org');
        preg_match('#href="(https?://[^"]*/mon-compte/email/confirmer[^"]*)"#', (string) $email->getHtmlBody(), $link);
        self::assertNotEmpty($link);

        $this->client->request('GET', html_entity_decode($link[1]));
        self::assertResponseRedirects('/mon-compte/profil#email_change');
        $this->entityManager->clear();
        self::assertNotNull($this->entityManager->getRepository(User::class)->findOneBy(['email' => 'jeanne.new@example.org']));
    }

    public function testPrivacyChoicesApplyToNewFlightsAndTheRanking(): void
    {
        $crawler = $this->client->request('GET', '/mon-compte/confidentialite');
        $form = $crawler->filter('form[name="privacy"]')->form();
        $public = $form['privacy[flightsPublicByDefault]'];
        $ranking = $form['privacy[inRanking]'];
        \assert($public instanceof ChoiceFormField && $ranking instanceof ChoiceFormField);
        $public->tick();
        $ranking->untick();
        $this->client->submit($form);
        self::assertResponseRedirects('/mon-compte/confidentialite');

        $flight = $this->upload();
        self::assertTrue($flight->isPublic(), 'New flights are public by default');
        $this->client->request('GET', '/vols/records?saison=2026');
        self::assertSelectorTextNotContains('body', 'jeanne@example.org');
    }

    public function testTheDataAreExportedAsAZipFile(): void
    {
        $this->upload();
        $crawler = $this->client->request('GET', '/mon-compte/confidentialite');
        $this->client->submit($crawler->selectButton('Télécharger mes données')->form());

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/zip');
        $file = (string) tempnam(sys_get_temp_dir(), 'zip');
        file_put_contents($file, $this->client->getInternalResponse()->getContent());
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($file));
        $account = json_decode((string) $zip->getFromName('compte.json'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('jeanne@example.org', $account['email']);
        $flights = json_decode((string) $zip->getFromName('vols.json'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertCount(1, $flights);
        self::assertNotFalse($zip->locateName($flights[0]['fichier']), 'The track is in the archive');
        self::assertNotFalse($zip->locateName('parcours_de_formation.json'));
        $zip->close();
    }

    public function testTheAccountIsDeletedAndItsFlightsKeptAnonymised(): void
    {
        $flight = $this->upload();
        $path = static::getContainer()->get(FlightImporter::class)->pathOf($flight);
        $comment = (new FlightComment($flight, $this->pilot, new \DateTimeImmutable()))->setBody('Note pour moi.');
        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/mon-compte/suppression');
        self::assertSelectorTextContains('body', 'Garder mes vols de façon anonyme');
        $this->client->submit($crawler->filter('form[name="deletion"]')->form(['deletion[keepFlights]' => '1']));
        self::assertResponseRedirects('/mon-compte/suppression/confirmer?vols=1');

        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.p-account__danger-note', 'restent sur le site de façon anonyme');
        $this->client->submit($crawler->filter('form[name="deletion_confirmation"]')->form(['deletion_confirmation[password]' => 'pas le bon']));
        self::assertResponseStatusCodeSame(422);

        $crawler = $this->client->request('GET', '/mon-compte/suppression/confirmer?vols=1');
        $this->client->submit($crawler->filter('form[name="deletion_confirmation"]')->form(['deletion_confirmation[password]' => self::PASSWORD, 'deletion_confirmation[understood]' => '1']));
        self::assertResponseRedirects('/compte-supprime');

        $this->entityManager->clear();
        self::assertNull($this->entityManager->getRepository(User::class)->findOneBy(['email' => 'jeanne@example.org']));
        $kept = $this->entityManager->find(Flight::class, $flight->getId());
        self::assertSame(Flight::ANONYMOUS_PILOT, $kept?->getPilot());
        self::assertNull($kept->getOwner());
        self::assertFileDoesNotExist($path, 'The file holds the pilot name');
        self::assertSame(0, $this->entityManager->getRepository(FlightComment::class)->count([]));

        $this->client->request('GET', '/mon-compte');
        self::assertResponseRedirects('/connexion', null, 'Logged out');
    }

    public function testTheAccountIsDeletedWithItsFlights(): void
    {
        $flight = $this->upload();
        $crawler = $this->client->request('GET', '/mon-compte/suppression/confirmer?vols=0');
        $this->client->submit($crawler->filter('form[name="deletion_confirmation"]')->form(['deletion_confirmation[password]' => self::PASSWORD, 'deletion_confirmation[understood]' => '1']));

        self::assertResponseRedirects('/compte-supprime');
        $this->entityManager->clear();
        self::assertNull($this->entityManager->find(Flight::class, $flight->getId()));
    }

    public function testTheLastAdministratorCannotDeleteTheirAccount(): void
    {
        $admin = $this->user('admin@example.org', 'Admin', 'admin');
        foreach ($this->entityManager->getRepository(User::class)->findAll() as $other) {
            if ($other !== $admin && $other->hasAllPermissions()) {
                $this->entityManager->remove($other);
            }
        }
        $this->entityManager->flush();
        $this->client->loginUser($admin);

        $this->client->request('GET', '/mon-compte/confidentialite');
        self::assertSelectorTextContains('body', 'Vous êtes le seul administrateur');
        $this->client->request('GET', '/mon-compte/suppression');
        self::assertResponseRedirects('/mon-compte/confidentialite');
    }

    public function testThePilotIsToldOfACommentOnTheirFlight(): void
    {
        $flight = $this->upload();
        $trainer = $this->user('camille@example.org', 'Camille Formatrice', 'admin');
        $this->client->loginUser($trainer);
        $crawler = $this->client->request('GET', '/vols/'.$flight->getId());
        $this->client->submit($crawler->selectButton('Commenter')->form(['flight_comment[body]' => 'Belle spirale.']));

        self::assertQueuedEmailCount(1);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailAddressContains($email, 'To', 'jeanne@example.org');
        self::assertEmailHtmlBodyContains($email, 'Belle spirale.');
    }

    private function upload(): Flight
    {
        $file = sys_get_temp_dir().'/task.igc';
        file_put_contents($file, FlightSamples::igc(FlightSamples::glideThermalGlide(), extraHeaders: FlightSamples::igcTask('Tâche test', [0, 0, 7000])));

        return static::getContainer()->get(FlightImporter::class)->import($file, 'task.igc', $this->entityManager->find(User::class, $this->pilot->getId()));
    }

    private function user(string $email, string $name, string ...$groups): User
    {
        $user = (new User())->setEmail($email)->setDisplayName($name)->setVerified(true);
        $user->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, self::PASSWORD));
        foreach ($groups as $code) {
            $user->addGroup(static::getContainer()->get(GroupRepository::class)->findOneByCode($code) ?? throw new \LogicException($code));
        }
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
