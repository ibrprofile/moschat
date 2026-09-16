<?php

declare(strict_types=1);

namespace MosChat\Channels;

final class ChannelRegistry
{
    /** @var array<string, ChannelInterface> */
    private static array $channels = [];

    private static bool $booted = false;

    public static function register(ChannelInterface $channel): void
    {
        self::$channels[$channel->key()] = $channel;
    }

    public static function get(string $key): ChannelInterface
    {
        self::boot();
        if (!isset(self::$channels[$key])) {
            throw new \RuntimeException('Канал не зарегистрирован: ' . $key);
        }
        return self::$channels[$key];
    }

    public static function has(string $key): bool
    {
        self::boot();
        return isset(self::$channels[$key]);
    }

    /** @return list<string> */
    public static function keys(): array
    {
        self::boot();
        return array_keys(self::$channels);
    }

    private static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;
        self::register(new WebsiteChannel());
    }
}
