<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Group;
use App\Entity\StepProgress;
use App\Entity\User;
use App\Security\Permission;
use App\Training\ProgressStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Follow-up of the training path: a trainer marks the steps of a pilot from their logbook, the
 * pilot reads them; nobody validates their own steps.
 */
final class TrainingTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testATrainerValidatesAStepThePilotReadsIt(): void
    {
        $pilot = $this->user('jeanne@example.org', 'Jeanne Pilote');
        $trainer = $this->user('alain@example.org', 'Alain Formateur', $this->trainers());
        $this->client->loginUser($trainer);

        $crawler = $this->client->request('GET', '/vols/carnet/'.$pilot->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#parcours', '0 étape validée sur 8');
        $this->client->submit($crawler->filter('form[name="step_1"]')->form([
            'step_1[status]' => 'validated',
            'step_1[note]' => 'Exercices de base maîtrisés.',
            'step_1[validatedOn]' => '2026-10-01',
        ]));
        self::assertResponseRedirects('/vols/carnet/'.$pilot->getId().'#parcours');
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.c-alert', 'Étape 1 · Découvrir Condor et le pilotage : enregistrée.');
        self::assertSelectorTextContains('#parcours', 'qualification du club : Élève ePilote');
        self::assertSelectorTextContains('#parcours', 'Validée le 1 octobre 2026');

        // In progress, then back to not started
        $this->client->submit($crawler->filter('form[name="step_2"]')->form(['step_2[status]' => 'in_progress']));
        $this->entityManager->clear();
        self::assertSame(ProgressStatus::InProgress, $this->progress($pilot, 2)?->getStatus());
        $crawler = $this->client->request('GET', '/vols/carnet/'.$pilot->getId());
        $this->client->submit($crawler->filter('form[name="step_2"]')->form(['step_2[status]' => '']));
        $this->entityManager->clear();
        self::assertNull($this->progress($pilot, 2));

        // The pilot reads it in their logbook and on their dashboard, without the forms
        $this->client->loginUser($pilot);
        $this->client->request('GET', '/mon-compte/carnet');
        self::assertSelectorTextContains('#parcours', 'Exercices de base maîtrisés.');
        self::assertSelectorTextContains('#parcours', 'par Alain Formateur');
        self::assertSelectorNotExists('form[name="step_1"]');
        $this->client->request('GET', '/mon-compte');
        self::assertSelectorTextContains('.p-account__training', 'Élève ePilote');
        self::assertSelectorTextContains('.p-account__training', 'en cours : Étape 2');
    }

    public function testNobodyValidatesTheirOwnSteps(): void
    {
        $trainer = $this->user('alain@example.org', 'Alain Formateur', $this->trainers());
        $pilot = $this->user('jeanne@example.org', 'Jeanne Pilote');

        $this->client->loginUser($pilot);
        $this->client->request('POST', '/vols/carnet/'.$pilot->getId().'/parcours/1', ['step_1' => ['status' => 'validated']]);
        self::assertResponseStatusCodeSame(403);

        $this->client->loginUser($trainer);
        $this->client->request('POST', '/vols/carnet/'.$trainer->getId().'/parcours/1', ['step_1' => ['status' => 'validated']]);
        self::assertResponseStatusCodeSame(403);
    }

    private function progress(User $pilot, int $step): ?StepProgress
    {
        return $this->entityManager->getRepository(StepProgress::class)->findOneBy(['pilot' => $pilot->getId(), 'step' => $step]);
    }

    private function trainers(): Group
    {
        $group = (new Group('formateur', 'Formateur'))->setPermissions([Permission::FlightReview]);
        $this->entityManager->persist($group);

        return $group;
    }

    private function user(string $email, string $name, ?Group $group = null): User
    {
        $user = (new User())->setEmail($email)->setDisplayName($name)->setVerified(true)->setPassword('x');
        if (null !== $group) {
            $user->addGroup($group);
        }
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
