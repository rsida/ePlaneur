<?php

declare(strict_types=1);

namespace App\Push;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * The subscription a browser gives (PushSubscription.toJSON()): its address and its keys.
 */
final readonly class PushSubscriptionInput
{
    /**
     * @param array<string, mixed> $keys
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Url(protocols: ['https'], requireTld: false)]
        #[Assert\Length(max: 700)]
        public string $endpoint = '',
        #[Assert\Collection(fields: [
            'p256dh' => [new Assert\NotBlank(), new Assert\Type('string'), new Assert\Length(max: 255)],
            'auth' => [new Assert\NotBlank(), new Assert\Type('string'), new Assert\Length(max: 255)],
        ], allowExtraFields: true)]
        public array $keys = [],
    ) {
    }
}
