<?php

declare(strict_types=1);

namespace App\Tests\Functional\WordPress;

use App\Content\Block as B;
use App\WordPress\ConversionContext;
use App\WordPress\HtmlConverter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Conversion of WordPress HTML (Gutenberg, Shortcodes Ultimate) into content blocks.
 */
final class HtmlConverterTest extends KernelTestCase
{
    /** @var list<string> */
    private array $imported = [];

    public function testGutenbergBlocksBecomeSiteBlocks(): void
    {
        $context = $this->context();
        $blocks = static::getContainer()->get(HtmlConverter::class)->convert(<<<'HTML'
            <h2 class="wp-block-heading">Nature des statuts</h2>
            <p class="wp-block-paragraph">Premier <strong>paragraphe</strong> avec un <a href="https://club.eplaneur.fr/le-club/statuts/">lien</a>.</p>
            <ul class="wp-block-list"><li>Un</li><li>Deux</li></ul>
            <hr class="wp-block-separator"/>
            <p>Après le séparateur.</p>
            <h3 class="wp-block-heading">Sous-titre</h3>
            <figure class="wp-block-image"><img src="https://club.eplaneur.fr/wp-content/uploads/2025/03/vue-1024x576.jpg" alt="Une vue"><figcaption>Légende</figcaption></figure>
            <figure class="wp-block-table"><table><thead><tr><th>Session</th><th>Heure</th></tr></thead><tbody><tr><td>Découverte</td><td>20:30</td></tr></tbody></table></figure>
            <div class="wp-block-file"><a href="https://club.eplaneur.fr/wp-content/uploads/a.pdf">Statuts signés</a><a href="https://club.eplaneur.fr/wp-content/uploads/a.pdf" class="wp-block-file__button">Télécharger</a></div>
            <div class="wp-block-file"><a href="https://club.eplaneur.fr/wp-content/uploads/b.pdf">Règlement</a></div>
            <figure class="wp-block-embed is-provider-youtube"><div class="wp-block-embed__wrapper"><iframe src="https://www.youtube.com/embed/aqz-KE-bpKQ?feature=oembed"></iframe></div></figure>
            <div class="wp-block-query"><p>Liste dynamique</p></div>
            HTML, $context);

        self::assertSame([B\SectionBlock::class, B\TextBlock::class, B\TextBlock::class, B\HeadingBlock::class, B\ImageBlock::class, B\TableBlock::class, B\DownloadsBlock::class, B\VideoBlock::class], array_map(static fn (B\BlockInterface $block): string => $block::class, $blocks));
        [$section, $text, $after, , $image, $table, $downloads, $video] = $blocks;
        \assert($section instanceof B\SectionBlock && $text instanceof B\TextBlock && $after instanceof B\TextBlock && $image instanceof B\ImageBlock && $table instanceof B\TableBlock && $downloads instanceof B\DownloadsBlock && $video instanceof B\VideoBlock);
        self::assertSame('Nature des statuts', $section->title);
        self::assertStringContainsString('<a href="/le-club/textes-officiels/statuts">lien</a>', $text->html, 'Links lead to the new address');
        self::assertStringContainsString('<li>Deux</li>', $text->html, 'Paragraphs and lists form one text block');
        self::assertSame('<p>Après le séparateur.</p>', $after->html, 'A separator ends a text block');
        self::assertSame('Légende', $image->caption);
        self::assertSame(['Session', 'Heure'], $table->headers);
        self::assertSame([['Découverte', '20:30']], $table->rows);
        self::assertSame(['Statuts signés', 'Règlement'], array_map(static fn (B\DownloadItem $item): string => $item->title, $downloads->files), 'Consecutive files form one block');
        self::assertNotNull($video->embedUrl());
        self::assertContains('https://club.eplaneur.fr/wp-content/uploads/2025/03/vue-1024x576.jpg', $this->imported);
        self::assertStringContainsString('élément dynamique ignoré (div.wp-block-query)', implode(' ', $context->warnings()));
    }

    public function testSpoilersBecomeQuestionsUnlessTheyHoldMore(): void
    {
        $blocks = static::getContainer()->get(HtmlConverter::class)->convert(<<<'HTML'
            <div class="su-accordion">
                <div class="su-spoiler"><div class="su-spoiler-title">Quand voler ?</div><div class="su-spoiler-content"><p>Le jeudi soir.</p></div></div>
                <div class="su-spoiler"><div class="su-spoiler-title">Le statut ?</div><div class="su-spoiler-content"><p>Signé.</p><div class="wp-block-file"><a href="https://club.eplaneur.fr/wp-content/uploads/s.pdf">Statuts</a></div></div></div>
            </div>
            <div class="su-spoiler"><div class="su-spoiler-title">Communiquer avec Discord</div><div class="su-spoiler-content"><h3>Le serveur</h3><p>Rejoindre le salon.</p></div></div>
            HTML, $this->context());

        self::assertSame([B\FaqBlock::class, B\DownloadsBlock::class, B\HeadingBlock::class, B\HeadingBlock::class, B\TextBlock::class], array_map(static fn (B\BlockInterface $block): string => $block::class, $blocks));
        [$faq, , $heading] = $blocks;
        \assert($faq instanceof B\FaqBlock && $heading instanceof B\HeadingBlock);
        self::assertSame(['Quand voler ?', 'Le statut ?'], array_map(static fn (B\FaqItem $item): string => $item->question, $faq->items));
        self::assertSame('<p>Le jeudi soir.</p>', $faq->items[0]->answer);
        self::assertSame('Communiquer avec Discord', $heading->text, 'A spoiler with headings or pictures is shown open');
    }

    public function testUploadedRecordingsAreOfferedForDownload(): void
    {
        $blocks = static::getContainer()->get(HtmlConverter::class)->convert(
            '<figure class="wp-block-audio"><audio controls src="https://club.eplaneur.fr/wp-content/uploads/reunion.mp3"></audio><figcaption>Enregistrement de la réunion</figcaption></figure>'
            .'<figure class="wp-block-video"><video controls src="https://club.eplaneur.fr/wp-content/uploads/club.mp4"></video></figure>',
            $this->context(),
        );

        self::assertCount(1, $blocks);
        self::assertInstanceOf(B\DownloadsBlock::class, $blocks[0]);
        self::assertSame(['Enregistrement de la réunion', 'Vidéo : club.mp4'], array_map(static fn (B\DownloadItem $item): string => $item->title, $blocks[0]->files));
    }

    private function context(): ConversionContext
    {
        return new ConversionContext(
            static fn (string $url): string => str_replace('https://club.eplaneur.fr/le-club/statuts/', '/le-club/textes-officiels/statuts', $url),
            function (string $url): int {
                $this->imported[] = $url;

                return \count($this->imported);
            },
            'test',
        );
    }
}
