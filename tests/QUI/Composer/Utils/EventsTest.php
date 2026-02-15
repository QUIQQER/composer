<?php

namespace QUITests\Composer\Utils;

use PHPUnit\Framework\TestCase;
use QUI\Composer\Utils\Events;
use QUI\ExceptionStack;

class EventsTest extends TestCase
{
    private static int $sum = 0;
    private static int $noArgCalls = 0;

    public static function addNumbers(int $a, int $b): int
    {
        return $a + $b;
    }

    public static function noArgValue(): int
    {
        self::$noArgCalls++;
        return 42;
    }

    public function testAddEventPreventsDuplicates(): void
    {
        $events = new Events();
        $callback = static function (): void {
        };

        $events->addEvent('onTest', $callback);
        $events->addEvent('onTest', $callback);

        $list = $events->getList();
        $this->assertCount(1, $list['onTest']);
    }

    public function testFireEventUsesPriorityOrder(): void
    {
        $events = new Events();
        $order = [];

        $events->addEvent('onRun', static function () use (&$order): void {
            $order[] = 'second';
        }, 10);

        $events->addEvent('onRun', static function () use (&$order): void {
            $order[] = 'first';
        }, 0);

        $events->fireEvent('run');

        $this->assertSame(['first', 'second'], $order);
    }

    public function testFireEventReturnsStringCallableResults(): void
    {
        $events = new Events();

        $events->addEvent('onCalc', self::class . '::addNumbers');
        $result = $events->fireEvent('calc', [2, 5]);

        $this->assertSame(7, $result[self::class . '::addNumbers']);
    }

    public function testRemoveEventAndRemoveEvents(): void
    {
        $events = new Events();
        $one = static function (): void {
        };
        $two = static function (): void {
        };

        $events->addEvent('onA', $one);
        $events->addEvent('onA', $two);
        $events->removeEvent('onA', $one);

        $list = $events->getList();
        $this->assertCount(1, $list['onA']);

        $events->removeEvents(['onA' => $two]);
        $list = $events->getList();
        $this->assertEmpty($list['onA']);
    }

    public function testAddEventsAndRemoveWholeEventType(): void
    {
        $events = new Events();
        $called = 0;

        $events->addEvents([
            'onBulk' => static function () use (&$called): void {
                $called++;
            }
        ]);

        $events->fireEvent('bulk');
        $this->assertSame(1, $called);

        $events->removeEvent('onBulk', false);
        $list = $events->getList();
        $this->assertArrayNotHasKey('onBulk', $list);
    }

    public function testNoopPathsForMissingEventAndForceFlag(): void
    {
        $events = new Events();

        $events->removeEvent('onMissing', false);
        $events->removeEvents(['onMissing' => false]);
        $this->assertSame([], $events->fireEvent('missing'));

        $counter = 0;
        $events->addEvent('onRecursion', function () use (&$counter, $events): void {
            $counter++;

            if ($counter < 2) {
                $events->fireEvent('recursion', false, true);
            }
        });

        $events->fireEvent('recursion');
        $this->assertSame(2, $counter);
    }

    public function testFireEventRecursionGuard(): void
    {
        $events = new Events();
        self::$sum = 0;

        $events->addEvent('onLoop', function () use ($events): void {
            self::$sum++;
            $events->fireEvent('loop');
        });

        $events->fireEvent('loop');
        $this->assertSame(1, self::$sum);
    }

    public function testFireEventThrowsExceptionStack(): void
    {
        $events = new Events();

        $events->addEvent('onFail', static function (): void {
            throw new \Exception('boom');
        });

        $this->expectException(ExceptionStack::class);
        $events->fireEvent('fail');
    }

    public function testFireEventCoversBoolArgsAndStringCallableBranches(): void
    {
        $events = new Events();
        $calls = 0;
        self::$noArgCalls = 0;

        $events->addEvent('onBoolArgs', static function () use (&$calls): void {
            $calls++;
        });

        // bool(true) triggers the "!is_array($args)" fallback branch for callable closures.
        $events->fireEvent('boolArgs', true);
        $this->assertSame(1, $calls);

        $events->addEvent('onStringNoArgs', self::class . '::noArgValue');
        $result = $events->fireEvent('stringNoArgs');
        $this->assertSame(42, $result[self::class . '::noArgValue']);

        // bool(true) triggers the "!is_array($args)" fallback branch for string callables.
        $result = $events->fireEvent('stringNoArgs', true);
        $this->assertSame(42, $result[self::class . '::noArgValue']);
        $this->assertSame(2, self::$noArgCalls);

        // Invalid string callback is skipped by is_callable guard.
        $events->addEvent('onInvalid', 'Not\\Callable::missingMethod');
        $this->assertSame([], $events->fireEvent('invalid'));
    }
}
