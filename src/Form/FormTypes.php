<?php

namespace Base\Admin\Form;

/**
 * A form type named in a string, compared with a class: the same type under
 * any of its names (a class and an alias of it), with or without its leading
 * backslash, so a plain `===` on one spelling does not miss another.
 *
 * @internal
 */
final class FormTypes
{
    private function __construct()
    {
    }

    /** Whether $type names $class (one of its names, or the class itself). */
    public static function same(?string $type, string $class): bool
    {
        if (null === $type) {
            return false;
        }
        $type = ltrim($type, '\\');
        $class = ltrim($class, '\\');
        if ($type === $class) {
            return true;
        }

        return class_exists($type) && class_exists($class)
            && (new \ReflectionClass($type))->getName() === (new \ReflectionClass($class))->getName();
    }
}
