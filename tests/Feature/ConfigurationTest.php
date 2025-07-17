<?php

use Illuminate\Support\Facades\Config;

beforeEach(function () {
    // Reset config before each test
    Config::set('users-online', [
        'default_duration' => 300,
        'cache_prefix'     => 'UserOnline',
        'cache_store'      => null,
        'user_fields'      => ['id', 'name', 'email'],
        'auto_cleanup'     => true,
    ]);
});

describe('Configuration Management', function () {

    it('uses default configuration values when not set', function () {
        $user = makeUser();

        expect($user->getCacheKey())->toBe('UserOnline-1');
    });

    it('respects custom cache prefix from config', function () {
        Config::set('users-online.cache_prefix', 'CustomPrefix');

        $user = makeUser();

        expect($user->getCacheKey())->toBe('CustomPrefix-1');
    });

    it('uses custom default duration from config', function () {
        Config::set('users-online.default_duration', 600); // 10 minutes

        $user = makeUser();
        $user->setCacheWithConfig();

        expect($user->isOnline())->toBeTrue();

        // Move forward 5 minutes (should still be online)
        $this->travel(5)->minutes();
        expect($user->isOnline())->toBeTrue();

        // Move forward another 6 minutes (should be offline)
        $this->travel(6)->minutes();
        expect($user->isOnline())->toBeFalse();
    });

    it('respects custom user fields configuration', function () {
        Config::set('users-online.user_fields', ['id', 'name']);

        $user = makeUser();
        $user->setCache();

        $cacheContent = $user->getCacheContent();
        $userData = $cacheContent['user'];

        expect($userData->id)->toBe($user->id);
        expect($userData->name)->toBe($user->name);
        expect(isset($userData->email))->toBeFalse(); // Email should not be cached
    });

    it('falls back to defaults when config is missing', function () {
        // Clear all config
        Config::set('users-online', []);

        $user = makeUser();

        expect($user->getCacheKey())->toBe('UserOnline-1');

        $user->setCache();
        expect($user->isOnline())->toBeTrue();
    });

    it('allows override of config duration with method parameter', function () {
        Config::set('users-online.default_duration', 600); // 10 minutes

        $user = makeUser();
        $user->setCacheWithConfig(120); // Override to 2 minutes

        expect($user->isOnline())->toBeTrue();

        // Move forward 3 minutes (should be offline due to override)
        $this->travel(3)->minutes();
        expect($user->isOnline())->toBeFalse();
    });

    it('validates cache duration is positive', function () {
        $user = makeUser();

        expect(fn() => $user->setCache(0))
            ->toThrow(InvalidArgumentException::class, 'Cache duration must be greater than 0');

        expect(fn() => $user->setCache(-100))
            ->toThrow(InvalidArgumentException::class, 'Cache duration must be greater than 0');
    });

    it('handles cache store configuration', function () {
        // Test that different cache stores can be configured
        // This test assumes default array cache works
        Config::set('users-online.cache_store', null); // Use default

        $user = makeUser();
        $user->setCache();

        expect($user->isOnline())->toBeTrue();
    });

});

describe('Environment Variable Support', function () {

    it('supports USERS_ONLINE_DURATION environment variable', function () {
        // This test documents the expected behavior when using env variables
        // In real usage: USERS_ONLINE_DURATION=1800 would set 30 minutes

        Config::set('users-online.default_duration', 1800);

        $user = makeUser();
        $user->setCacheWithConfig();

        expect($user->isOnline())->toBeTrue();
    });

    it('supports USERS_ONLINE_PREFIX environment variable', function () {
        // This test documents the expected behavior when using env variables
        // In real usage: USERS_ONLINE_PREFIX=MyApp would set custom prefix

        Config::set('users-online.cache_prefix', 'MyApp');

        $user = makeUser();

        expect($user->getCacheKey())->toBe('MyApp-1');
    });

});
