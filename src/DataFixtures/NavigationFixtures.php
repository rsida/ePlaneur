<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Content\Block as B;
use App\Entity\Document;
use App\Entity\DocumentCategory;
use App\Entity\Group;
use App\Entity\MenuItem;
use App\Entity\Page;
use App\Media\MediaStorage;
use App\Navigation\MenuLocation;
use App\Security\DefaultGroup;
use App\Security\Visibility;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Development pages, official documents and menus, modelled on the "Le Club" section of the
 * current site (doc/project/current-site.md) and the Figma mock-ups of step 2b. Runs after PostFixtures, which empties the uploads.
 */
final class NavigationFixtures extends Fixture implements DependentFixtureInterface
{
    private ObjectManager $manager;

    public function __construct(
        private readonly MediaStorage $storage,
        private readonly B\BlockFactory $blocks,
        #[Autowire('%kernel.project_dir%/fixtures/media')]
        private readonly string $mediaDirectory,
    ) {
    }

    public function getDependencies(): array
    {
        return [AppFixtures::class, PostFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $this->manager = $manager;
        $committee = $this->getReference('group-'.DefaultGroup::Committee->value, Group::class);
        $members = $this->getReference('group-'.DefaultGroup::Member->value, Group::class);

        // ---------- Official documents ----------
        $founding = $this->category('Textes fondateurs', 'textes-fondateurs', 0);
        $decisions = $this->category('Décisions', 'decisions', 1);
        $communication = $this->category('Communication', 'communication', 2);

        $statutes = $this->document('Statuts officiels du Club ePlaneur', 'statuts-club-eplaneur.pdf', $founding, 'Cadre juridique et fonctionnement de l’association', 'V23', ['Adoptés le 15/12/2025']);
        $this->document('Règlement intérieur', 'reglement-interieur-v13.pdf', $founding, 'Règles de fonctionnement et modalités de participation', 'V13', ['Document daté du 10/02/2026', 'Validation constitutive : 15/12/2025', 'Adoption de la V13 : non précisée']);
        $this->document('Publication au JOAFE', 'statuts-club-eplaneur.pdf', $founding, 'Annonce n° 3024 · reconnaissance de l’existence juridique', null, ['Publication : 03/02/2026']);
        $this->document('Récépissé de déclaration', 'decision-cotisations-2026.pdf', $founding, 'Déclaration de l’association en préfecture', null, ['Déclaration : 26/01/2026', 'Émis le 28/01/2026']);
        $this->document('Cotisations 2026', 'decision-cotisations-2026.pdf', $decisions, 'Cotisations adoptées par le Comité fondateur', null, ['Adoptées le 01/10/2025']);
        $this->document('Objectifs du Club ePlaneur', 'lettre-aux-enseignants.pdf', $communication, 'Présentation des objectifs et du projet associatif', 'V11');
        $this->document('Lettre aux enseignants', 'lettre-aux-enseignants.pdf', $communication, 'Présenter l’ePlaneur aux professeurs préparant le BIA');
        $minutes = $this->document('Compte rendu du Comité Directeur – septembre 2026', 'compte-rendu-cd-2026-09.pdf', $decisions, 'Réunion du 22 septembre 2026', null, ['Approuvé le 29/09/2026']);
        $minutes->setVisibility(Visibility::Groups)->addAllowedGroup($committee);
        // Blocks reference documents by id
        $manager->flush();

        // ---------- Pages: the "Le Club" section (Figma "Le Club — rubrique") ----------
        $club = $this->page('Le Club', 'le-club', null, 'Le Club ePlaneur est une association dont le fonctionnement repose sur une organisation claire, des règles partagées et l’engagement de ses membres.', [
            new B\TextBlock('<p>Il s’inscrit dans une démarche collective visant à structurer et accompagner la pratique du ePlaneur dans un cadre associatif reconnu.</p>'),
            new B\TextBlock('<p>Cette rubrique rassemble l’ensemble des informations relatives à la vie du club. Elle permet de comprendre comment l’association est organisée, sur quelles bases elle fonctionne et quelles sont les modalités de participation à ses activités.</p>', true),
            new B\ChildPagesBlock('Comprendre / Participer / Échanger'),
            new B\CalloutBlock('Un compte gratuit, une adhésion distincte', '<p>La création d’un compte gratuit est distincte de l’adhésion à l’association. Les cotisations actuelles sont de 10 € pour les moins de 25 ans et de 25 € pour les adultes. Un soutien facultatif n’accorde pas d’avantage supplémentaire.</p>', 'tip', false),
            new B\HeadingBlock('Un club, même à distance.', 'lg'),
            new B\TextBlock('<p>Contrairement aux clubs de planeur traditionnels, le Club ePlaneur ne repose sur aucun lieu physique.</p>'),
            new B\TextBlock('<p>Son activité est entièrement dématérialisée : formation, échanges, organisation des vols en réseau et vie associative se déroulent à distance, dans un cadre structuré et partagé par ses membres.</p>', true),
            new B\LinksBlock([new B\LinkItem('Voir la vidéo de présentation', '/le-club/communication#video')], 'arrows'),
        ], 'Le Club / Vie associative');
        $club->setHighlight("Chacun son rythme.\nLa même envie de ciel.")->setHighlightNote("Le Club ePlaneur\nAssociation loi 1901");

        $presentation = $this->section($club, 'Présentation', 'presentation', 'Un club sans lieu physique : formation, échanges, vols en réseau et vie associative se déroulent à distance.', 'Découvrir le club');
        $this->section($club, 'Objectifs', 'objectifs', 'Pratiquer, promouvoir et développer le ePlaneur. Découvrez le projet associatif et le projet de brevet ePlaneur.', 'Comprendre nos objectifs');
        $texts = $this->section($club, 'Textes officiels', 'textes-officiels', 'Statuts, règlement intérieur et documents administratifs : les textes qui fondent le fonctionnement du club.', 'Consulter les textes');
        $this->section($club, 'Communication', 'communication', 'Communication interne, vidéo de présentation et lettre aux enseignants : les supports pour échanger et faire connaître le club.', 'Voir les supports');
        $membership = $this->section($club, 'Adhésions', 'adhesions', 'Cotisations, première adhésion et inscription : les démarches pour participer à la vie de l’association.', 'Préparer mon adhésion');
        $lists = $this->section($club, 'Listes des membres', 'listes-des-membres', 'Le Comité Directeur et le Bureau sont présentés publiquement. La liste des membres inscrits nécessite une connexion.', 'Voir les listes');
        $activities = $this->section($club, 'Activités', 'activites', 'Réunions, suivi des actions et ateliers avec des établissements d’enseignement : la vie collective du club.', 'Découvrir les activités');
        $this->section($club, 'Contact', 'contact', 'Une question sur l’association ? Écrivez au Club par courriel, ou retrouvez le groupe Facebook ePlaneur.', 'Écrire au Club');

        $reference = [new B\CalloutBlock('Les textes de référence', '<p>Les versions signées font foi. Consultez les documents officiels du club.</p>', 'tip', false)];
        $this->page('Statuts', 'statuts', $texts, 'Les statuts constituent le document fondateur du Club ePlaneur.', [
            new B\SectionBlock('Nature et rôle des statuts', 'Statuts du club', null, 'Nature et rôle'),
            new B\TextBlock('<p>Ils définissent le cadre juridique de l’association, son objet, ses règles de fonctionnement, la composition de ses instances et les modalités de prise de décision.</p><p>Ils engagent l’ensemble des membres et assurent la conformité de l’association aux dispositions de la loi du 1er juillet 1901 relative au contrat d’association.</p>'),
            new B\CalloutBlock('Le document officiel fait foi', '<p>Cette présentation n’a pas de valeur juridique autonome : seule la version officielle figurant dans les statuts signés fait foi.</p>', 'info', false),
            new B\SectionBlock('Adoption et dépôt officiel', 'Statuts du club', 'Les étapes ci-dessous sont attestées par les documents publics. La date d’approbation des statuts ne doit pas être confondue avec la date de mise à jour éditoriale de cette page.', 'Adoption'),
            new B\TableBlock(['Date', 'Étape attestée'], [['15 décembre 2025', 'Approbation par l’Assemblée générale constitutive'], ['26 janvier 2026', 'Déclaration en préfecture'], ['3 février 2026', 'Publication au JOAFE']], 'Association déclarée · RNA W9R1011072. Affiliation fédérale et conventions : en cours.'),
            new B\SectionBlock('Consultation et téléchargement du document officiel', 'Statuts du club', null, 'Consultation'),
            new B\TextBlock('<p>La version officielle des statuts signés est consultable au format PDF. La version en vigueur porte la référence V23 et comprend 10 pages.</p>'),
            new B\DocumentBlock((int) $statutes->getId(), 'Consulter les statuts signés'),
            new B\SectionBlock('Formulation administrative de l’objet', 'Statuts du club', null, 'Objet'),
            new B\QuoteBlock('« L’Association a pour objet : pratiquer et promouvoir le ePlaneur à l’aide de simulateurs dans le cadre des activités eSportives reconnues par la Fédération Française de Vol en Planeur, préparer les eSportifs à la participation à des compétitions régionales, nationales ou internationales, promouvoir et développer le ePlaneur en direction des jeunes préparant le brevet d’initiation aéronautique et de leurs formateurs à La Réunion […] »', 'Extrait du document officiel signé, adopté le 15 décembre 2025.', 'Statuts V23 / Article 2'),
            new B\SectionBlock('Présentation simplifiée de l’objet de l’association', 'Statuts du club', null, 'Objet simplifié'),
            new B\TextBlock('<p>Le Club a pour objet de pratiquer et promouvoir le ePlaneur, de former et perfectionner ses pratiquants, d’accompagner la participation aux compétitions et de développer l’esprit aéronautique, particulièrement auprès des jeunes. Cette synthèse renvoie à l’article 2 ; elle ne remplace pas le texte signé.</p>'),
            new B\CalloutBlock('Des règles partagées', '<p>Les extraits suivants précisent l’engagement des membres, la composition du Comité directeur et le fonctionnement à distance. Ils sont cités depuis les statuts V23.</p>', 'tip', false),
            new B\ExcerptBlock('L’engagement des membres', '« Tous les membres de l’Association sont tenus de prendre connaissance des présents statuts et, le cas échéant, du règlement intérieur, et de s’engager par écrit à les respecter. »', 'Statuts V23 · Article 9'),
            new B\ExcerptBlock('Le Comité directeur', '« L’Association est gérée par un Comité directeur, composé de 5 membres au moins. Ce nombre pourra être augmenté par décision de l’Assemblée générale, sans pouvoir excéder 12 membres. »', 'Statuts V23 · Article 15'),
            new B\ExcerptBlock('La participation à distance', '« Les réunions du Comité directeur et les Assemblées générales peuvent être tenues par visioconférence ou tout autre moyen de communication à distance permettant la participation effective de leurs membres. »', 'Statuts V23 · Article 16'),
            new B\LinksBlock([new B\LinkItem('Lire le règlement intérieur', '/le-club/textes-officiels/reglement-interieur'), new B\LinkItem('Voir les autres documents', '/le-club/textes-officiels/autres-documents')], 'arrows', 'Source : site officiel du Club ePlaneur, page « Statuts » et PDF V23.'),
        ], 'Le Club / Textes officiels', $reference);
        $this->page('Règlement intérieur', 'reglement-interieur', $texts, 'Version 13, rédigée avec le service juridique de la FFVP.', [
            new B\TextBlock('<p>Le règlement intérieur précise les statuts : adhésion, cotisations, vols en réseau, comportement.</p>'),
            new B\DocumentsBlock($founding->getId()),
        ], 'Le Club / Textes officiels', $reference);
        $this->page('Autres documents', 'autres-documents', $texts, 'Cette page regroupe les principaux documents administratifs et institutionnels attestant de l’existence et du fonctionnement du Club ePlaneur.', [
            new B\DocumentsBlock(null, 'Documents officiels', 'list', 'Les documents publics pour comprendre les fondements et le fonctionnement de l’association. Les dates ci-dessous distinguent adoption, déclaration, émission et publication.'),
            new B\CalloutBlock('Cotisations actuelles', '<p>10 € pour les moins de 25 ans, 25 € pour les adultes. Le soutien est facultatif et sans avantage supplémentaire. Un compte gratuit reste distinct de l’adhésion.</p>', 'tip', false),
            new B\LinksBlock([new B\LinkItem('Retour aux textes officiels', '/le-club/textes-officiels')], 'arrows', 'RNA W9R1011072 · Affiliation fédérale et conventions : en cours.'),
        ], 'Le Club / Textes officiels', $reference);

        $this->page('Cotisations', 'cotisations', $membership, 'Les tarifs 2026.', [
            new B\TableBlock(['Type', 'Tarif'], [['Moins de 25 ans', '10 €'], ['Adulte', '25 €'], ['Soutien', 'Montant libre']], 'Décision du 1er octobre 2025.'),
        ], 'Le Club / Adhésions');
        $this->page('Première adhésion', 'premiere-adhesion', $membership, 'Créer un compte, adhérer, être validé.', [
            new B\StepsBlock([
                new B\StepItem('Créer un compte gratuit sur ce site', 'Il donne accès aux contenus réservés aux inscrits.'),
                new B\StepItem('Adhérer sur Yapla', 'Formulaire, paiement par carte, facture.'),
                new B\StepItem('Validation par le club', 'Le comité valide l’adhésion et vous ajoute au groupe « Membre ».'),
            ]),
        ], 'Le Club / Adhésions');

        $this->page('Comité Directeur', 'comite-directeur', $lists, 'Les membres élus du Comité Directeur.', [
            new B\TextBlock('<p>Le Comité Directeur compte au moins 5 membres élus par l’Assemblée générale.</p>'),
        ], 'Le Club / Listes des membres');
        $this->page('Bureau', 'bureau', $lists, 'Président, trésorier et secrétaire.', [
            new B\TextBlock('<p>Le Bureau est élu par le Comité Directeur parmi ses membres.</p>'),
        ], 'Le Club / Listes des membres');
        $registered = $this->page('Membres inscrits', 'membres-inscrits', $lists, 'La liste des membres inscrits du Club ePlaneur.', [
            new B\TextBlock('<p>Liste des adhérents à jour de leur cotisation.</p>'),
        ], 'Le Club / Listes des membres');
        $registered->setVisibility(Visibility::Groups)->addAllowedGroup($members)->addAllowedGroup($committee);

        $reports = $this->page('Comptes rendus du Comité', 'comptes-rendus', $activities, 'Réunions et suivi de la vie associative du Club ePlaneur.', [
            new B\DocumentsBlock($decisions->getId()),
        ], 'Le Club / Activités');
        $reports->setVisibility(Visibility::Groups)->addAllowedGroup($committee)->setAnnounced(false);
        $tools = $this->page('Outils des membres', 'outils-membres', $activities, 'Ressources réservées aux adhérents.', [
            new B\LinksBlock([new B\LinkItem('Rechercher un FPL', 'https://www.eplaneur.fr', 'Base de plans de vol sur eplaneur.fr.')]),
        ], 'Le Club / Activités');
        $tools->setVisibility(Visibility::Groups)->addAllowedGroup($members)->addAllowedGroup($committee);

        $this->page('Mentions légales', 'mentions-legales', null, 'Éditeur, hébergement, propriété intellectuelle et données personnelles.', [
            new B\TextBlock('<p>Éditeur : Club ePlaneur, association loi 1901, Saint-Denis (La Réunion). Directeur de la publication : le Président.</p>'),
        ]);
        $this->page('Confidentialité', 'confidentialite', null, 'Les données collectées et vos droits.', [
            new B\TextBlock('<p>Données collectées : compte du site, formulaire de contact. Vos droits s’exercent par e-mail auprès du club.</p>'),
        ]);

        // ---------- Main menu: "Le Club" mega-menu (Figma "Menu Le Club ouvert") ----------
        $position = 0;
        $this->link(MenuLocation::Main, 'Accueil', '/', null, $position++);
        $this->link(MenuLocation::Main, 'Formation', '/#formation', null, $position++);
        $this->link(MenuLocation::Main, 'Voler', '/#vols', null, $position++);
        $clubLink = $this->link(MenuLocation::Main, 'Le Club', null, null, $position++, $club, 'Le Club / Vie associative');
        $this->link(MenuLocation::Main, 'Actualités', '/actualites', null, $position++);

        $mega = [
            'presentation' => [['Découvrir le club', '/le-club/presentation'], ['Vidéo de présentation', '/le-club/communication#video', 'Communication']],
            'objectifs' => [['Brevet ePlaneur', '/le-club/objectifs#brevet', 'Un projet du club'], ['Objectifs du Club', '/le-club/textes-officiels/autres-documents', 'Document PDF']],
            'textes-officiels' => null,
            'communication' => [['Communication interne', '/le-club/communication#interne'], ['Vidéo de présentation', '/le-club/communication#video'], ['Lettre aux enseignants', '/le-club/communication#enseignants']],
            'adhesions' => null,
            'listes-des-membres' => null,
            'activites' => [['Réunions', '/le-club/activites#reunions', 'Section de la page'], ['Suivi des actions', '/le-club/activites#actions', 'Section de la page'], ['Ateliers avec des établissements d’enseignement', '/le-club/activites#ateliers', 'Section de la page']],
            'contact' => [['Écrire au Club', 'mailto:contact@club.eplaneur.fr', 'Formulaire et courriel'], ['Groupe Facebook ePlaneur', 'https://www.facebook.com/groups/eplaneur', 'Lien externe']],
        ];
        $index = 0;
        foreach ($club->getChildren() as $section) {
            $sectionLink = $this->link(MenuLocation::Main, $section->getTitle(), null, $clubLink, $index++, $section);
            $links = $mega[$section->getSlug()] ?? null;
            if (null === $links) {
                // Sub-pages of the section
                foreach ($section->getChildren() as $childIndex => $child) {
                    $this->link(MenuLocation::Main, $child->getTitle(), null, $sectionLink, $childIndex, $child);
                }
                continue;
            }
            foreach ($links as $linkIndex => $link) {
                $this->link(MenuLocation::Main, $link[0], $link[1], $sectionLink, $linkIndex, null, $link[2] ?? null);
            }
        }
        // Activities: the reserved pages follow the page sections
        foreach ($activities->getChildren() as $childIndex => $child) {
            $this->link(MenuLocation::Main, $child->getTitle(), null, $this->menuItemFor($activities), 10 + $childIndex, $child);
        }

        $columns = [
            'Formation' => ['Installer Condor 2' => '/#guides', 'Installer Condor 3' => '/#guides', 'Parcours guidé' => '/#formation', 'Vidéos pédagogiques' => '/#formation', 'Bonnes pratiques' => '/#guides'],
            'Voler' => ['Vols en réseau' => '/#vols', 'Participer à un vol' => '/#vols', 'Prérequis pour voler' => '/#vols', 'Déposer une trace' => '/#carnet', 'Créer mon carnet' => '/#carnet'],
            'Le Club' => ['Présentation du club' => $presentation, 'Textes officiels' => $texts, 'Actualités' => '/actualites', 'Adhésions' => $membership, 'Contacter le club' => 'mailto:contact@club.eplaneur.fr'],
        ];
        $column = 0;
        foreach ($columns as $title => $links) {
            $columnLink = $this->link(MenuLocation::Footer, $title, null, null, $column++);
            $index = 0;
            foreach ($links as $label => $target) {
                $this->link(MenuLocation::Footer, $label, \is_string($target) ? $target : null, $columnLink, $index++, $target instanceof Page ? $target : null);
            }
        }

        $manager->flush();
    }

    /** Second-level page of the "Le Club" section: excerpt shown on its card, link label. */
    private function section(Page $club, string $title, string $slug, string $excerpt, string $linkLabel): Page
    {
        return $this->page($title, $slug, $club, $excerpt, [
            new B\TextBlock('<p>'.$excerpt.'</p>'),
        ], 'Le Club')->setLinkLabel($linkLabel);
    }

    /**
     * @param list<B\BlockInterface> $body
     * @param list<B\BlockInterface> $aside
     */
    private function page(string $title, string $slug, ?Page $parent, string $excerpt, array $body, ?string $kicker = null, array $aside = []): Page
    {
        $page = (new Page($title, $slug))
            ->setParent($parent)
            ->setKicker($kicker)
            ->setExcerpt($excerpt)
            ->setAside($this->blocks->serializeAll($aside))
            ->setPosition(null === $parent ? 0 : $parent->getChildren()->count())
            ->setPublishedAt(new \DateTimeImmutable('-30 days'))
            ->setUpdatedAt(new \DateTimeImmutable('-3 days'))
            ->setBody($this->blocks->serializeAll($body));
        $parent?->getChildren()->add($page);
        $this->manager->persist($page);

        return $page;
    }

    /** @var array<string, MenuItem> menu items by target page path */
    private array $pageLinks = [];

    private function link(MenuLocation $location, string $label, ?string $url, ?MenuItem $parent, int $position, ?Page $page = null, ?string $description = null): MenuItem
    {
        $item = (new MenuItem($location, $label))->setUrl($url)->setPage($page)->setParent($parent)->setPosition($position)->setDescription($description);
        $this->manager->persist($item);
        if (null !== $page && MenuLocation::Main === $location) {
            $this->pageLinks[$page->getPath()] = $item;
        }

        return $item;
    }

    private function menuItemFor(Page $page): MenuItem
    {
        return $this->pageLinks[$page->getPath()];
    }

    private function category(string $name, string $slug, int $position): DocumentCategory
    {
        $category = (new DocumentCategory($name, $slug))->setPosition($position);
        $this->manager->persist($category);
        // Blocks reference the category by id
        $this->manager->flush();

        return $category;
    }

    private int $documentPosition = 0;

    /**
     * @param list<string> $details dated mentions ("Adoptés le 15/12/2025")
     */
    private function document(string $title, string $file, DocumentCategory $category, ?string $description = null, ?string $version = null, array $details = []): Document
    {
        $media = $this->storage->storeCopy($this->mediaDirectory.'/'.$file);
        $this->manager->persist($media);

        $document = (new Document($title, $media))
            ->setCategory($category)
            ->setPosition($this->documentPosition++)
            ->setDescription($description)
            ->setVersion($version)
            ->setDetails($details);
        $this->manager->persist($document);

        return $document;
    }
}
