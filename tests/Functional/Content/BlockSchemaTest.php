<?php

declare(strict_types=1);

namespace App\Tests\Functional\Content;

use App\Content\Block\BlockFactory;
use App\Content\Editor\BlockSchema;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The editor schema covers every block type, and a new block (its defaults) can always be read.
 */
final class BlockSchemaTest extends KernelTestCase
{
    public function testEveryBlockTypeHasFieldsAndValidDefaults(): void
    {
        $schema = static::getContainer()->get(BlockSchema::class)->describe('page');
        $factory = static::getContainer()->get(BlockFactory::class);

        self::assertNotEmpty($schema['types']);
        foreach ($schema['types'] as $type) {
            self::assertNotEmpty($type['fields'], $type['type'].' has editable fields');
            self::assertContains($type['group'], array_column($schema['groups'], 'id'));
            $block = $factory->createOrFail(['type' => $type['type'], 'data' => $type['defaults']]);
            self::assertSame($type['type'], $block::type()->value, 'A new '.$type['type'].' block can be read');
        }
    }

    public function testPostsDoNotOfferSubPages(): void
    {
        $types = array_column(static::getContainer()->get(BlockSchema::class)->describe('post')['types'], 'type');

        self::assertNotContains('child_pages', $types);
        self::assertContains('takeaways', $types);
    }

    public function testListFieldsDescribeTheirItems(): void
    {
        $types = array_column(static::getContainer()->get(BlockSchema::class)->describe('post')['types'], null, 'type');
        $steps = array_column($types['steps']['fields'], null, 'name')['steps'];

        self::assertSame('items', $steps['widget']);
        self::assertSame(['title', 'text'], array_column($steps['item']['fields'] ?? [], 'name'));
        self::assertSame([['title' => '', 'text' => null]], $types['steps']['defaults']['steps']);
    }
}
