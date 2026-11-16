<?php

namespace Base\Admin\Filter;

use Base\Database\Repository\Finder\Criterion;
use Base\Database\Repository\Finder\Group;
use Base\Database\Repository\Finder\Key;
use Base\Database\Repository\Finder\Parser;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * One filter definition: which property, which comparison widget, and how
 * it constrains the list. Single class - the type string drives both the
 * rendering (inline bar) and the condition.
 *
 * The condition is a group of the core's finder language (match()), which the
 * persistence the site chose compiles, whatever keeps the records. A filter of
 * its own says its condition with matchWith().
 */
class Filter
{
    public const TYPE_TEXT = 'text';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_CHOICE = 'choice';
    public const TYPE_DATE = 'date';
    public const TYPE_NUMERIC = 'numeric';
    public const TYPE_ASSOCIATION = 'association';

    protected string $property;
    protected TranslatableInterface|string|null $label = null;
    protected string $type = self::TYPE_TEXT;
    /** @var array<string, string> value => label */
    protected array $choices = [];
    /** @var callable|null custom condition: fn(mixed $value): Criterion|Group|null */
    protected mixed $matchCallable = null;

    public static function new(string $property, TranslatableInterface|string|null $label = null): static
    {
        $filter = new static();
        $filter->property = $property;
        $filter->label = $label;

        return $filter;
    }

    public function getProperty(): string
    {
        return $this->property;
    }

    public function getLabel(): TranslatableInterface|string|null
    {
        return $this->label;
    }

    public function setLabel(TranslatableInterface|string|null $label): static
    {
        $this->label = $label;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function asBoolean(): static
    {
        return $this->setType(self::TYPE_BOOLEAN);
    }

    public function asDate(): static
    {
        return $this->setType(self::TYPE_DATE);
    }

    public function asNumeric(): static
    {
        return $this->setType(self::TYPE_NUMERIC);
    }

    /**
     * @param array<string, string> $choices value => label
     */
    public function asChoice(array $choices): static
    {
        $this->choices = $choices;
        return $this->setType(self::TYPE_CHOICE);
    }

    /**
     * Filter by a to-one association (Destination, Author, Tag, ...) - a
     * dropdown of the related entity's real rows rather than a free-text
     * guess at an id. $entities is typically `$repository->findAll()` (or
     * a narrower query - findBy(), a custom finder, ...); each becomes one
     * choice, keyed by id, labeled with $labelCallback($entity) or the
     * entity's own __toString() if none is given. The condition is the
     * association's key equal to the id chosen (Key::of(), its own column,
     * no join), so this is really "asChoice(), but the choices come from a
     * repository instead of a hand-written array".
     *
     * @param iterable<object> $entities
     */
    public function asAssociation(iterable $entities, ?callable $labelCallback = null): static
    {
        $choices = [];
        foreach ($entities as $entity) {
            if (!method_exists($entity, 'getId')) {
                continue;
            }
            $choices[(string) $entity->getId()] = null !== $labelCallback ? $labelCallback($entity) : (string) $entity;
        }

        $this->choices = $choices;
        return $this->setType(self::TYPE_ASSOCIATION);
    }

    public function getChoices(): array
    {
        return $this->choices;
    }

    /**
     * The filter's condition in the core's finder language: fn(mixed $value): Criterion|Group|null - the value as the
     * URL gives it (a {comparison, value} pair read as its value), a criterion (Criterion::of('roles',
     * '%'.$value.'%', Parser::OPTION_PARTIAL)) or a group (Group::of()), null for no condition.
     */
    public function matchWith(callable $matchCallable): static
    {
        $this->matchCallable = $matchCallable;
        return $this;
    }

    // comparison operators, historical URL shape:
    //   filters[prop][comparison]=like|eq|neq|gt|lt & filters[prop][value]=...
    // a bare scalar (filters[prop]=x) is still accepted and means the
    // type's default comparison, so simple links stay simple
    public const COMPARISON_LIKE = 'like';
    public const COMPARISON_EQ = 'eq';
    public const COMPARISON_NEQ = 'neq';
    public const COMPARISON_GT = 'gt';
    public const COMPARISON_LT = 'lt';

    /**
     * The comparison operators this filter's widget should offer, keyed by
     * operator, valued with a short display symbol. Empty = no chooser
     * (boolean/choice/association/date have a fixed semantic).
     *
     * @return array<string, string>
     */
    public function getComparisons(): array
    {
        return match ($this->type) {
            self::TYPE_TEXT => [self::COMPARISON_LIKE => '≈', self::COMPARISON_EQ => '=', self::COMPARISON_NEQ => '≠'],
            self::TYPE_NUMERIC => [self::COMPARISON_EQ => '=', self::COMPARISON_GT => '>', self::COMPARISON_LT => '<', self::COMPARISON_NEQ => '≠'],
            default => [],
        };
    }

    /**
     * The filter's condition as a group of the core's finder language: a boolean equal, a date between the first second of its "from" day and the last of its "to" day,
     * a number compared (=, >, <, !=), a choice equal, an association by its key (its own column, no join), a text
     * equal or not, else contained, whatever its case (LOWER(...) LIKE '%value%'). Null for no condition.
     *
     * @param mixed $value the raw request value (already checked non-empty)
     */
    public function match(mixed $value): ?Group
    {
        $comparison = null;
        if (\is_array($value) && \array_key_exists('value', $value)) {
            $comparison = $value['comparison'] ?? null;
            $value = $value['value'];
        }

        if (null !== $this->matchCallable) {
            $match = ($this->matchCallable)($value);

            return $match instanceof Criterion ? Group::of(Parser::SEPARATOR_AND, $match) : $match;
        }

        $property = $this->property;
        $members = match ($this->type) {
            self::TYPE_BOOLEAN => [Criterion::of($property, '1' === $value || 'true' === $value || true === $value)],
            self::TYPE_DATE => array_values(array_filter([
                \is_array($value) && ($value['from'] ?? null) ? Criterion::of($property, new \DateTimeImmutable($value['from'].' 00:00:00'), Parser::OPTION_GREATER_EQUAL) : null,
                \is_array($value) && ($value['to'] ?? null) ? Criterion::of($property, new \DateTimeImmutable($value['to'].' 23:59:59'), Parser::OPTION_LOWER_EQUAL) : null,
            ])),
            self::TYPE_NUMERIC => [Criterion::of($property, $value, match ($comparison) {
                self::COMPARISON_GT => Parser::OPTION_GREATER,
                self::COMPARISON_LT => Parser::OPTION_LOWER,
                self::COMPARISON_NEQ => Parser::OPTION_NOT_EQUAL,
                default => Parser::OPTION_EQUAL,
            })],
            self::TYPE_CHOICE => [Criterion::of($property, $value)],
            self::TYPE_ASSOCIATION => [Criterion::of($property, Key::of(\is_scalar($value) ? (string) $value : $value))],
            default => match ($comparison) {
                self::COMPARISON_EQ => [Criterion::of($property, $value)],
                self::COMPARISON_NEQ => [Criterion::of($property, $value, Parser::OPTION_NOT_EQUAL)],
                default => [Criterion::of($property, '%'.mb_strtolower((string) $value).'%', Parser::OPTION_INSENSITIVE, Parser::OPTION_PARTIAL)],
            },
        };

        return [] === $members ? null : Group::of(Parser::SEPARATOR_AND, ...$members);
    }

    /**
     * A filter value counts as "set" when it constrains anything.
     */
    public static function isActive(mixed $value): bool
    {
        if (\is_array($value)) {
            if (\array_key_exists('value', $value)) {
                return null !== $value['value'] && '' !== trim((string) $value['value']);
            }

            return '' !== trim((string) ($value['from'] ?? '')) || '' !== trim((string) ($value['to'] ?? ''));
        }

        return null !== $value && '' !== trim((string) $value);
    }
}
