<?php

namespace App\Services;

class MutationRegistry
{
    /** @var array<string, callable|array{0: class-string, 1: string}> */
    protected array $handlers = [];

    /** @param  callable|array{0: class-string, 1: string}  $handler */
    public function register(string $op, callable|array $handler): void
    {
        $this->handlers[$op] = $handler;
    }

    /** @param  array<string, callable|array{0: class-string, 1: string}>  $handlers */
    public function registerMany(array $handlers): void
    {
        foreach ($handlers as $op => $handler) {
            $this->register($op, $handler);
        }
    }

    public function has(string $op): bool
    {
        return isset($this->handlers[$op]);
    }

    /** @return array<string, callable|array{0: class-string, 1: string}> */
    public function all(): array
    {
        return $this->handlers;
    }
}
