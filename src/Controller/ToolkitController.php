<?php

declare(strict_types=1);

namespace App\Controller;

use App\Content\Block as B;
use App\Content\PlacedBlock;
use App\Content\TocEntry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Living style guide of the design toolkit, available in the dev environment only.
 */
final class ToolkitController extends AbstractController
{
    #[Route('/_toolkit', name: 'app_toolkit', methods: ['GET'], env: 'dev')]
    public function index(): Response
    {
        return $this->render('toolkit/index.html.twig', ['blocks' => $this->sampleBlocks()]);
    }

    /**
     * One sample of each content block that needs no media (pictures, files and videos are shown
     * by the demo article of the fixtures).
     *
     * @return list<PlacedBlock>
     */
    private function sampleBlocks(): array
    {
        $blocks = [
            new B\SectionBlock('Section : titre numéroté', 'Rubrique', 'Introduction de la section, entrée du sommaire.'),
            new B\TextBlock('<p>Texte riche : <strong>gras</strong>, <em>italique</em> et <a href="#">lien</a>.</p><p>Second paragraphe.</p>'),
            new B\HeadingBlock('Sous-titre'),
            new B\CalloutBlock('Encadré info', '<p>Variante <code>info</code>.</p>', 'info'),
            new B\CalloutBlock('Encadré conseil', '<p>Variante <code>tip</code>.</p>', 'tip'),
            new B\CalloutBlock('Encadré vigilance', '<p>Variante <code>warning</code>.</p>', 'warning'),
            new B\ListBlock(['<strong>Liste</strong> à puces', 'Deuxième point']),
            new B\ListBlock(['Liste numérotée', 'Deuxième point'], true),
            new B\QuoteBlock('« Citation mise en exergue. »', 'Auteur · Fonction'),
            new B\StepsBlock([new B\StepItem('Étape', 'Texte de l’étape.'), new B\StepItem('Étape suivante', 'Texte.')]),
            new B\TabsBlock([
                new B\Tab('Onglet 1', 'Panneau 1', '<p>Texte du panneau.</p>', [new B\Fact('Repère', 'Texte'), new B\Fact('Repère', 'Texte')], 'Action', '#'),
                new B\Tab('Onglet 2', 'Panneau 2', '<p>Autre panneau.</p>'),
            ]),
            new B\ChecklistBlock('Checklist', ['Première vérification', 'Deuxième vérification'], 'Les coches restent dans le navigateur.'),
            new B\TableBlock(['Colonne', 'Valeur A', 'Valeur B'], [['Ligne', 'A1', 'B1'], ['Ligne', 'A2', 'B2']], 'Légende du tableau.'),
            new B\NotesBlock('Notes', [new B\Fact('Libellé', 'Texte de la note.'), new B\Fact('Libellé', 'Autre note.')]),
            new B\GlossaryBlock([new B\Fact('Terme', 'Définition du terme.')], 'Glossaire'),
            new B\FaqBlock([new B\FaqItem('Question ouverte ?', '<p>Réponse.</p>'), new B\FaqItem('Question fermée ?', '<p>Réponse.</p>')], 'Questions fréquentes'),
            new B\LinksBlock([new B\LinkItem('Lien', '#', 'Description du lien.')]),
            new B\ResourceBlock('Ressource', 'Carte de la colonne latérale.', 'Lien ↓', '#'),
            new B\TakeawaysBlock('Bandeau de conclusion', 'L’essentiel', 'Texte de synthèse.', ['Point clé', 'Autre point'], 'Action', '#'),
        ];

        return array_map(static function (B\BlockInterface $block, int $index): PlacedBlock {
            $toc = $block instanceof B\SectionBlock || $block instanceof B\TakeawaysBlock ? new TocEntry('0'.($index ? 2 : 1), 'Entrée', 'toolkit-'.$index) : null;

            return new PlacedBlock($block, 'toolkit-'.$index, $toc);
        }, $blocks, array_keys($blocks));
    }
}
