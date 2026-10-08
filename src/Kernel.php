<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /** Dates are stored and shown in the club's time (parameter app.timezone). */
    public function boot(): void
    {
        parent::boot();
        date_default_timezone_set((string) $this->getContainer()->getParameter('app.timezone'));
    }

    /**
     * @return list<string> An array of allowed values for APP_ENV
     */
    private function getAllowedEnvs(): array
    {
        return ['prod', 'dev', 'test'];
    }
}
