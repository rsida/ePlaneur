<?php

declare(strict_types=1);

namespace App\Security;

/**
 * E-mails an account may choose to receive ("Mon profil" > "Préférences e-mail"). Security and
 * confirmation e-mails are always sent. Some are also notifications on the devices that turned them
 * on (PUSH, doc/installable-site.md#notifications), chosen apart from the e-mails.
 */
enum UserNotification: string
{
    case FlightComment = 'flight_comment';
    case Message = 'message';
    case Renewal = 'renewal';
    case News = 'news';
    case Sessions = 'sessions';

    /** What may be a notification on a device, in this order in the profile. */
    public const array PUSH = [self::Sessions, self::Message, self::FlightComment];

    public function label(): string
    {
        return match ($this) {
            self::FlightComment => 'Un commentaire sur l’un de mes vols',
            self::Message => 'Un nouveau message',
            self::Renewal => 'Un rappel de renouvellement de mon adhésion',
            self::News => 'La lettre mensuelle du club : vols, records, prochaines sessions et actualités',
            self::Sessions => 'Les vols en réseau : un rappel la veille, puis les résultats',
        };
    }

    /** Label in the panel "Notifications sur cet appareil". */
    public function pushLabel(): string
    {
        return match ($this) {
            self::Sessions => 'Les vols en réseau : un rappel la veille, puis les résultats',
            self::Message => 'Un nouveau message',
            self::FlightComment => 'Un commentaire sur l’un de mes vols',
            default => $this->label(),
        };
    }
}
