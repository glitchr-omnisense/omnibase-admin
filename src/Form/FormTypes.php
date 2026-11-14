<?php

namespace Base\Admin\Form;

/**
 * A form type named in a string, compared with a class: the same type under
 * either of its names. omnibridge/symfony's form types were the core's
 * (Base\Field\Type\* is Omnibridge\Symfony\Omnibase\Field\Type\* until
 * omnisense/omnibase 6.0): a field's default type is still given by its former
 * name (omnibridge/symfony 1.x's SymfonyKinds), a bundle's settings may name
 * either, so a plain `===` on one name misses the other.
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
