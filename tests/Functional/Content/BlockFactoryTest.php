<?php

declare(strict_types=1);

namespace App\Tests\Functional\Content;

use App\Content\Block\BlockFactory;
use App\Content\Block\BlockType;
use App\Content\Block\CalloutBlock;
use App\Content\Block\CarouselBlock;
use App\Content\Block\CarouselSlide;
use App\Content\Block\Fact;
use App\Content\Block\Tab;
use App\Content\Block\TableBlock;
use App\Content\Block\TabsBlock;
use App\Content\Block\TextBlock;
use App\Content\Block\VideoBlock;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class BlockFactoryTest extends KernelTestCase
{
    private BlockFactory $factory;

    protected function setUp(): void
    {
        $serializer = static::getContainer()->get('serializer');
        self::assertInstanceOf(NormalizerInterface::class, $serializer);
        self::assertInstanceOf(DenormalizerInterface::class, $serializer);
        $this->factory = new BlockFactory($serializer, new NullLogger());
    }

    public function testBlocksSurviveARoundTripThroughJson(): void
    {
        $blocks = [
            new TextBlock('<p>Bonjour <strong>pilote</strong></p>'),
            new CalloutBlock('À retenir', '<p>Une séance, une intention.</p>', 'tip'),
            new TabsBlock([
                new Tab('Condor 3', 'Votre base', '<p>Texte</p>', [new Fact('À installer', 'Le simulateur')], 'Guide', '/guide'),
                new Tab('Condor 2', 'Autre base'),
            ]),
            new TableBlock(['À vérifier', 'Condor 2'], [['Paysage', 'Identique']], 'Note'),
            new CarouselBlock([new CarouselSlide(12, 'La vallée', 'Légende')], 'Quatre regards'),
        ];

        $stored = json_decode(json_encode($this->factory->serializeAll($blocks), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('tabs', $stored[2]['type']);
        self::assertArrayNotHasKey('linkUrl', $stored[2]['data']['tabs'][1], 'null values are not stored');

        self::assertEquals($blocks, $this->factory->createAll($stored));
    }

    public function testBrokenBlocksAreSkipped(): void
    {
        $blocks = $this->factory->createAll([
            ['type' => 'unknown', 'data' => []],
            ['type' => 'text', 'data' => []],                 // required html missing
            ['type' => 'heading', 'data' => ['text' => 'OK']],
        ]);

        self::assertCount(1, $blocks);
        self::assertSame(BlockType::Heading, $blocks[0]::type());
    }

    public function testVideoEmbedUrls(): void
    {
        self::assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1&rel=0', (new VideoBlock('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10'))->embedUrl());
        self::assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1&rel=0', (new VideoBlock('https://youtu.be/dQw4w9WgXcQ'))->embedUrl());
        self::assertSame('https://player.vimeo.com/video/76979871?autoplay=1&dnt=1', (new VideoBlock('https://vimeo.com/76979871'))->embedUrl());
        self::assertNull((new VideoBlock('https://example.org/video.mp4'))->embedUrl());
    }

    public function testEveryBlockTypeHasAComponent(): void
    {
        foreach (BlockType::cases() as $type) {
            $template = \dirname(__DIR__, 3).'/templates/components/'.str_replace(':', '/', $type->component()).'.html.twig';
            self::assertFileExists($template, \sprintf('Missing component for block "%s".', $type->value));
        }
    }
}
