<?php
declare(strict_types=1);

namespace App\Core;

final class EventBus
{
    /** @var array<string, array<int, callable>> */
    private array $listeners = [];

    public function on(string $event, callable $listener): void
    {
        $this->listeners[$event][] = $listener;
    }

    public function emit(string $event, array $payload = []): void
    {
        foreach ($this->listeners[$event] ?? [] as $listener) {
            $listener($payload);
        }
    }
}
