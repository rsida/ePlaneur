<?php

declare(strict_types=1);

namespace App\Security;

/**
 * E-mails an account may choose to receive ("Mon profil" > "Préférences e-mail"). Security and
 * confirmation e-mails are always sent.
 */
enum UserNotification: string
{
    case FlightComment = 'flight_comment';
    case Message = 'message';
    case Renewal = 'renewal';
    case News = 'news';

    public function label(): string
    {
        return match ($this) {
            self::FlightComment => 'Un commentaire sur l’un de mes vols',
            self::Message => 'Un nouveau message',
            self::Renewal => 'Un rappel de renouvellement de mon adhésion',
            self::News => 'Les actualités du club',
        };
    }
}
