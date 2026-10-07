<?php

declare(strict_types=1);

namespace App\Content\Editor;

use App\Content\Block\BlockFactory;
use App\Content\Block\InvalidBlockException;
use App\Content\LinkUrl;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

/**
 * Turns a block zone sent by the editor into stored blocks: each block is read through its class
 * (so only known types and fields are kept, with their types) and written back. Editor-only keys,
 * such as the block ids, are dropped.
 *
 * Values are also made safe to store: rich text is cleaned with the `app.rich_text` sanitizer (the
 * same as on display, so stored HTML can be shown in the editor as is), and link addresses must be
 * a path, an anchor, a web address or an e-mail (LinkUrl).
 *
 * @phpstan-import-type FieldSchema from BlockSchema
 */
final readonly class ZoneNormalizer
{
    public function __construct(
        private BlockFactory $blockFactory,
        private BlockSchema $schema,
        #[Target('app.rich_text')]
        private HtmlSanitizerInterface $richText,
    ) {
    }

    /**
     * @param string $contentKind "post" or "page"
     *
     * @return list<array{type: string, data: array<string, mixed>}>
     *
     * @throws InvalidZoneException listing the blocks that cannot be saved
     */
    public function normalize(mixed $zone, string $contentKind): array
    {
        if (!\is_array($zone) || !array_is_list($zone)) {
            throw new InvalidZoneException(['La liste des blocs est illisible.']);
        }

        $stored = [];
        $errors = [];
        foreach ($zone as $index => $item) {
            try {
                $block = $this->blockFactory->createOrFail(\is_array($item) ? $item : []);
                if (!$block::type()->allowedIn($contentKind)) {
                    throw new InvalidBlockException($block::type(), 'not allowed here');
                }
            } catch (InvalidBlockException $exception) {
                $errors[] = \sprintf('Bloc %d (%s) : contenu invalide.', $index + 1, $exception->type?->label() ?? 'type inconnu');
                continue;
            }

            [$data, $unsafeUrls] = $this->clean($this->blockFactory->serializeAll([$block])[0]['data'], $this->schema->fieldsOf($block::class));
            foreach ($unsafeUrls as $url) {
                $errors[] = \sprintf('Bloc %d (%s) : adresse de lien refusée « %s ». %s', $index + 1, $block::type()->label(), mb_strimwidth($url, 0, 40, '…'), LinkUrl::MESSAGE);
            }
            $stored[] = ['type' => $block::type()->value, 'data' => $data];
        }

        if ([] !== $errors) {
            throw new InvalidZoneException($errors);
        }

        return $stored;
    }

    /**
     * Cleans the rich text of block data and collects the unsafe link addresses, following the
     * #[Field] widgets of its class (lists of items included).
     *
     * @param array<string, mixed> $data
     * @param list<FieldSchema>    $fields
     *
     * @return array{array<string, mixed>, list<string>}
     */
    private function clean(array $data, array $fields): array
    {
        $unsafeUrls = [];
        foreach ($fields as $field) {
            $value = $data[$field['name']] ?? null;
            if ('rich' === $field['widget'] && \is_string($value)) {
                $data[$field['name']] = $this->richText->sanitize($value);
            } elseif ('url' === $field['widget'] && \is_string($value) && '' !== trim($value) && !LinkUrl::isSafe($value)) {
                $unsafeUrls[] = $value;
            } elseif ('items' === $field['widget'] && isset($field['item']) && \is_array($value)) {
                foreach ($value as $key => $item) {
                    if (\is_array($item)) {
                        [$data[$field['name']][$key], $itemUrls] = $this->clean($item, $field['item']['fields']);
                        array_push($unsafeUrls, ...$itemUrls);
                    }
                }
            }
        }

        return [$data, $unsafeUrls];
    }
}
