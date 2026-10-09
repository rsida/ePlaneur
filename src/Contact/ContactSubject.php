<?php

declare(strict_types=1);

namespace App\Contact;

/**
 * What a message to the club is about (contact form).
 */
enum ContactSubject: string
{
    case Membership = 'membership';
    case Training = 'training';
    case NetworkFlights = 'network_flights';
    case Website = 'website';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Membership => 'Adhésion',
            self::Training => 'Formation',
            self::NetworkFlights => 'Vols en réseau',
            self::Website => 'Site',
            self::Other => 'Autre',
        };
    }
}
