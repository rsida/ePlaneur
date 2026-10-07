<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Content\Block as B;
use App\Entity\Category;
use App\Entity\Group;
use App\Entity\Media;
use App\Entity\Post;
use App\Entity\User;
use App\Media\MediaStorage;
use App\Security\DefaultGroup;
use App\Security\Visibility;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Development content: the demo article of the Figma mock-up (every block type), two related posts
 * and a committee-only post. Pictures and files come from fixtures/media/.
 */
final class PostFixtures extends Fixture implements DependentFixtureInterface
{
    private ObjectManager $manager;

    public function __construct(
        private readonly MediaStorage $storage,
        private readonly B\BlockFactory $blocks,
        private readonly UserPasswordHasherInterface $passwordHasher,
        #[Autowire('%kernel.project_dir%/fixtures/media')]
        private readonly string $mediaDirectory,
    ) {
    }

    public function getDependencies(): array
    {
        return [AppFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $this->manager = $manager;
        // The loader has just emptied the media table: the stored files are orphans
        $this->storage->purge();

        $guides = $this->category('Guide pratique', 'guide-pratique');
        $practices = $this->category('Bonnes pratiques', 'bonnes-pratiques');
        $clubLife = $this->category('Vie du club', 'vie-du-club');

        $author = (new User())
            ->setEmail('camille@eplaneur.test')
            ->setDisplayName('Camille Laurent')
            ->setVerified(true)
            ->setJobTitle('Rédactrice · Profil fictif')
            ->setBio('Passionnée de pédagogie et de grands espaces, Camille accompagne les nouveaux pilotes virtuels avec une idée simple : comprendre, essayer, puis partager. Profil créé pour cet article de démonstration.')
            ->setAvatar($this->media('author-camille.png', 'Portrait de Camille Laurent'))
            ->addGroup($this->getReference('group-'.DefaultGroup::Committee->value, Group::class));
        $author->setPassword($this->passwordHasher->hashPassword($author, AppFixtures::PASSWORD));
        $manager->persist($author);

        $this->demoArticle($guides, $author);

        $this->simplePost('Les bons réflexes avant de décoller', 'les-bons-reflexes-avant-de-decoller', $practices, 'carousel-valley.png',
            'Une préparation simple pour partir l’esprit tranquille.', '-3 days', $author);
        $this->simplePost('Un cockpit prêt pour l’aventure', 'un-cockpit-pret-pour-l-aventure', $guides, 'related-config.png',
            'Commandes, vue et audio : trouvez votre équilibre.', '-6 days', $author);
        $this->simplePost('Lire le ciel, un nuage à la fois', 'lire-le-ciel-un-nuage-a-la-fois', $guides, 'related-sky.png',
            'Les premiers repères pour observer l’air et le relief.', '-9 days', $author);

        $minutes = $this->simplePost('Compte rendu de la réunion du Comité Directeur', 'compte-rendu-reunion-comite-directeur', $clubLife, null,
            'Décisions et suivi des actions de la réunion du Comité Directeur. Contenu réservé au comité.', '-2 days', $author);
        // Committee minutes: hidden from the other readers, not listed with a padlock
        $minutes->setVisibility(Visibility::Groups)->addAllowedGroup($this->getReference('group-'.DefaultGroup::Committee->value, Group::class))->setAnnounced(false);

        $manager->flush();
    }

    private function demoArticle(Category $category, User $author): void
    {
        $wing = $this->media('wing-valley.png', 'Aile de planeur au-dessus d’une vallée alpine', 'Image d’ambiance générée pour la maquette · Crédit : illustration ePlaneur · Pas une capture de Condor.');
        $poster = $this->media('video-poster-cockpit.png', '');
        $duo = [
            $this->media('duo-cockpit.png', 'Tableau de bord d’un planeur, main sur le manche', 'Illustration ePlaneur · Image générée.'),
            $this->media('duo-airfield.png', 'Planeur posé sur un terrain herbeux en montagne', 'Illustration ePlaneur · Image générée.'),
        ];
        $slides = [
            ['carousel-valley.png', 'La vallée', 'La vallée, un repère pour lire le relief.'],
            ['carousel-relief.png', 'Le relief', 'Les crêtes dessinent les zones de vol.'],
            ['carousel-sky.png', 'Le ciel', 'Les nuages racontent l’air autour de vous.'],
            ['carousel-field.png', 'Le terrain', 'Un terrain repéré avant le départ rassure.'],
        ];
        $briefing = $this->media('briefing-premieres-ailes.pdf', null);
        $checklist = $this->media('ma-checklist-de-vol.txt', null);

        $body = [
            new B\SectionBlock('Le premier objectif : se sentir à sa place.', 'Premières ailes',
                'Pas de record à battre. Pour cette première sortie, on cherche surtout un cockpit familier, un horizon calme et quelques bonnes habitudes.', 'Trouver ses repères'),
            new B\TextBlock('<p>Le planeur virtuel offre un terrain de découverte précieux : on peut recommencer un décollage, observer un paysage et comprendre un virage sans se presser. Au départ, oubliez la distance parcourue. <strong>La réussite, c’est de savoir ce que vous faites et pourquoi vous le faites.</strong></p><p>Chez ePlaneur, la progression se partage. Un membre vous accompagne, répond à vos questions et vous aide à relier les sensations aux instruments. Pour compléter ce guide, gardez à portée de main le <a href="/#formation">parcours de formation débutant</a> et revenez-y après chaque séance.</p>'),
            new B\CalloutBlock('À retenir · Une séance, une intention', '<p>Choisissez un objectif simple : tenir une trajectoire, enchaîner deux virages ou repérer une zone d’atterrissage. Mieux vaut une séance courte et comprise qu’un long vol subi.</p>', 'info'),
            new B\ImageBlock($this->id($wing), 'Un horizon dégagé aide à lire l’espace avant de regarder les instruments.'),
            new B\HeadingBlock('Trois repères plutôt que dix instruments'),
            new B\TextBlock('<p>Avant de décoller, prenez le temps de reconnaître les informations essentielles. Vous pourrez enrichir votre lecture du cockpit au fil des vols ; pour l’instant, concentrez-vous sur ces trois points :</p>'),
            new B\ListBlock([
                '<strong>L’horizon</strong> : pour sentir l’assiette et garder une trajectoire lisible.',
                '<strong>La vitesse</strong> : pour rester dans une plage adaptée au planeur et à l’exercice.',
                '<strong>Le variomètre</strong> : pour observer si votre énergie augmente ou diminue.',
            ]),
            new B\QuoteBlock('« Le bon premier vol n’est pas celui où l’on va loin. C’est celui où l’on a envie de revenir. »', 'Camille Laurent · Accompagnatrice fictive, ePlaneur'),

            new B\SectionBlock('Un cockpit prêt, l’esprit plus léger.', 'Premières ailes',
                'Un peu de préparation au sol vous libère pour l’essentiel : observer, essayer et profiter du vol.', 'Préparer le cockpit'),
            new B\TextBlock('<p>Nul besoin d’un équipement spectaculaire. Un ordinateur compatible, des commandes correctement réglées et une liaison audio claire constituent un bon départ. La configuration doit surtout être stable et familière.</p>'),
            new B\StepsBlock([
                new B\StepItem('Choisir une session adaptée', 'Repérez la version de Condor, le paysage et le niveau annoncé. Un vol découverte laisse de la place aux questions.'),
                new B\StepItem('Tester au sol, puis en local', 'Vérifiez les axes, les freins et la vue. Faites un court vol hors réseau pour éviter de découvrir les réglages au départ.'),
                new B\StepItem('Arriver un peu avant le briefing', 'Prévoyez un temps pour le son et la connexion. Présentez-vous comme débutant : personne ne vous demande de tout savoir.'),
            ]),
            new B\TabsBlock([
                new B\Tab('Condor 3', 'Votre base de départ sur Condor 3',
                    '<p>Ce panneau présente un exemple de préparation, pas une procédure officielle. Consultez toujours les indications de l’organisateur pour la session choisie.</p>',
                    [new B\Fact('À installer', 'Le simulateur à jour et le paysage annoncé pour la sortie.'), new B\Fact('À vérifier', 'Le planeur choisi, les commandes et une connexion audio stable.')],
                    'Consulter le guide Condor 3', '/#guides'),
                new B\Tab('Condor 2', 'Votre base de départ sur Condor 2',
                    '<p>Condor 2 reste très utilisé pour les sorties du club. Vérifiez que votre installation correspond à la version annoncée.</p>',
                    [new B\Fact('À installer', 'La dernière mise à jour de Condor 2 et le paysage de la sortie.'), new B\Fact('À vérifier', 'Les axes du joystick et le planeur imposé par l’organisateur.')],
                    'Consulter le guide Condor 2', '/#guides'),
                new B\Tab('Commandes & audio', 'Se faire entendre, bien entendre',
                    '<p>Une liaison audio claire compte autant que des commandes bien réglées : on vole ensemble, on se parle.</p>',
                    [new B\Fact('Casque', 'Testez le micro avant la séance, sans écho ni souffle.'), new B\Fact('Commandes', 'Repérez aérofreins, largage et trim sur votre matériel.')]),
            ]),
            new B\CalloutBlock('Conseil · Gardez vos réglages simples', '<p>Évitez de modifier plusieurs paramètres juste avant une sortie. Notez vos réglages actuels et ne changez qu’une chose à la fois : vous saurez plus facilement ce qui vous aide.</p>', 'tip'),
            new B\ChecklistBlock('La checklist avant le départ', [
                'Version du simulateur et paysage vérifiés',
                'Joystick calibré et aérofreins repérés',
                'Casque et micro testés',
                'Briefing et plan de vol lus',
                'Créneau libre et objectif personnel choisis',
            ], 'Vos coches restent enregistrées dans ce navigateur.'),
            new B\HeadingBlock('Deux versions, le même soin de préparation'),
            new B\TextBlock('<p>Le point important n’est pas de comparer les performances des versions, mais de rejoindre une session compatible. Le tableau ci-dessous illustre les informations à présenter dans un guide.</p>'),
            new B\TableBlock(['À vérifier', 'Condor 2', 'Condor 3'], [
                ['Point de départ', 'Installation existante', 'Installation récente'],
                ['Version de session', 'Session Condor 2', 'Session Condor 3'],
                ['Paysage', 'Identique à celui du groupe', 'Identique à celui du groupe'],
                ['Commandes', 'Axes et touches à vérifier', 'Axes et touches à vérifier'],
                ['Premier essai', 'Vol local avant le réseau', 'Vol local avant le réseau'],
            ], 'Tableau de démonstration · Vérifiez les prérequis du vol auprès du club.'),

            new B\SectionBlock('Observer d’abord. Essayer ensuite.', 'Premières ailes',
                'Une image, une séquence, un détail : varier les supports aide à comprendre sans multiplier les consignes.', 'Voir le vol autrement'),
            new B\TextBlock('<p>Avant votre première séance, imaginez le déroulé du vol. Où porter le regard au décollage ? Quand consulter la vitesse ? Comment retrouver un terrain connu ? La vidéo ci-dessous montre la place qu’un tutoriel peut prendre dans un article.</p>'),
            new B\VideoBlock('https://www.youtube.com/watch?v=aqz-KE-bpKQ', 'Les premières minutes dans le cockpit', 'Du regard extérieur aux premiers virages', $this->id($poster),
                'Vidéo d’exemple (Big Buck Bunny, Blender Foundation) en attendant un vrai tutoriel du club.'),
            new B\HeadingBlock('Le détail et la vue d’ensemble'),
            new B\TextBlock('<p>On apprend autant en regardant le cockpit qu’en observant ce qui l’entoure. Alternez ces deux échelles : les instruments racontent l’énergie du planeur ; le paysage raconte les possibilités du vol.</p>'),
            new B\ImageGridBlock([
                new B\CaptionedImage($this->id($duo[0]), '01 · Retrouver ses repères dans le cockpit.'),
                new B\CaptionedImage($this->id($duo[1]), '02 · Situer le terrain avant de partir.'),
            ]),
            new B\CarouselBlock(array_map(
                fn (array $slide): B\CarouselSlide => new B\CarouselSlide($this->id($this->media($slide[0], $slide[2], 'Visuel d’ambiance ePlaneur, pas une capture de Condor.')), $slide[1], $slide[2]),
                $slides,
            ), 'Quatre regards sur le même horizon'),

            new B\SectionBlock('Le briefing, votre fil conducteur.', 'Premières ailes',
                'Avant de rejoindre les autres, prenez quelques minutes pour comprendre le cadre de la sortie et les points à vérifier ensemble.', 'Lire le briefing'),
            new B\TextBlock('<p>Un bon briefing évite les surprises : il précise la version utilisée, l’environnement, le déroulé et la manière de communiquer. Le document ci-dessous est un exemple de mise en page. Il peut se lire directement dans l’article ou être téléchargé.</p>'),
            new B\CalloutBlock('Vigilance · Simulation uniquement', '<p>Ce contenu fictif ne remplace ni un manuel de vol ni une formation aéronautique. Il illustre une sortie virtuelle et ne doit jamais servir à préparer ou conduire un vol réel.</p>', 'warning'),
            new B\PdfBlock($this->id($briefing)),
            new B\DownloadsBlock([
                new B\DownloadItem($this->id($briefing), 'Le briefing complet', 'Exemple'),
                new B\DownloadItem($this->id($checklist), 'Ma checklist de vol', 'Version texte'),
            ]),

            new B\SectionBlock('Le vol se termine. L’apprentissage continue.', 'Premières ailes',
                'Quelques notes, une question et un prochain objectif : c’est souvent là que la confiance se construit.', 'Progresser après le vol'),
            new B\TextBlock('<p>Après l’atterrissage virtuel, ne cherchez pas seulement ce qui n’a pas marché. Repérez aussi un geste devenu plus naturel, une information mieux comprise ou un échange qui vous a aidé. Ces petites avancées rendent la progression visible.</p>'),
            new B\NotesBlock('Trois lignes pour le prochain vol', [
                new B\Fact('J’ai compris', 'Regarder dehors m’aide à garder une trajectoire plus régulière.'),
                new B\Fact('Je me demande', 'Comment mieux interpréter le variomètre dans un virage ?'),
                new B\Fact('La prochaine fois', 'Je travaille un seul exercice avant de rejoindre le groupe.'),
            ], 'Exemple de notes après un premier vol.'),
            new B\CalloutBlock('Conseil · Débriefez à chaud, progressez à votre rythme', '<p>Gardez cinq minutes à la fin de la séance pour poser votre question principale. Un retour précis sur une situation vécue est souvent plus utile qu’une longue liste de réglages à essayer.</p>', 'tip'),
            new B\GlossaryBlock([
                new B\Fact('Assiette', 'Orientation du planeur par rapport à l’horizon, notamment vers le haut ou vers le bas.'),
                new B\Fact('Variomètre', 'Instrument qui renseigne sur la montée ou la descente. Il aide à lire l’évolution du vol.'),
                new B\Fact('Thermique', 'Mouvement d’air ascendant que le planeur peut exploiter pour gagner de l’altitude.'),
            ], 'Quelques mots pour parler le même ciel'),
            new B\FaqBlock([
                new B\FaqItem('Faut-il déjà savoir piloter pour rejoindre une sortie ?', '<p>Non, si la séance est annoncée pour les débutants. Signalez votre niveau avant le départ et choisissez un vol découverte. Le groupe pourra adapter ses explications ; l’objectif est de comprendre les bases, pas de suivre les plus rapides.</p>'),
                new B\FaqItem('Peut-on voler ensemble avec des versions différentes ?', '<p>Non : tous les pilotes d’une session utilisent la même version de Condor. Vérifiez la version annoncée avant de vous inscrire.</p>'),
                new B\FaqItem('Que faire si je perds la connexion pendant le vol ?', '<p>Pas de panique : prévenez le groupe sur le canal audio si vous le pouvez, puis rejoignez la session si l’organisateur l’autorise.</p>'),
                new B\FaqItem('Où poser une question après la séance ?', '<p>Dans l’espace membre ou auprès de la personne qui vous a accompagné : toutes les questions sont les bienvenues.</p>'),
            ], 'Les questions qu’on se pose avant de partir'),
            new B\LinksBlock([
                new B\LinkItem('Le parcours débutant', '/#formation', 'Des repères progressifs, du réglage des commandes aux premiers virages.'),
                new B\LinkItem('Les bonnes pratiques en réseau', '/#guides', 'Préparer sa connexion et communiquer simplement pendant une séance.'),
                new B\LinkItem('Le calendrier des vols', '/#vols', 'Choisir une sortie adaptée à sa version et à son niveau.'),
            ]),
        ];

        $aside = [
            new B\ResourceBlock('Votre mémo avant le vol', 'Le briefing et la checklist, à retrouver au chapitre 04.', 'Voir les documents ↓', '#lire-le-briefing'),
        ];

        $outro = [
            new B\TakeawaysBlock('Un premier vol, pas à pas. Ensemble.', null,
                'Préparez votre environnement, choisissez un objectif modeste et prenez le temps d’échanger. La confiance vient des repères que l’on comprend, pas des kilomètres que l’on affiche.',
                ['Un cockpit vérifié avant de rejoindre la session.', 'Un briefing lu et une question à poser si besoin.', 'Un apprentissage noté pour la prochaine sortie.'],
                'Découvrir les vols pour débutants', '/#vols', 'L’essentiel à emporter'),
        ];

        $post = (new Post('Votre premier vol. Le grand air se prépare.', 'votre-premier-vol'))
            ->setKicker('Guide pratique · Premiers vols')
            ->setBadge('Débutant')
            ->setExcerpt('Du cockpit à la première sortie en réseau : les bons repères pour prendre confiance, préparer votre vol virtuel et partager le ciel avec ePlaneur.')
            ->setHighlight("Chacun son rythme.\nLa même envie de ciel.")
            ->setHighlightNote("Article de démonstration\nCollection de blocs éditoriaux")
            ->setCover($this->media('cover-glider-alps.png', 'Planeur au-dessus des Alpes', 'Visuel d’ambiance ePlaneur · Pas une capture de Condor'))
            ->setCoverCaption('Avant le premier virage, il y a l’envie d’y aller.')
            ->setCategory($category)
            ->setKeywords(['Premier vol', 'Formation', 'Vol en réseau'])
            ->setAuthor($author)
            ->setPublishedAt(new \DateTimeImmutable('-10 days 18:00'))
            ->setUpdatedAt(new \DateTimeImmutable('-1 day 09:00'))
            ->setFeatured(true)
            ->setBody($this->blocks->serializeAll($body))
            ->setAside($this->blocks->serializeAll($aside))
            ->setOutro($this->blocks->serializeAll($outro));
        $this->manager->persist($post);
    }

    private function simplePost(string $title, string $slug, Category $category, ?string $cover, string $excerpt, string $publishedAt, User $author): Post
    {
        $post = (new Post($title, $slug))
            ->setExcerpt($excerpt)
            ->setCategory($category)
            ->setAuthor($author)
            ->setPublishedAt(new \DateTimeImmutable($publishedAt))
            ->setCover(null !== $cover ? $this->media($cover, '', 'Visuel d’ambiance ePlaneur, pas une capture de Condor.') : null)
            ->setBody($this->blocks->serializeAll([
                new B\TextBlock('<p>'.$excerpt.' Article d’exemple : son contenu complet reste à écrire.</p>'),
            ]));
        $this->manager->persist($post);

        return $post;
    }

    private function category(string $name, string $slug): Category
    {
        $category = new Category($name, $slug);
        $this->manager->persist($category);

        return $category;
    }

    /** @var array<string, Media> */
    private array $stored = [];

    private function media(string $file, ?string $alt, ?string $credit = null): Media
    {
        if (!isset($this->stored[$file])) {
            $media = $this->storage->storeCopy($this->mediaDirectory.'/'.$file)->setAlt($alt)->setCredit($credit);
            $this->manager->persist($media);
            $this->stored[$file] = $media;
        }

        return $this->stored[$file];
    }

    /** Blocks reference media by id: flush to get it. */
    private function id(Media $media): int
    {
        if (null === $media->getId()) {
            $this->manager->flush();
        }

        return (int) $media->getId();
    }
}
