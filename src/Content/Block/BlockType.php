<?php

declare(strict_types=1);

namespace App\Content\Block;

/**
 * Catalogue of block types. The value is what is stored in JSON (`{"type": "text", "data": {...}}`):
 * never rename a value without migrating the stored content.
 *
 * Adding a type: create the block class, its Twig component in templates/components/Block/, add a
 * case here, then show it in the /_toolkit style guide.
 */
enum BlockType: string
{
    case Section = 'section';
    case Text = 'text';
    case Heading = 'heading';
    case Callout = 'callout';
    case Image = 'image';
    case ImageGrid = 'image_grid';
    case Carousel = 'carousel';
    case Video = 'video';
    case Quote = 'quote';
    case Excerpt = 'excerpt';
    case List = 'list';
    case Steps = 'steps';
    case Tabs = 'tabs';
    case Checklist = 'checklist';
    case Table = 'table';
    case Pdf = 'pdf';
    case Downloads = 'downloads';
    case Documents = 'documents';
    case Document = 'document';
    case ChildPages = 'child_pages';
    case Notes = 'notes';
    case Glossary = 'glossary';
    case Faq = 'faq';
    case Links = 'links';
    case Resource = 'resource';
    case Takeaways = 'takeaways';

    /**
     * @return class-string<BlockInterface>
     */
    public function blockClass(): string
    {
        return match ($this) {
            self::Section => SectionBlock::class,
            self::Text => TextBlock::class,
            self::Heading => HeadingBlock::class,
            self::Callout => CalloutBlock::class,
            self::Image => ImageBlock::class,
            self::ImageGrid => ImageGridBlock::class,
            self::Carousel => CarouselBlock::class,
            self::Video => VideoBlock::class,
            self::Quote => QuoteBlock::class,
            self::Excerpt => ExcerptBlock::class,
            self::List => ListBlock::class,
            self::Steps => StepsBlock::class,
            self::Tabs => TabsBlock::class,
            self::Checklist => ChecklistBlock::class,
            self::Table => TableBlock::class,
            self::Pdf => PdfBlock::class,
            self::Downloads => DownloadsBlock::class,
            self::Documents => DocumentsBlock::class,
            self::Document => DocumentBlock::class,
            self::ChildPages => ChildPagesBlock::class,
            self::Notes => NotesBlock::class,
            self::Glossary => GlossaryBlock::class,
            self::Faq => FaqBlock::class,
            self::Links => LinksBlock::class,
            self::Resource => ResourceBlock::class,
            self::Takeaways => TakeawaysBlock::class,
        };
    }

    /** Twig component rendering the block. */
    public function component(): string
    {
        return 'Block:'.str_replace('_', '', ucwords($this->value, '_'));
    }

    public function label(): string
    {
        return match ($this) {
            self::Section => 'Section',
            self::Text => 'Texte',
            self::Heading => 'Sous-titre',
            self::Callout => 'Encadré',
            self::Image => 'Image',
            self::ImageGrid => 'Images côte à côte',
            self::Carousel => 'Carrousel d’images',
            self::Video => 'Vidéo YouTube ou Vimeo',
            self::Quote => 'Citation',
            self::Excerpt => 'Extrait référencé',
            self::List => 'Liste',
            self::Steps => 'Étapes numérotées',
            self::Tabs => 'Onglets',
            self::Checklist => 'Checklist',
            self::Table => 'Tableau',
            self::Pdf => 'Document PDF intégré',
            self::Downloads => 'Fichiers à télécharger',
            self::Documents => 'Documents officiels',
            self::Document => 'Document mis en avant',
            self::ChildPages => 'Sous-pages',
            self::Notes => 'Notes',
            self::Glossary => 'Glossaire',
            self::Faq => 'Questions fréquentes',
            self::Links => 'Liens',
            self::Resource => 'Ressource mise en avant',
            self::Takeaways => 'L’essentiel à retenir',
        };
    }

    /** Group of the block library: text, media, layout or club. */
    public function group(): string
    {
        return match ($this) {
            self::Text, self::Heading, self::Section, self::List, self::Quote, self::Excerpt, self::Callout => 'text',
            self::Image, self::ImageGrid, self::Carousel, self::Video, self::Pdf, self::Downloads => 'media',
            self::Steps, self::Tabs, self::Checklist, self::Table, self::Faq, self::Glossary, self::Notes, self::Links => 'layout',
            self::Documents, self::Document, self::ChildPages, self::Resource, self::Takeaways => 'club',
        };
    }

    /** Icon of the editor (assets/icons/admin/). */
    public function icon(): string
    {
        return match ($this) {
            self::Section => 'hash',
            self::Text => 'type',
            self::Heading => 'heading',
            self::Callout => 'info',
            self::Image => 'image',
            self::ImageGrid => 'images',
            self::Carousel => 'gallery-horizontal-end',
            self::Video => 'video',
            self::Quote => 'quote',
            self::Excerpt => 'text-quote',
            self::List => 'list',
            self::Steps => 'list-ordered',
            self::Tabs => 'panels-top-left',
            self::Checklist => 'list-checks',
            self::Table => 'table',
            self::Pdf => 'file-text',
            self::Downloads => 'download',
            self::Documents => 'file-stack',
            self::Document => 'file-badge',
            self::ChildPages => 'layout-grid',
            self::Notes => 'notebook-pen',
            self::Glossary => 'book-open-text',
            self::Faq => 'circle-help',
            self::Links => 'link',
            self::Resource => 'bookmark',
            self::Takeaways => 'flag',
        };
    }

    /** One line under the name, in the library and the "/" command. */
    public function description(): string
    {
        return match ($this) {
            self::Section => 'Titre numéroté, entrée du sommaire',
            self::Text => 'Paragraphes : gras, italique, liens, listes',
            self::Heading => 'Titre intermédiaire',
            self::Callout => 'À retenir, conseil ou vigilance',
            self::Image => 'Une image avec sa légende',
            self::ImageGrid => 'Plusieurs images sur une ligne',
            self::Carousel => 'Images à faire défiler, avec vignettes',
            self::Video => 'YouTube ou Vimeo, chargée au clic',
            self::Quote => 'Citation, éventuellement d’un texte officiel',
            self::Excerpt => 'Passage d’un texte de référence',
            self::List => 'Liste à puces ou numérotée',
            self::Steps => 'Étapes 01, 02, 03…',
            self::Tabs => 'Contenus côte à côte, un onglet chacun',
            self::Checklist => 'Points à cocher par le lecteur',
            self::Table => 'Lignes et colonnes',
            self::Pdf => 'Lecteur PDF dans la page',
            self::Downloads => 'Liste de fichiers à télécharger',
            self::Documents => 'Afficher les textes de référence du club',
            self::Document => 'Un document officiel dans une carte',
            self::ChildPages => 'Cartes des sous-pages de la page',
            self::Notes => 'Libellés et textes courts',
            self::Glossary => 'Termes et définitions',
            self::Faq => 'Questions et réponses dépliables',
            self::Links => 'Liens vers d’autres pages ou sites',
            self::Resource => 'Carte de la barre latérale avec un lien',
            self::Takeaways => 'Bande finale numérotée comme une section',
        };
    }

    /** Whether the block makes sense in this kind of content (post or page). */
    public function allowedIn(string $contentKind): bool
    {
        return self::ChildPages !== $this || 'page' === $contentKind;
    }
}
