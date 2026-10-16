<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Account\NameShown;
use App\Entity\Group;
use App\Entity\SchoolClass;
use App\Entity\User;
use App\Security\Permission;
use App\Tests\Functional\Admin\AdminUsers;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Teacher space: a teacher creates a class, pupils join it with its link (first name or pseudonym),
 * the teacher follows their steps and flights; the pupil leaves when they want.
 */
final class SchoolClassTest extends WebTestCase
{
    use AdminUsers;

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testOnlyTeachersCreateClasses(): void
    {
        $this->client->loginUser($this->userIn($this->entityManager, 'membre@example.org', 'member'));
        $this->client->request('GET', '/mon-compte/classes');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#nouvelle');
        self::assertSelectorTextContains('main', 'Vous n’êtes dans aucune classe');
        $this->client->request('GET', '/mon-compte');
        self::assertSelectorNotExists('a[href="/mon-compte/classes"]', 'No menu item for an account with no class');

        $this->client->loginUser($this->teacher());
        $this->client->request('GET', '/mon-compte');
        self::assertSelectorExists('a[href="/mon-compte/classes"]');
        $this->client->request('GET', '/mon-compte/classes');
        $this->client->submitForm('Créer la classe', ['school_class[name]' => '', 'school_class[school]' => '']);
        self::assertResponseStatusCodeSame(422);
        $this->client->submitForm('Créer la classe', ['school_class[name]' => 'BIA 2026-2027', 'school_class[school]' => 'Lycée Roland-Garros']);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'BIA 2026-2027');
        self::assertSelectorTextContains('#lien', '/classe/');
    }

    public function testAPupilJoinsWithTheLinkAndTheTeacherFollowsThem(): void
    {
        $teacher = $this->teacher();
        $class = $this->schoolClass($teacher);
        $lea = $this->userIn($this->entityManager, 'lea@example.org')->setDisplayName('Léa Petit')->setFlightsPublicByDefault(true);
        $this->entityManager->flush();
        $link = '/classe/'.$class->getCode();

        $this->client->request('GET', $link);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Créer mon compte');
        $this->client->request('GET', $link.'/connexion');
        self::assertResponseRedirects('/connexion');

        $this->client->loginUser($lea);
        $this->client->request('GET', $link);
        $this->client->submitForm('Rejoindre la classe', ['class_join[nameShown]' => 'pseudonym', 'class_join[pseudonym]' => '']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Choisissez votre pseudonyme.');
        $this->client->submitForm('Rejoindre la classe', ['class_join[nameShown]' => 'pseudonym', 'class_join[pseudonym]' => 'Faucon']);
        self::assertResponseRedirects('/mon-compte/classes');
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'BIA 2026-2027');
        self::assertSelectorExists('a[href="/mon-compte/classes"]', 'A pupil has the menu item');
        $lea = $this->reload($lea);
        self::assertSame('Faucon', $lea->getPublicName());
        self::assertFalse($lea->isFlightsPublicByDefault(), 'Their next flights are private');

        // While in a class, the others never see their name
        $this->client->request('GET', '/mon-compte/profil');
        $this->client->submitForm('Enregistrer le nom affiché', ['display_name[displayName]' => 'Léa Petit', 'display_name[nameShown]' => 'name', 'display_name[pseudonym]' => 'Faucon']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#display_name', 'Tant que vous êtes dans une classe');

        // Another member cannot see the class nor the logbook of the pupil
        $this->client->loginUser($this->userIn($this->entityManager, 'max@example.org'));
        $this->client->request('GET', '/mon-compte/classes/'.$class->getId());
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/vols/carnet/'.$lea->getId());
        self::assertResponseStatusCodeSame(403);

        $this->client->loginUser($teacher);
        $this->client->request('GET', '/mon-compte/classes/'.$class->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.p-class__table', 'Léa Petit');
        self::assertSelectorTextContains('.p-class__table', 'vu comme Faucon');
        self::assertSelectorCount(8, '.p-class__table tbody tr:first-child .p-class__state');
        $this->client->request('GET', '/vols/carnet/'.$lea->getId());
        self::assertResponseIsSuccessful('The teacher reads the logbook of their pupil');
        self::assertSelectorNotExists('form[name="step_1"]', 'A teacher does not validate steps');

        // The pupil leaves: the teacher no longer sees them
        $this->client->loginUser($lea);
        $this->client->request('GET', '/mon-compte/classes');
        $this->client->submitForm('Quitter la classe');
        self::assertResponseRedirects('/mon-compte/classes');
        self::assertCount(0, $this->reload($class)->getPupils());
        $this->client->loginUser($teacher);
        $this->client->request('GET', '/vols/carnet/'.$lea->getId());
        self::assertResponseStatusCodeSame(403);
    }

    public function testTheTeacherRenewsTheLinkRemovesAPupilAndDeletesTheClass(): void
    {
        $teacher = $this->teacher();
        $class = $this->schoolClass($teacher);
        $pupil = $this->userIn($this->entityManager, 'lea@example.org')->setNameShown(NameShown::FirstName);
        $class->addPupil($pupil);
        $this->entityManager->flush();
        $old = $class->getCode();
        $this->client->loginUser($teacher);

        $this->client->request('GET', '/mon-compte/classes/'.$class->getId());
        $this->client->submitForm('Changer le lien');
        self::assertResponseRedirects();
        $class = $this->reload($class);
        self::assertNotSame($old, $class->getCode());
        $this->client->request('GET', '/classe/'.$old);
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/mon-compte/classes/'.$class->getId());
        $this->client->submitForm('Enregistrer', ['school_class[name]' => 'BIA 2026-2027', 'school_class[open]' => false]);
        self::assertResponseRedirects();
        $this->client->request('GET', '/classe/'.$class->getCode());
        self::assertResponseStatusCodeSame(404, 'A closed link lets nobody join');

        $this->client->request('GET', '/mon-compte/classes/'.$class->getId());
        $this->client->submitForm('Retirer');
        self::assertResponseRedirects();
        self::assertCount(0, $this->reload($class)->getPupils());

        $id = $class->getId();
        $this->client->request('GET', '/mon-compte/classes/'.$id);
        $this->client->submitForm('Supprimer la classe');
        self::assertResponseRedirects('/mon-compte/classes');
        self::assertNull($this->entityManager->find(SchoolClass::class, $id));
        self::assertNotNull($this->entityManager->find(User::class, $pupil->getId()), 'The account of the pupil stays');
    }

    public function testATeacherWithoutTheRightNoLongerFollowsTheClass(): void
    {
        $class = $this->schoolClass($this->userIn($this->entityManager, 'ancien-prof@example.org'));
        $this->client->loginUser($class->getTeacher());
        $this->client->request('GET', '/mon-compte/classes/'.$class->getId());

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * The entity as stored: the entity manager is reset between requests.
     *
     * @template T of object
     *
     * @param T $entity
     *
     * @return T
     */
    private function reload(object $entity): object
    {
        $this->entityManager->clear();
        $stored = $this->entityManager->find($entity::class, $entity->getId());
        self::assertNotNull($stored);

        return $stored;
    }

    private function teacher(): User
    {
        $group = (new Group('teacher', 'Enseignant'))->grant(Permission::ClassTeach);
        $this->entityManager->persist($group);
        $teacher = $this->userIn($this->entityManager, 'prof@example.org')->addGroup($group);
        $this->entityManager->flush();

        return $teacher;
    }

    private function schoolClass(User $teacher): SchoolClass
    {
        $class = new SchoolClass($teacher, 'BIA 2026-2027', new \DateTimeImmutable());
        $this->entityManager->persist($class);
        $this->entityManager->flush();

        return $class;
    }
}
