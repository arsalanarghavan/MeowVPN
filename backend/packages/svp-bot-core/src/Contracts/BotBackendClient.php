<?php

namespace Svp\Bot\Core\Contracts;

interface BotBackendClient
{
    /**
     * @param  array<string, mixed>  $update
     * @return array<string, mixed>
     */
    public function processUpdate(string $platform, array $update, int $resellerSvpUserId = 0, bool $async = true): array;

    /**
     * @param  array<string, mixed>  $from
     * @return array<string, mixed>
     */
    public function resolveUser(string $platform, array $from, int $resellerSvpUserId = 0, bool $create = false, string $startText = ''): array;

    /**
     * @param  array<int, string>  $keys
     * @return array<string, string>
     */
    public function texts(array $keys, string $locale = 'fa'): array;

    /**
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    public function settings(array $keys): array;

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function mutate(int $actorSvpUserId, string $op, array $params = []): array;

    public function health(): array;
}
