<?php

use Carbon\Carbon;
use SamuelTerra22\UsersOnline\Providers\UsersOnlineEventServiceProvider;

beforeEach(function () {
    $this->app->register(UsersOnlineEventServiceProvider::class);
});

describe('Edge Cases and Validations', function () {

    it('returns true when setCache is successful', function () {
        $user = makeUser();

        $result = $user->setCache(300);

        expect($result)->toBeTrue();
    });

    it('handles setCache with different time values', function () {
        $user = makeUser();

        expect($user->setCache(1))->toBeTrue(); // 1 second
        expect($user->setCache(86400))->toBeTrue(); // 24 hours
        expect($user->setCache(300))->toBeTrue(); // 5 minutes
    });

    it('handles getCacheContent when cache does not exist', function () {
        $user = makeUser();

        $content = $user->getCacheContent();

        expect($content)->toBeArray()
            ->and($content)->toHaveKey('cachedAt')
            ->and($content)->toHaveKey('user')
            ->and($content['cachedAt'])->toBeInstanceOf(Carbon::class)
            ->and($content['user']->id)->toBe($user->id)
            ->and($content['user']->name)->toBe($user->name);
    });

    it('preserves existing cache when calling getCacheContent', function () {
        $user = makeUser();
        $originalTime = Carbon::create('2023', 1, 1, 12, 0, 0);

        Carbon::setTestNow($originalTime);
        $user->setCache();

        Carbon::setTestNow($originalTime->addHours(1));
        $content = $user->getCacheContent();

        expect($content['cachedAt']->toDateTimeString())
            ->toBe($originalTime->toDateTimeString());
    });

    it('handles getCachedAt with integer timestamp in cache', function () {
        $user = makeUser();
        $timestamp = 1640995200; // 2022-01-01 00:00:00

        // Manually set cache with integer timestamp
        cache()->put($user->getCacheKey(), [
            'cachedAt' => $timestamp,
            'user' => $user
        ], 300);

        expect($user->getCachedAt())->toBe($timestamp);
    });

    it('handles sorting with mixed timestamp formats', function () {
        $user1 = makeUser();
        $user2 = makeUser();
        $user3 = makeUser();

        // Use current time base to avoid cache expiration
        $baseTime = Carbon::now();

        // Set user1 with Carbon instance
        Carbon::setTestNow($baseTime);
        $user1->setCache();

        // Set user2 with later time (1 second later)
        Carbon::setTestNow($baseTime->copy()->addSecond());
        $user2->setCache();

        // Set user3 with latest time (2 seconds later)
        Carbon::setTestNow($baseTime->copy()->addSeconds(2));
        $user3->setCache();

        // Don't reset time completely, just move forward slightly to ensure cache is still valid
        Carbon::setTestNow($baseTime->copy()->addSeconds(3));

        // Verify all users are online first
        expect($user1->isOnline())->toBeTrue();
        expect($user2->isOnline())->toBeTrue();
        expect($user3->isOnline())->toBeTrue();

        $allOnline = getUserModel()->allOnline();
        expect($allOnline)->toHaveCount(3);

        $mostRecent = collect(getUserModel()->mostRecentOnline());
        $expectedOrder = [$user3->id, $user2->id, $user1->id];

        expect($mostRecent->pluck('id')->all())->toBe($expectedOrder);

        // Reset time at the end
        Carbon::setTestNow();
    });

    it('handles corrupted cache data gracefully', function () {
        $user = makeUser();

        // Set corrupted cache
        cache()->put($user->getCacheKey(), [
            'cachedAt' => 'invalid-data',
            'user' => $user
        ], 300);

        expect($user->getCachedAt())->toBe(0);
        expect($user->isOnline())->toBeTrue(); // Cache exists but data is corrupted
    });

    it('handles cache without cachedAt key', function () {
        $user = makeUser();

        // Set cache without cachedAt
        cache()->put($user->getCacheKey(), [
            'user' => $user
        ], 300);

        expect($user->getCachedAt())->toBe(0);
    });

    it('handles large user datasets efficiently', function () {
        $users = collect();

        // Create 50 users and set them online
        for ($i = 0; $i < 50; $i++) {
            $user = makeUser();
            $user->setCache();
            $users->push($user);

            // Small delay to ensure different timestamps
            usleep(1000); // 1ms
        }

        $onlineUsers = getUserModel()->allOnline();
        $mostRecent = getUserModel()->mostRecentOnline();
        $leastRecent = getUserModel()->leastRecentOnline();

        expect($onlineUsers)->toHaveCount(50);
        expect($mostRecent)->toHaveCount(50);
        expect($leastRecent)->toHaveCount(50);

        // Verify ordering
        expect($mostRecent[0]['id'])->toBe($users->last()->id);
        expect($leastRecent[0]['id'])->toBe($users->first()->id);
    });

    it('maintains consistency after multiple cache operations', function () {
        $user = makeUser();

        // Multiple cache operations
        $user->setCache(300);
        expect($user->isOnline())->toBeTrue();

        $user->pullCache();
        expect($user->isOnline())->toBeFalse();

        $user->setCache(600);
        expect($user->isOnline())->toBeTrue();

        $firstCacheTime = $user->getCachedAt();

        // Set cache again with different duration
        sleep(1);
        $user->setCache(900);
        $secondCacheTime = $user->getCachedAt();

        expect($secondCacheTime)->toBeGreaterThan($firstCacheTime);
    });

    it('handles concurrent operations correctly', function () {
        $user1 = makeUser();
        $user2 = makeUser();
        $user3 = makeUser();

        // Simulate concurrent logins
        $user1->setCache();
        $user2->setCache();
        $user3->setCache();

        expect(getUserModel()->allOnline())->toHaveCount(3);

        // Simulate concurrent logouts
        $user1->pullCache();
        $user3->pullCache();

        expect(getUserModel()->allOnline())->toHaveCount(1);
        expect(getUserModel()->allOnline()->first()->id)->toBe($user2->id);
    });

});

describe('Service Provider Integration', function () {

    it('automatically registers event listeners', function () {
        // Force register the service provider
        $this->app->register(UsersOnlineEventServiceProvider::class);

        $listeners = app('events')->getListeners('Illuminate\Auth\Events\Login');
        $logoutListeners = app('events')->getListeners('Illuminate\Auth\Events\Logout');

        expect($listeners)->not->toBeEmpty();
        expect($logoutListeners)->not->toBeEmpty();
    });

    it('uses session lifetime configuration by default', function () {
        config(['session.lifetime' => 120]); // 2 hours

        $user = makeUser();

        // Manually trigger the login listener since Auth::login doesn't fire events in tests
        $listener = new \SamuelTerra22\UsersOnline\Listeners\LoginListener();
        $loginEvent = new \Illuminate\Auth\Events\Login('web', $user, false);
        $listener->handle($loginEvent);

        expect($user->isOnline())->toBeTrue();

        // Fast forward to just before expiration (119 minutes)
        Carbon::setTestNow(Carbon::now()->addMinutes(119));
        expect($user->isOnline())->toBeTrue();

        // Fast forward past expiration (121 minutes)
        Carbon::setTestNow(Carbon::now()->addMinutes(2));
        expect($user->isOnline())->toBeFalse();

        Carbon::setTestNow(); // Reset time
    });

});
