<?php

declare(strict_types=1);

namespace App\Push;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * The address of one device, to turn its notifications off or to try them.
 */
final readonly class PushEndpointInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 700)]
        public string $endpoint = '',
    ) {
    }
}
