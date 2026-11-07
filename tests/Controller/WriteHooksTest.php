<?php

namespace Tests\Base\Admin\Controller;

use Base\Admin\Controller\AbstractCrudController;
use Base\Database\Repository\Repositories;
use Doctrine\ORM\EntityManagerInterface;
use Omnibridge\Persistence\UnitOfWork;
use PHPUnit\Framework\TestCase;

/**
 * The CRUD's writes (omnibase's plan "omnibase agnostic", step B1a): through the persistence the site chose
 * (persistRecord(), updateRecord(), deleteRecord(), removeRecord() on omnisense/omnibridge's UnitOfWork), unless the
 * controller still overrides a former hook (persistEntity()...), which is then called with Doctrine's entity manager as
 * before - and a class that defines both is given the new one.
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
        $this->wire($controller, $entityManager = $this->createMock(EntityManagerInterface::class), $unitOfWork = $this->unitOfWork());
        $entityManager->expects(self::never())->method(self::anything());

        $this->write($controller, 'persist', $entity);
        $this->write($controller, 'update', $entity);
        $this->write($controller, 'delete', $entity);
        $controller->removeRecord($unitOfWork, $entity);

        self::assertSame(['persist', 'flush', 'flush', 'remove', 'flush', 'remove'], self::$calls);
    }

    public function testAFormerHookOverriddenIsStillCalledWithTheEntityManager(): void
    {
        $controller = new WriteHooksFormerController();
        $entity = new \stdClass();
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush')->willReturnCallback(static function (): void { self::$calls[] = 'em flush'; });
        $entityManager->expects(self::once())->method('remove')->willReturnCallback(static function (): void { self::$calls[] = 'em remove'; });
        $this->wire($controller, $entityManager, $unitOfWork = $this->unitOfWork());

        $this->write($controller, 'update', $entity);
        // removeEntity() overridden: the batch delete's removeRecord() runs it too.
        $controller->removeRecord($unitOfWork, $entity);
        // persist is not overridden: the unit of work's.
        $this->write($controller, 'persist', $entity);

        self::assertSame(['former update', 'em flush', 'former remove', 'em remove', 'persist', 'flush'], self::$calls);
    }

    public function testTheNewHookWinsWhereAClassDefinesBoth(): void
    {
        $controller = new WriteHooksBothController();
        $this->wire($controller, $entityManager = $this->createMock(EntityManagerInterface::class), $this->unitOfWork());
        $entityManager->expects(self::never())->method(self::anything());

        $this->write($controller, 'update', new \stdClass());

        self::assertSame(['new update', 'flush'], self::$calls);
    }

    public function testAFormerHookBelowANewOneIsTheOneCalled(): void
    {
        // A site's controller overriding the former hook, under a bundle's that moved to the new one.
        $controller = new WriteHooksFormerBelowNewController();
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('flush')->willReturnCallback(static function (): void { self::$calls[] = 'em flush'; });
        $this->wire($controller, $entityManager, $this->unitOfWork());

        $this->write($controller, 'update', new \stdClass());

        self::assertSame(['former update', 'em flush'], self::$calls);
    }

    private function unitOfWork(): UnitOfWork
    {
        $unitOfWork = $this->createMock(UnitOfWork::class);
        foreach (['persist', 'remove', 'flush'] as $method) {
            $unitOfWork->method($method)->willReturnCallback(static function () use ($method): void { self::$calls[] = $method; });
        }

        return $unitOfWork;
    }

    private function wire(AbstractCrudController $controller, EntityManagerInterface $entityManager, UnitOfWork $unitOfWork): void
    {
        (new \ReflectionProperty(AbstractCrudController::class, 'entityManager'))->setValue($controller, $entityManager);
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

class WriteHooksFormerController extends WriteHooksPlainController
{
    public function updateEntity(EntityManagerInterface $entityManager, object $entity): void
    {
        WriteHooksTest::call('former update');
        parent::updateEntity($entityManager, $entity);
    }

    public function removeEntity(EntityManagerInterface $entityManager, object $entity): void
    {
        WriteHooksTest::call('former remove');
        parent::removeEntity($entityManager, $entity);
    }
}

class WriteHooksBothController extends WriteHooksPlainController
{
    public function updateRecord(UnitOfWork $unitOfWork, object $entity): void
    {
        WriteHooksTest::call('new update');
        parent::updateRecord($unitOfWork, $entity);
    }

    public function updateEntity(EntityManagerInterface $entityManager, object $entity): void
    {
        WriteHooksTest::call('former update');
        parent::updateEntity($entityManager, $entity);
    }
}

class WriteHooksNewController extends WriteHooksPlainController
{
    public function updateRecord(UnitOfWork $unitOfWork, object $entity): void
    {
        WriteHooksTest::call('new update');
        parent::updateRecord($unitOfWork, $entity);
    }
}

class WriteHooksFormerBelowNewController extends WriteHooksNewController
{
    public function updateEntity(EntityManagerInterface $entityManager, object $entity): void
    {
        WriteHooksTest::call('former update');
        parent::updateEntity($entityManager, $entity);
    }
}
