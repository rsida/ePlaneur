<?php

declare(strict_types=1);

namespace App\Training;

/**
 * Steps of "Mon premier vol" (roadmap 11a): what a newcomer does before and around their first
 * network flight, gathered from the pages of the site. Most are seen by the site (account, versions
 * of Condor in the profile, flights, step 1 of "Mon parcours"); the pilot says the others are done.
 */
enum FirstFlightStep: string
{
    case Account = 'account';
    case Condor = 'condor';
    case Discord = 'discord';
    case SoloFlight = 'solo_flight';
    case NetworkFlight = 'network_flight';
    case FirstStep = 'first_step';

    public function label(): string
    {
        return match ($this) {
            self::Account => 'Créer mon compte gratuit',
            self::Condor => 'Installer Condor 2',
            self::Discord => 'Rejoindre le Discord du club',
            self::SoloFlight => 'Voler seul et déposer ma trace',
            self::NetworkFlight => 'Participer à un vol d’initiation',
            self::FirstStep => 'Terminer l’étape 1 de Mon parcours',
        };
    }

    public function text(): string
    {
        return match ($this) {
            self::Account => 'Il donne accès au carnet de vol, à l’analyse de vos traces, à Mon parcours et aux vols en réseau. Gratuit, sans adhésion.',
            self::Condor => 'Le simulateur, la scène AA2 et un joystick suffisent pour commencer ; le club peut prêter des licences de Condor 2. Indiquez ensuite dans votre profil la version que vous avez : la case se coche toute seule.',
            self::Discord => 'Le briefing et les échanges pendant les vols en réseau se font à la voix sur le serveur Discord « ePlaneur ». La page « Participer » explique comment le rejoindre.',
            self::SoloFlight => 'Un premier vol seul, avec les exercices de l’étape 1, puis déposez la trace (.ftr ou .igc) : le site l’analyse et vous conseille.',
            self::NetworkFlight => 'Le mercredi à 16h30 (heure de Paris), un vol facile avec un formateur pour découvrir le vol à plusieurs. Déposez ensuite votre trace : elle rejoint la session.',
            self::FirstStep => 'Lectures, quiz et exercices en vol : la suite se passe dans Mon parcours.',
        };
    }

    /** Steps the site cannot see: the pilot says they are done. */
    public function isDeclared(): bool
    {
        return self::Discord === $this;
    }
}
