<?php

namespace Base\Admin\Test;

use Base\Admin\Config\Actions;
use Base\Field\FieldDescriptor;
use PHPUnit\Framework\TestCase;

/**
 * What a bundle's back office screens ask, against what its records need -
 * extended by each bundle's own test, which names its source directory:
 *
 *   final class CrudRequiredFieldsTest extends CrudFieldsTestCase
 *   {
 *       protected static function sourceDirectory(): string { return __DIR__.'/../../src'; }
 *   }
 *
 * A field without setRequired(false) is required, and the browser does not
 * send a form with a required field left empty: the links, the frames, the
 * picture of a classroom card stopped every card. So, on the new and edit
 * forms of every CRUD controller under that directory:
 *
 * - a field is marked required only when the record cannot do without it: a
 *   column that is not nullable and has no default (none in the mapping, the
 *   property or the constructor), a join column that is not nullable, a
 *   NotBlank, NotNull or Count(min: 1) constraint - or the field a required
 *   slug is made from, a thread's title (what names it), or when the bundle
 *   says why its screen asks more (requiredByTheScreen());
 * - what the record cannot do without is marked required, else the field
 *   left empty reaches the database or a setter that refuses it;
 * - a column with a default may be either: the form shows that default;
 * - a field left optional takes an empty value: what an empty text, number
 *   or date sends is null, so its setter accepts null, or the field names
 *   what empty means (setEmptyData());
 * - one field per property (two fields on one property are one form field,
 *   the last), and a slug follows the field it is made from.
 *
 * A form the screen does not open (new or edit disabled) is not checked.
 * Read without a kernel or a database: the controllers are built without
 * their constructor (stubs for the services configureFields() reads), the
 * entities' mapping from their attributes (their own, their parents',
 * their traits', and their translation class's - Name + Intl). A browser-level
 * required is what is checked: the hidden inputs behind an editor, a
 * checkbox, a multiple choice and a collection never carry one.
 */
abstract class CrudFieldsTestCase extends TestCase
{
    /** The pages whose form is checked. */
    private const PAGES = [FieldDescriptor::PAGE_NEW, FieldDescriptor::PAGE_EDIT];

    /** Form types the browser never refuses to send empty (hidden inputs, checkboxes, wrappers). */
    private const NEVER_REQUIRED = [
        'Symfony\Component\Form\Extension\Core\Type\HiddenType',
        'Symfony\Component\Form\Extension\Core\Type\CollectionType',
        'Base\Field\Type\EditorType',
        'Base\Field\Type\WysiwygType',
        'Base\Field\Type\CollectionType',
        'Base\Field\Type\TranslationType',
        'Base\Field\Type\TranslatableType',
    ];

    /** Form types that are not required unless they say so (their own default). */
    private const OPTIONAL_BY_DEFAULT = [
        'Symfony\Component\Form\Extension\Core\Type\CheckboxType',
        'Base\Field\Type\BooleanType',
        'Base\Field\Type\DateTimePickerType',
        'Base\Field\Type\PasswordType',
        'Base\Field\Type\SlugType',
    ];

    /** Form types that never hand null to the record (a checkbox is false, a list is empty, a file is kept). */
    private const NEVER_NULL = [
        'Symfony\Component\Form\Extension\Core\Type\CheckboxType',
        'Base\Field\Type\BooleanType',
        'Base\Field\Type\FileType',
        'Base\Field\Type\ImageType',
        'Base\Field\Type\AvatarType',
        'Base\Field\Type\AudioType',
        'Base\Field\Type\VideoType',
        'Base\Field\Type\AssociationFileType',
        'Base\Field\Type\SlugType',
        'Base\Field\Type\TranslationType',
        'Base\Field\Type\TranslatableType',
        'Base\Field\Type\CollectionType',
        'Symfony\Component\Form\Extension\Core\Type\CollectionType',
    ];

    /** Choice types, multiple when the property is a list. */
    private const CHOICES = [
        'Base\Field\Type\SelectType',
        'Base\Field\Type\AssociationType',
        'Base\Field\Type\AttributeType',
    ];

    /** Accessor suffixes over a mapped property: periodValue, linksText, statusName... */
    private const SUFFIXES = ['AsText', 'Value', 'Text', 'Name', 'Label'];

    /** @var array<class-string, array<string, array{class-string, class-string, list<FieldDescriptor>}>> the screens read, by test */
    private static array $screens = [];

    /** @var array<class-string, list<string>> the screens that could not be read, and why, by test */
    private static array $unread = [];

    /** The directory holding the bundle's classes. */
    abstract protected static function sourceDirectory(): string;

    /**
     * Fields the screen requires though the record may leave them empty, and
     * why: controller class => [property => reason].
     *
     * @return array<class-string, array<string, string>>
     */
    protected static function requiredByTheScreen(): array
    {
        return [];
    }

    /**
     * Fields the record needs that the screen leaves optional, and who fills
     * them when left empty: controller class => [property => reason].
     *
     * @return array<class-string, array<string, string>>
     */
    protected static function filledByTheScreen(): array
    {
        return [];
    }

    /**
     * Slugs typed as they are, following no field, and why: controller class
     * => [property => reason] (a key such as a setting's path, not made from a
     * title).
     *
     * @return array<class-string, array<string, string>>
     */
    protected static function typedSlugs(): array
    {
        return [];
    }

    /**
     * Controllers left out, and why: one that cannot be read here (a class of
     * an optional package its entity needs, absent from this installation).
     *
     * @return array<class-string, string>
     */
    protected static function skipped(): array
    {
        return [];
    }

    /**
     * What a controller's configureFields() reads, by property name, where a
     * stub does not do (a translator whose catalogue is read, the site's
     * languages...).
     *
     * @return array<string, mixed>
     */
    protected function services(): array
    {
        return [];
    }

    public function testEveryScreenIsRead(): void
    {
        $this->screens();
        self::assertSame([], self::$unread[static::class] ?? [], 'every screen is read (skipped() names those that cannot be, and why; services() gives what a stub cannot)');
        if ([] !== $skipped = static::skipped()) {
            self::markTestIncomplete('not read here: '.implode('; ', array_map(fn ($controller, $why) => $controller.' ('.$why.')', array_keys($skipped), $skipped)));
        }
    }

    public function testOnlyWhatTheRecordNeedsIsRequired(): void
    {
        $problems = [];
        foreach ($this->screens() as $screen => [$controller, $entity, $fields]) {
            $reasons = static::requiredByTheScreen()[$controller] ?? [];
            // A thread is named by its title (lists, pickers, its slug read it), whatever its translations leave empty.
            if (is_a($entity, 'Base\Entity\Thread', true)) {
                $reasons['title'] ??= 'a thread is named by its title';
            }
            // What a slug is made from may be required: the slug is, and follows it as it is typed.
            foreach ($fields as $field) {
                if ('Base\Field\SlugField' === $field->getFieldFqcn() && \is_string($target = $field->getFormTypeOptions()['target'] ?? null)) {
                    $reasons[$target] ??= 'the slug follows it';
                }
            }
            foreach ($fields as $field) {
                // Left optional, a field whose setter refuses null breaks the saving: required is its only safe state.
                if (!$this->isMarkedRequired($entity, $field) || isset($reasons[$field->getProperty()]) || null !== $this->refusal($entity, $field)) {
                    continue;
                }
                $need = self::need($entity, $field->getProperty());
                if (\in_array($need['kind'], ['optional', 'list'], true)) {
                    $problems[] = $screen.': '.$field->getProperty().' is required, the record may leave it empty ('.$need['why'].')';
                }
            }
        }
        self::assertSame([], $problems, 'left empty, these would stop the browser: setRequired(false), or requiredByTheScreen() says why');
    }

    public function testWhatTheRecordNeedsIsRequired(): void
    {
        $problems = [];
        foreach ($this->screens() as $screen => [$controller, $entity, $fields]) {
            $filled = static::filledByTheScreen()[$controller] ?? [];
            foreach ($fields as $field) {
                $type = $field->getFormType() ?? '';
                if (isset($filled[$field->getProperty()]) || $this->isDisabled($field) || $this->isAny($type, self::NEVER_REQUIRED) || $this->isAny($type, self::OPTIONAL_BY_DEFAULT)) {
                    continue;
                }
                $need = self::need($entity, $field->getProperty());
                if ('needed' === $need['kind'] && !$this->isMarkedRequired($entity, $field)) {
                    $problems[] = $screen.': '.$field->getProperty().' is optional, the record needs it ('.$need['why'].')';
                }
            }
        }
        self::assertSame([], $problems, 'left empty, these would reach the database: required, or filledByTheScreen() says who fills them');
    }

    public function testAnOptionalFieldTakesAnEmptyValue(): void
    {
        $problems = [];
        foreach ($this->screens() as $screen => [, $entity, $fields]) {
            foreach ($fields as $field) {
                if (!$this->isDisabled($field) && !$this->isMarkedRequired($entity, $field) && null !== $refusal = $this->refusal($entity, $field)) {
                    $problems[] = $screen.': '.$field->getProperty().' may be left empty, '.$refusal.' refuses null';
                }
            }
        }
        self::assertSame([], $problems, 'an empty text, number or date sends null: the setter accepts it, the field says what empty is (setEmptyData()), or it is required');
    }

    public function testOneFieldPerProperty(): void
    {
        $problems = [];
        foreach ($this->screens() as $screen => [, , $fields]) {
            $names = array_map(fn (FieldDescriptor $field) => $field->getPropertyWithSuffix(), $fields);
            foreach (array_unique(array_diff_assoc($names, array_unique($names))) as $twice) {
                $problems[] = $screen.': '.$twice;
            }
        }
        self::assertSame([], $problems, 'two fields on one property are one form field, the last');
    }

    public function testTheSlugFollowsAField(): void
    {
        $problems = [];
        $directory = realpath(static::sourceDirectory()).\DIRECTORY_SEPARATOR;
        foreach ($this->screens() as $screen => [$controller, , $fields]) {
            // The screens this bundle declares: one inherited (omnibase's taxa) is its package's to answer for.
            if (!str_starts_with((string) (new \ReflectionMethod($controller, 'configureFields'))->getFileName(), $directory)) {
                continue;
            }
            $properties = array_map(fn (FieldDescriptor $field) => $field->getProperty(), $fields);
            foreach ($fields as $field) {
                if ('Base\Field\SlugField' !== $field->getFieldFqcn()) {
                    continue;
                }
                $target = $field->getFormTypeOptions()['target'] ?? null;
                if (null === $target && isset(static::typedSlugs()[$controller][$field->getProperty()])) {
                    continue;
                }
                if (null === $target) {
                    $problems[] = $screen.': '.$field->getProperty().' follows nothing';
                } elseif (!\in_array(explode('.', ltrim($target, '.'))[0], $properties, true)) {
                    $problems[] = $screen.': '.$field->getProperty().' follows '.$target.', not on the form';
                }
            }
        }
        self::assertSame([], $problems, 'a slug without setTargetFieldName() stays empty as the title is typed');
    }

    /**
     * Every CRUD controller's new and edit forms: "Controller page" =>
     * [controller, entity, the fields the form holds].
     *
     * @return array<string, array{class-string, class-string, list<FieldDescriptor>}>
     */
    protected function screens(): array
    {
        if (isset(self::$screens[static::class])) {
            return self::$screens[static::class];
        }
        $directory = realpath(static::sourceDirectory());

        $found = [];
        $unread = [];
        foreach (self::controllers($directory) as $controller) {
            if (isset(static::skipped()[$controller])) {
                continue;
            }
            $crud = (new \ReflectionClass($controller))->newInstanceWithoutConstructor();
            $this->giveServices($crud);
            $actions = self::actions($crud);
            foreach (self::PAGES as $page) {
                // A form the screen never opens (records made by the site, shown only) asks nothing.
                if ($actions?->isDisabled(Actions::PAGE_INDEX, $page)) {
                    continue;
                }
                try {
                    $fields = [];
                    foreach ($crud->configureFields($page) as $field) {
                        $descriptor = $field instanceof FieldDescriptor ? $field : $field->getAsDto();
                        if ($descriptor->isDisplayedOn($page)) {
                            $fields[] = $descriptor;
                        }
                    }
                } catch (\Throwable $e) {
                    $unread[] = $controller.' '.$page.': '.$e->getMessage();
                    continue;
                }
                $found[substr(strrchr($controller, '\\'), 1).' '.$page] = [$controller, $controller::getEntityFqcn(), $fields];
            }
        }
        self::assertNotEmpty($found, 'CRUD controllers under '.$directory);
        self::$unread[static::class] = $unread;

        return self::$screens[static::class] = $found;
    }

    /** The controller's actions, null when they cannot be read without the application. */
    private static function actions(object $crud): ?Actions
    {
        try {
            return $crud->configureActions(Actions::new());
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return list<class-string> the concrete CRUD controllers under the directory */
    private static function controllers(string $directory): array
    {
        $controllers = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if (!str_ends_with($file->getFilename(), 'CrudController.php')) {
                continue;
            }
            $code = (string) file_get_contents($file->getPathname());
            if (!preg_match('/^namespace\s+([^;]+);/m', $code, $namespace) || !preg_match('/^(?:final\s+|abstract\s+|readonly\s+)*class\s+(\w+)/m', $code, $class)) {
                continue;
            }
            $controller = $namespace[1].'\\'.$class[1];
            $reflection = new \ReflectionClass($controller);
            if (!$reflection->isAbstract() && $reflection->hasMethod('getEntityFqcn')) {
                $controllers[] = $controller;
            }
        }
        sort($controllers);

        return $controllers;
    }

    /** Stubs for the services a controller's properties hold, those configureFields() may read. */
    private function giveServices(object $crud): void
    {
        $services = $this->services();
        for ($class = new \ReflectionClass($crud); $class; $class = $class->getParentClass()) {
            foreach ($class->getProperties() as $property) {
                if ($property->isStatic() || $property->getDeclaringClass()->getName() !== $class->getName() || $property->isInitialized($crud)) {
                    continue;
                }
                if (\array_key_exists($property->getName(), $services)) {
                    $property->setValue($crud, $services[$property->getName()]);
                    continue;
                }
                $type = $property->getType();
                if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                    continue;
                }
                try {
                    $property->setValue($crud, $this->createStub($type->getName()));
                } catch (\Throwable) {
                    // a final class: left out, a screen reading it says so
                }
            }
        }
    }

    private function isDisabled(FieldDescriptor $field): bool
    {
        return true === $field->isDisabled() || true === ($field->getFormTypeOptions()['disabled'] ?? false);
    }

    /** Whether the browser refuses to send the form with this field empty. */
    private function isMarkedRequired(string $entity, FieldDescriptor $field): bool
    {
        $type = $field->getFormType() ?? '';
        $options = $field->getFormTypeOptions();
        if ($this->isDisabled($field) || $this->isAny($type, self::NEVER_REQUIRED)) {
            return false;
        }
        if ($this->isAny($type, self::CHOICES) && $this->isMultiple($entity, $field) && !($options['required_when_multiple'] ?? false)) {
            return false;
        }
        if ('Base\Field\Type\SelectType' === $type && ($options['tags'] ?? false) && null === ($options['required'] ?? $field->isRequired())) {
            return false;
        }

        return (bool) ($options['required'] ?? $field->isRequired() ?? !$this->isAny($type, self::OPTIONAL_BY_DEFAULT));
    }

    private function isMultiple(string $entity, FieldDescriptor $field): bool
    {
        $multiple = $field->getFormTypeOptions()['multiple'] ?? null;

        return $multiple ?? 'list' === self::need($entity, $field->getProperty())['kind'];
    }

    private function neverSendsNull(string $entity, FieldDescriptor $field): bool
    {
        $type = $field->getFormType() ?? '';
        if ($this->isAny($type, self::NEVER_NULL) || $this->isAny($type, self::CHOICES) && $this->isMultiple($entity, $field)) {
            return true;
        }

        return null !== $field->getEmptyData() || null !== ($field->getFormTypeOptions()['empty_data'] ?? null);
    }

    /** The setter that would be handed null were the field left empty ("Class::setX()"), null when none would. */
    private function refusal(string $entity, FieldDescriptor $field): ?string
    {
        if ($this->neverSendsNull($entity, $field) || null === $setter = self::setter($entity, $field->getProperty())) {
            return null;    // no setter: written another way (a public property, a form event), not this test's to judge
        }
        $parameter = $setter->getParameters()[0] ?? null;

        return null === $parameter || $parameter->allowsNull() || $parameter->isDefaultValueAvailable() ? null : $setter->class.'::'.$setter->name.'()';
    }

    /** @param list<string> $types */
    private function isAny(string $type, array $types): bool
    {
        foreach ($types as $candidate) {
            if (is_a($type, $candidate, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * What the record asks of a property: needed (no value, no default, or a
     * constraint), defaulted (not nullable, with a default), optional, or a
     * list (to-many) - and why.
     *
     * @return array{kind: 'needed'|'defaulted'|'optional'|'list', why: string}
     */
    protected static function need(string $entity, string $property): array
    {
        static $needs = [];
        if (isset($needs[$entity][$property])) {
            return $needs[$entity][$property];
        }

        $found = self::mappedProperty($entity, $property);
        if (null === $found) {
            return $needs[$entity][$property] = ['kind' => 'optional', 'why' => 'not mapped'];
        }
        [$class, $reflection] = $found;
        $attributes = self::attributes($reflection);
        $named = $reflection->getName() !== $property ? $reflection->getName().': ' : '';

        foreach (['NotBlank', 'NotNull'] as $constraint) {
            if (isset($attributes[$constraint])) {
                return $needs[$entity][$property] = ['kind' => 'needed', 'why' => $named.$constraint];
            }
        }
        foreach ($attributes['Count'] ?? [] as $arguments) {
            if (($arguments['min'] ?? $arguments[0]['min'] ?? 0) >= 1) {
                return $needs[$entity][$property] = ['kind' => 'needed', 'why' => $named.'Count(min: 1)'];
            }
        }
        if (isset($attributes['ManyToMany']) || isset($attributes['OneToMany'])) {
            return $needs[$entity][$property] = ['kind' => 'list', 'why' => $named.'a list'];
        }
        if (isset($attributes['ManyToOne']) || isset($attributes['OneToOne'])) {
            $nullable = $attributes['JoinColumn'][0]['nullable'] ?? true;

            return $needs[$entity][$property] = false === $nullable
                ? ['kind' => 'needed', 'why' => $named.'join column not nullable']
                : ['kind' => 'optional', 'why' => $named.'join column nullable'];
        }

        $column = $attributes['Column'][0] ?? [];
        if (\in_array($column['type'] ?? null, ['json', 'array', 'simple_array'], true) && !($column['nullable'] ?? false)) {
            return $needs[$entity][$property] = ['kind' => 'list', 'why' => $named.'a list (json)'];
        }
        if ($column['nullable'] ?? false) {
            return $needs[$entity][$property] = ['kind' => 'optional', 'why' => $named.'column nullable'];
        }
        if (isset($attributes['Id'])) {
            return $needs[$entity][$property] = ['kind' => 'defaulted', 'why' => $named.'an identifier'];
        }
        if (isset($column['options']['default'])) {
            return $needs[$entity][$property] = ['kind' => 'defaulted', 'why' => $named.'default in the mapping'];
        }
        if ($reflection->hasDefaultValue() && null !== $reflection->getDefaultValue()) {
            return $needs[$entity][$property] = ['kind' => 'defaulted', 'why' => $named.'default on the property'];
        }
        if ($class === $entity && self::constructed($entity, $reflection)) {
            return $needs[$entity][$property] = ['kind' => 'defaulted', 'why' => $named.'set by the constructor'];
        }

        return $needs[$entity][$property] = ['kind' => 'needed', 'why' => $named.'column not nullable, no default'];
    }

    /**
     * The mapped property behind a field: the entity's (its parents', its
     * traits'), its translation class's, or the one an accessor stands for
     * (periodValue, linksText, statusName...).
     *
     * @return array{class-string, \ReflectionProperty}|null
     */
    private static function mappedProperty(string $entity, string $property): ?array
    {
        $names = [$property];
        foreach (self::SUFFIXES as $suffix) {
            if (str_ends_with($property, $suffix) && \strlen($property) > \strlen($suffix)) {
                $names[] = substr($property, 0, -\strlen($suffix));
            }
        }
        $classes = [$entity];
        for ($class = $entity; $class; $class = get_parent_class($class)) {
            if (class_exists($class.'Intl')) {
                $classes[] = $class.'Intl';
            }
        }
        foreach ($names as $name) {
            foreach ($classes as $class) {
                $reflection = self::property($class, $name);
                if (null === $reflection) {
                    continue;
                }
                $attributes = self::attributes($reflection);
                foreach (['Column', 'ManyToOne', 'OneToOne', 'ManyToMany', 'OneToMany', 'Embedded', 'NotBlank', 'NotNull'] as $mapping) {
                    if (isset($attributes[$mapping])) {
                        return [$class, $reflection];
                    }
                }
            }
        }

        return null;
    }

    private static function property(string $class, string $name): ?\ReflectionProperty
    {
        for ($reflection = new \ReflectionClass($class); $reflection; $reflection = $reflection->getParentClass()) {
            if ($reflection->hasProperty($name)) {
                return $reflection->getProperty($name);
            }
        }

        return null;
    }

    /** @return array<string, list<array<mixed>>> the attributes by short name, their arguments */
    private static function attributes(\ReflectionProperty $property): array
    {
        $attributes = [];
        foreach ($property->getAttributes() as $attribute) {
            $attributes[substr(strrchr('\\'.$attribute->getName(), '\\'), 1)][] = $attribute->getArguments();
        }

        return $attributes;
    }

    /** Whether a record built with no argument holds a value there. */
    private static function constructed(string $entity, \ReflectionProperty $property): bool
    {
        static $records = [];
        if (!\array_key_exists($entity, $records)) {
            $records[$entity] = null;
            $constructor = (new \ReflectionClass($entity))->getConstructor();
            if (null === $constructor || 0 === $constructor->getNumberOfRequiredParameters()) {
                try {
                    $records[$entity] = new $entity();
                } catch (\Throwable) {
                    // built with services: what it sets is not known here
                }
            }
        }
        $record = $records[$entity];

        return null !== $record && $property->isInitialized($record) && null !== $property->getValue($record);
    }

    /** The method a form writes the property through: the entity's setter, or its translation's. */
    private static function setter(string $entity, string $property): ?\ReflectionMethod
    {
        $method = 'set'.ucfirst($property);
        if (method_exists($entity, $method)) {
            return new \ReflectionMethod($entity, $method);
        }
        for ($class = $entity; $class; $class = get_parent_class($class)) {
            if (class_exists($class.'Intl') && method_exists($class.'Intl', $method)) {
                return new \ReflectionMethod($class.'Intl', $method);
            }
        }

        return null;
    }
}
