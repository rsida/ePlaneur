<?php

declare(strict_types=1);

use App\Tests\Behat\WebContext;
use Behat\Config\Config;
use Behat\Config\Extension;
use Behat\Config\Profile;
use Behat\Config\Suite;
use FriendsOfBehat\SymfonyExtension\ServiceContainer\SymfonyExtension;

return (new Config())
    ->withProfile(
        (new Profile('default'))
            ->withExtension(new Extension(SymfonyExtension::class, [
                'bootstrap' => 'tests/bootstrap.php',
                'kernel' => ['environment' => 'test'],
            ]))
            ->withSuite(
                (new Suite('default'))
                    ->withPaths('%paths.base%/features')
                    ->withContexts(WebContext::class)
            )
    );
