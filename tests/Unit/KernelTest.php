<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Kernel;
use PHPUnit\Framework\TestCase;

/**
 * Placeholder unit test: replace it with tests of your own services.
 */
final class KernelTest extends TestCase
{
    public function testKernelUsesTheGivenEnvironment(): void
    {
        $kernel = new Kernel('test', false);

        self::assertSame('test', $kernel->getEnvironment());
        self::assertFalse($kernel->isDebug());
    }
}
