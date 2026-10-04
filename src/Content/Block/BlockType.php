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
    case List = 'list';
    case Steps = 'steps';
    case Tabs = 'tabs';
    case Checklist = 'checklist';
    case Table = 'table';
    case Pdf = 'pdf';
    case Downloads = 'downloads';
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
            self::List => ListBlock::class,
            self::Steps => StepsBlock::class,
            self::Tabs => TabsBlock::class,
            self::Checklist => ChecklistBlock::class,
            self::Table => TableBlock::class,
            self::Pdf => PdfBlock::class,
            self::Downloads => DownloadsBlock::class,
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
            self::Section => 'Section (titre numéroté, entrée du sommaire)',
            self::Text => 'Texte',
            self::Heading => 'Sous-titre',
            self::Callout => 'Encadré',
            self::Image => 'Image',
            self::ImageGrid => 'Images côte à côte',
            self::Carousel => 'Carrousel d’images',
            self::Video => 'Vidéo',
            self::Quote => 'Citation',
            self::List => 'Liste',
            self::Steps => 'Étapes numérotées',
            self::Tabs => 'Onglets',
            self::Checklist => 'Checklist',
            self::Table => 'Tableau',
            self::Pdf => 'Document PDF intégré',
            self::Downloads => 'Fichiers à télécharger',
            self::Notes => 'Notes',
            self::Glossary => 'Glossaire',
            self::Faq => 'Questions fréquentes',
            self::Links => 'Liens',
            self::Resource => 'Ressource mise en avant',
            self::Takeaways => 'L’essentiel à retenir',
        };
    }
}
