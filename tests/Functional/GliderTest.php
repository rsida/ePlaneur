<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Flight;
use App\Entity\User;
use App\Tests\Unit\Flight\Glider\GliderSamples;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * The gliders of Condor: imported from a Planes folder, their pages, and the polar of a flight.
 */
final class GliderTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
    }

    public function testTheGlidersOfAPlanesFolderAreImportedAndShown(): void
    {
        // Under the name of the glider of the sample track
        $this->import('Diana2');
        // Run again after a Condor update: updated, not duplicated
        self::assertStringContainsString('1 gliders read: 0 new, 1 updated.', $this->import('Diana2'));

        $this->client->request('GET', '/planeurs');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.p-glider__list tbody', 'Acme Tester Glider-15');

        $this->client->clickLink('Acme Tester Glider-15');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Acme Tester Glider-15');
        self::assertSelectorTextContains('.p-glider__sheet', '30,0 à 48,0 kg/m²');
        self::assertSelectorTextContains('.p-glider__speeds tbody', '2,0 m/s');
        $curves = json_decode((string) $this->client->getCrawler()->filter('[data-controller="polar-chart"]')->attr('data-polar-chart-curves-value'), true);
        self::assertIsArray($curves);
        self::assertCount(2, $curves, 'Without water and full');

        $this->client->request('GET', '/planeurs/Inconnu');
        self::assertResponseStatusCodeSame(404);
    }

    public function testTheFlightPageShowsThePolarOfItsGlider(): void
    {
        $this->import('Diana2');
        $pilot = (new User())->setEmail('pilote@example.org')->setDisplayName('Jeanne Pilote')->setVerified(true)->setPassword('x');
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($pilot);
        $entityManager->flush();
        $this->client->loginUser($pilot);
        $crawler = $this->client->request('GET', '/vols');
        $copy = sys_get_temp_dir().'/'.uniqid('upload', true);
        copy(\dirname(__DIR__).'/fixtures/flights/aa3-laragne-start.ftr', $copy);
        $this->client->request('POST', '/vols', ['_token' => $crawler->filter('input[name="_token"]')->attr('value')], ['trace' => new UploadedFile($copy, 'C3-c060-1.ftr', 'application/octet-stream', null, true)]);
        $flight = $entityManager->getRepository(Flight::class)->findOneBy([]);
        self::assertSame('Diana2', $flight?->getGlider());

        $this->client->request('GET', '/vols/'.$flight->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.p-flight__glider', 'Acme Tester Glider-15');
        self::assertSelectorTextContains('.p-flight__glider', 'Charge alaire');
        self::assertSelectorExists('.p-flight__glider [data-controller="polar-chart"]');

        // The viewer gets the netto vario of the glides, one value (or null) per second
        $this->client->request('GET', '/vols/'.$flight->getId().'/analyse.json');
        $analysis = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertCount(\count($analysis['series']['vario']), $analysis['series']['netto']);
    }

    /** Imports a folder holding the sample glider under a name, and returns what the command says. */
    private function import(string $name): string
    {
        $directory = sys_get_temp_dir().'/planes-'.bin2hex(random_bytes(4));
        mkdir($directory.'/'.$name, recursive: true);
        file_put_contents(\sprintf('%s/%s/%s.txt', $directory, $name, $name), GliderSamples::SHEET);
        file_put_contents(\sprintf('%s/%s/%s.pol', $directory, $name, $name), GliderSamples::pol());
        $command = new CommandTester((new Application(self::$kernel ?? throw new \LogicException()))->find('app:gliders:import'));
        $command->execute(['directory' => $directory]);
        $command->assertCommandIsSuccessful();

        return $command->getDisplay();
    }
}
