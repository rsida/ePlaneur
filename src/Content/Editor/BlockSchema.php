<?php

declare(strict_types=1);

namespace App\Content\Editor;

use App\Content\Block\BlockType;
use App\Content\Block\Field;

/**
 * Describes every block type for the editor (library, "/" command, settings panel, new blocks),
 * from the #[Field] attributes of the block classes. Given to the editor page as JSON.
 *
 * @phpstan-type FieldSchema array{name: string, label: string, widget: string, help: ?string, required: bool, inline: bool, choices: list<array{value: string, label: string}>, accept: ?string, item?: array{fields: list<array<string, mixed>>, defaults: array<string, mixed>}}
 */
final class BlockSchema
{
    public const array GROUPS = ['text' => 'Texte', 'media' => 'Médias', 'layout' => 'Mise en forme', 'club' => 'Club'];

    /** @var array<class-string, list<FieldSchema>> */
    private array $fields = [];

    /**
     * @param string $contentKind "post" or "page": some blocks only make sense in one of them
     *
     * @return array{groups: list<array{id: string, label: string}>, types: list<array{type: string, label: string, group: string, icon: string, description: string, fields: list<FieldSchema>, defaults: array<string, mixed>}>}
     */
    public function describe(string $contentKind): array
    {
        $types = [];
        foreach (BlockType::cases() as $type) {
            if (!$type->allowedIn($contentKind)) {
                continue;
            }
            $types[] = [
                'type' => $type->value,
                'label' => $type->label(),
                'group' => $type->group(),
                'icon' => $type->icon(),
                'description' => $type->description(),
                'fields' => $this->fieldsOf($type->blockClass()),
                'defaults' => $this->defaultsOf($type->blockClass()),
            ];
        }

        $groups = [];
        foreach (self::GROUPS as $id => $label) {
            $groups[] = ['id' => $id, 'label' => $label];
        }

        return ['groups' => $groups, 'types' => $types];
    }

    /**
     * Data of a new block or item: constructor defaults, empty values for the rest.
     *
     * @param class-string $class
     *
     * @return array<string, mixed>
     */
    public function defaultsOf(string $class): array
    {
        $defaults = [];
        foreach ($this->parameters($class) as [$parameter, $field]) {
            $defaults[$parameter->getName()] = $parameter->isDefaultValueAvailable()
                ? $parameter->getDefaultValue()
                : $this->emptyValue($field);
        }

        return $defaults;
    }

    /**
     * Fields of a block or item class, from its #[Field] attributes.
     *
     * @param class-string $class
     *
     * @return list<FieldSchema>
     */
    public function fieldsOf(string $class): array
    {
        if (isset($this->fields[$class])) {
            return $this->fields[$class];
        }

        $fields = [];
        foreach ($this->parameters($class) as [$parameter, $field]) {
            $type = $parameter->getType();
            $schema = [
                'name' => $parameter->getName(),
                'label' => $field->label,
                'widget' => self::widget($field, $parameter),
                'help' => $field->help,
                'required' => !$parameter->isDefaultValueAvailable() && !($type?->allowsNull() ?? true),
                'inline' => $field->inline,
                'choices' => array_map(static fn (string $value, string $label): array => ['value' => $value, 'label' => $label], array_keys($field->choices), $field->choices),
                'accept' => $field->accept,
            ];
            if (null !== $field->item) {
                $schema['item'] = ['fields' => $this->fieldsOf($field->item), 'defaults' => $this->defaultsOf($field->item)];
            }
            $fields[] = $schema;
        }

        return $this->fields[$class] = $fields;
    }

    /**
     * @param class-string $class
     *
     * @return list<array{\ReflectionParameter, Field}>
     */
    private function parameters(string $class): array
    {
        $parameters = [];
        foreach ((new \ReflectionClass($class))->getConstructor()?->getParameters() ?? [] as $parameter) {
            $attribute = $parameter->getAttributes(Field::class)[0] ?? null;
            if (null === $attribute) {
                throw new \LogicException(\sprintf('%s::$%s needs a #[Field] attribute to be edited.', $class, $parameter->getName()));
            }
            $parameters[] = [$parameter, $attribute->newInstance()];
        }

        return $parameters;
    }

    private static function widget(Field $field, \ReflectionParameter $parameter): string
    {
        if (null !== $field->widget) {
            return $field->widget;
        }

        $type = $parameter->getType();

        return match ($type instanceof \ReflectionNamedType ? $type->getName() : null) {
            'bool' => 'bool',
            'int' => 'number',
            default => 'text',
        };
    }

    private function emptyValue(Field $field): mixed
    {
        return match ($field->widget) {
            'media', 'document' => 0,
            'choice' => array_key_first($field->choices) ?? '',
            'lines' => [''],
            'rows' => [['']],
            'items' => null !== $field->item ? [$this->defaultsOf($field->item)] : [],
            'bool' => false,
            default => '',
        };
    }
}
