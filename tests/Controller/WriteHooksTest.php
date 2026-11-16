<?php

namespace Tests\Base\Admin\Controller;

use Base\Admin\Controller\AbstractCrudController;
use Base\Database\Repository\Repositories;
use Base\Contract\UnitOfWork;
use PHPUnit\Framework\TestCase;

/**
 * The CRUD's writes go through the persistence the site chose (persistRecord(), updateRecord(), deleteRecord(),
 * removeRecord() on omnisense/omnibridge's UnitOfWork), an override of one of them is the one called, and nothing of an
 * ORM's entity manager is left on the controller.
 */
class WriteHooksTest extends TestCase
{
    /** @var list<string> */
    private static array $calls = [];

    protected function setUp(): void
    {
        self::$calls = [];
    }

    public function testTheWritesGoThroughTheUnitOfWorkChosen(): void
    {
        $controller = new WriteHooksPlainController();
        $entity = new \stdClass();
        $this->wire($controller, $unitOfWork = $this->unitOfWork());

        $this->write($controller, 'persist', $entity);
        $this->write($controller, 'update', $entity);
        $this->write($controller, 'delete', $entity);
        $controller->removeRecord($unitOfWork, $entity);

        self::assertSame(['persist', 'flush', 'flush', 'remove', 'flush', 'remove'], self::$calls);
    }

    public function testAnOverriddenHookIsTheOneCalled(): void
    {
        $controller = new WriteHooksOwnController();
        $this->wire($controller, $unitOfWork = $this->unitOfWork());

        $this->write($controller, 'update', new \stdClass());
        // removeRecord() overridden: the single delete runs it too.
        $this->write($controller, 'delete', new \stdClass());

        self::assertSame(['own update', 'flush', 'own remove', 'remove', 'flush'], self::$calls);
    }

    public function testNoEntityManagerIsLeftOnTheController(): void
    {
        self::assertFalse(property_exists(AbstractCrudController::class, 'entityManager'));
        foreach (['persistEntity', 'updateEntity', 'deleteEntity', 'removeEntity', 'createIndexQueryBuilder', 'applySearch', 'applyFilters'] as $method) {
            self::assertFalse(method_exists(AbstractCrudController::class, $method), $method);
        }
    }

    private function unitOfWork(): UnitOfWork
    {
        $unitOfWork = $this->createMock(UnitOfWork::class);
        foreach (['persist', 'remove', 'flush'] as $method) {
            $unitOfWork->method($method)->willReturnCallback(static function () use ($method): void { self::$calls[] = $method; });
        }

        return $unitOfWork;
    }

    private function wire(AbstractCrudController $controller, UnitOfWork $unitOfWork): void
    {
        $controller->setPersistence($unitOfWork, $this->createMock(Repositories::class));
    }

    private function write(AbstractCrudController $controller, string $hook, object $entity): void
    {
        (new \ReflectionMethod(AbstractCrudController::class, 'write'))->invoke($controller, $hook, $entity);
    }

    public static function call(string $call): void
    {
        self::$calls[] = $call;
    }
}

class WriteHooksPlainController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return \stdClass::class;
    }
}

class WriteHooksOwnController extends WriteHooksPlainController
{
    public function updateRecord(UnitOfWork $unitOfWork, object $entity): void
    {
        WriteHooksTest::call('own update');
        parent::updateRecord($unitOfWork, $entity);
    }

    public function removeRecord(UnitOfWork $unitOfWork, object $entity): void
    {
        WriteHooksTest::call('own remove');
        parent::removeRecord($unitOfWork, $entity);
    }
}
