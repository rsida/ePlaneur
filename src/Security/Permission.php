<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Catalogue of the actions that can be granted to a group.
 *
 * The list is fixed in code because code checks it (`is_granted('USER_MANAGE')`); which group holds
 * which permission is data, edited by an administrator. Add a case here when a feature needs a new
 * right, then grant it to groups.
 */
enum Permission: string implements TranslatableInterface
{
    case AdminAccess = 'ADMIN_ACCESS';
    case UserManage = 'USER_MANAGE';
    case GroupManage = 'GROUP_MANAGE';
    case PostCreate = 'POST_CREATE';
    case PostEdit = 'POST_EDIT';
    case PostPublish = 'POST_PUBLISH';
    case PostDelete = 'POST_DELETE';
    case CategoryManage = 'CATEGORY_MANAGE';
    case MediaManage = 'MEDIA_MANAGE';
    case PageManage = 'PAGE_MANAGE';
    case MenuManage = 'MENU_MANAGE';
    case DocumentManage = 'DOCUMENT_MANAGE';
    case FlightReview = 'FLIGHT_REVIEW';
    case MembershipManage = 'MEMBERSHIP_MANAGE';
    case ContactManage = 'CONTACT_MANAGE';
    case SessionOrganise = 'SESSION_ORGANISE';
    case SessionManage = 'SESSION_MANAGE';
    case FlightPlanPrepare = 'FLIGHT_PLAN_PREPARE';
    case TrainingManage = 'TRAINING_MANAGE';
    case MessageSend = 'MESSAGE_SEND';
    case ActivityView = 'ACTIVITY_VIEW';

    public function label(): string
    {
        return match ($this) {
            self::AdminAccess => 'Accéder à l’administration',
            self::UserManage => 'Gérer les comptes',
            self::GroupManage => 'Gérer les groupes et leurs droits',
            self::PostCreate => 'Écrire des articles',
            self::PostEdit => 'Modifier tous les articles et voir les brouillons',
            self::PostPublish => 'Publier ou dépublier des articles',
            self::PostDelete => 'Supprimer des articles',
            self::CategoryManage => 'Gérer les catégories d’articles',
            self::MediaManage => 'Gérer la médiathèque (images, documents)',
            self::PageManage => 'Gérer les pages et voir les pages non publiées',
            self::MenuManage => 'Gérer les menus de navigation',
            self::DocumentManage => 'Gérer les documents officiels',
            self::FlightReview => 'Commenter les vols des pilotes et suivre leur carnet de vol (formateurs)',
            self::MembershipManage => 'Gérer les saisons et valider les adhésions',
            self::ContactManage => 'Lire et traiter les messages du formulaire de contact',
            self::SessionOrganise => 'Proposer des sessions de vol en réseau et modifier les siennes',
            self::SessionManage => 'Gérer toutes les sessions de vol en réseau et les plans de vol',
            self::FlightPlanPrepare => 'Préparer des plans de vol avec l’atelier (consigne pour Claude)',
            self::TrainingManage => 'Configurer « Mon parcours » : étapes, exercices et quiz',
            self::MessageSend => 'Écrire aux membres, au comité et aux administrateurs (messagerie)',
            self::ActivityView => 'Voir l’activité du club mois par mois et l’exporter (assemblée générale)',
        };
    }

    /** French label, shown as is by forms and the back-office (EasyAdmin renders translatable enums). */
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $this->label();
    }
}
