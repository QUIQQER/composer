<?php

/**
 * This file contains \QUI\Composer\Utils\Events
 */

namespace QUI\Composer\Utils;

use Exception;
use QUI;

use function call_user_func;
use function call_user_func_array;
use function is_callable;
use function is_string;
use function preg_replace;
use function ucfirst;
use function usort;

/**
 * Events Handling
 * Extends a class with the events interface
 */
class Events
{
    /**
     * Registered events
     *
     * @var array<string, array<int, array{callable: callable, priority: int}>>
     */
    protected array $events = [];

    /**
     * @var array<string, bool>
     */
    protected array $currentRunning = [];

    /**
     * @return array<string, array<int, array{callable: callable, priority: int}>>
     */
    public function getList(): array
    {
        return $this->events;
    }

    /**
     * @param string $event - The type of event (e.g. 'complete').
     * @param callable $fn - The function to execute.
     * @param int $priority - optional, Priority of the event
     * @see QUI\Interfaces\Events::addEvent
     *
     */
    public function addEvent(string $event, callable $fn, int $priority = 0): void
    {
        if (!isset($this->events[$event])) {
            $this->events[$event] = [];
        }

        // don't add double events
        foreach ($this->events[$event] as $params) {
            if ($params['callable'] == $fn) {
                return;
            }
        }

        $this->events[$event][] = [
            'callable' => $fn,
            'priority' => $priority
        ];
    }

    /**
     * @param array<string, callable> $events
     * @see QUI\Interfaces\Events::addEvents
     *
     */
    public function addEvents(array $events): void
    {
        foreach ($events as $event => $fn) {
            $this->addEvent($event, $fn);
        }
    }

    /**
     * @param string $event - The type of event (e.g. 'complete').
     * @param callable|boolean $fn - (optional) The function to remove.
     * @see QUI\Interfaces\Events::removeEvent
     *
     */
    public function removeEvent(string $event, callable | bool $fn = false): void
    {
        if (!isset($this->events[$event])) {
            return;
        }

        if (!$fn) {
            unset($this->events[$event]);

            return;
        }

        foreach ($this->events[$event] as $k => $_fn) {
            if ($_fn['callable'] == $fn) {
                unset($this->events[$event][$k]);
            }
        }
    }

    /**
     * @param array<string, callable|bool> $events - (optional) If not passed, removes all events of all types.
     * @see QUI\Interfaces\Events::removeEvents
     *
     */
    public function removeEvents(array $events): void
    {
        foreach ($events as $event => $fn) {
            $this->removeEvent($event, $fn);
        }
    }

    /**
     * @param string $event - The type of event (e.g. 'onComplete').
     * @param bool|array<int, mixed> $args - (optional) the argument(s) to pass to the function.
     *                            The arguments must be in an array.
     * @param bool $force - (optional) no recursion check, optional, default = false
     *
     * @return array<string, mixed> - Event results, associative array
     *
     * @throws QUI\ExceptionStack
     * @see QUI\Interfaces\Events::fireEvent
     *
     */
    public function fireEvent(string $event, bool | array $args = false, bool $force = false): array
    {
        $results = [];

        if (!str_starts_with($event, 'on')) {
            $event = 'on' . ucfirst($event);
        }


        // recursion check
        if (
            isset($this->currentRunning[$event])
            && $this->currentRunning[$event]
            && $force === false
        ) {
            return $results;
        }

        if (!isset($this->events[$event])) {
            return $results;
        }

        $this->currentRunning[$event] = true;

        $Stack = new QUI\ExceptionStack();
        $events = $this->events[$event];

        // sort
        usort($events, function (array $a, array $b) {
            if ($a['priority'] == $b['priority']) {
                return 0;
            }

            return $a['priority'] < $b['priority'] ? -1 : 1;
        });

        // execute events
        foreach ($events as $data) {
            $fn = $data['callable'];

            try {
                if (!is_string($fn)) {
                    if ($args === false) {
                        $fn();
                        continue;
                    }

                    if (!is_array($args)) {
                        $fn();
                        continue;
                    }

                    call_user_func_array($fn, $args);
                    continue;
                }

                $fn = preg_replace('/\\{2,}/', '\\', $fn);

                if ($fn === null) {
                    continue;
                }

                if (!is_callable($fn)) {
                    continue;
                }

                if ($args === false) {
                    $results[$fn] = call_user_func($fn);
                    continue;
                }

                if (!is_array($args)) {
                    $results[$fn] = call_user_func($fn);
                    continue;
                }

                $results[$fn] = call_user_func_array($fn, $args);
            } catch (Exception $Exception) {
                $message = $Exception->getMessage();

                if (is_string($fn)) {
                    $message .= ' :: ' . $fn;
                }

                $Clone = new QUI\Exception(
                    $message,
                    $Exception->getCode(),
                    ['trace' => $Exception->getTraceAsString()]
                );

                $Stack->addException($Clone);
            }
        }

        $this->currentRunning[$event] = false;

        if (!$Stack->isEmpty()) {
            throw $Stack;
        }

        return $results;
    }
}
