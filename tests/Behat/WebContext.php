<?php

declare(strict_types=1);

namespace App\Tests\Behat;

use Behat\Behat\Context\Context;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Generic HTTP steps, run against the Symfony kernel (no web server, no browser).
 */
final class WebContext implements Context
{
    public function __construct(
        #[Autowire(service: 'test.client')]
        private readonly KernelBrowser $client,
    ) {
    }

    #[Given('I am on :path')]
    #[When('I go to :path')]
    public function iAmOn(string $path): void
    {
        $this->client->request('GET', $path);
    }

    #[Then('the response status code should be :code')]
    public function theResponseStatusCodeShouldBe(int $code): void
    {
        $actual = $this->client->getResponse()->getStatusCode();

        if ($actual !== $code) {
            throw new \RuntimeException(\sprintf('Expected status code %d, got %d.', $code, $actual));
        }
    }

    #[Then('I should see :text')]
    public function iShouldSee(string $text): void
    {
        if (!str_contains($this->client->getCrawler()->text(), $text)) {
            throw new \RuntimeException(\sprintf('"%s" was not found in the page.', $text));
        }
    }
}
