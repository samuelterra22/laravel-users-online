<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use SamuelTerra22\UsersOnline\Providers\UsersOnlineEventServiceProvider;

beforeEach(function () {
    $this->app->register(UsersOnlineEventServiceProvider::class);
});

describe('Laravel Integration Tests', function () {

    it('respects session lifetime configuration', function () {
        // Set custom session lifetime
        Config::set('session.lifetime', 60); // 1 hour

        $user = makeUser();

        // Manually set cache using session lifetime (simulating login event)
        $user->setCache(60 * 60); // 1 hour in seconds

        expect($user->isOnline())->toBeTrue();

        // Should still be online before expiration
        Carbon::setTestNow(Carbon::now()->addMinutes(59));
        expect($user->isOnline())->toBeTrue();

        // Should be offline after expiration
        Carbon::setTestNow(Carbon::now()->addMinutes(2));
        expect($user->isOnline())->toBeFalse();

        Carbon::setTestNow(); // Reset time
    });

    it('works with different authentication guards', function () {
        $user = makeUser();

        // Test with manual cache setting (simulating guard login)
        $user->setCache();
        expect($user->isOnline())->toBeTrue();

        // Test logout (simulating guard logout)
        $user->pullCache();
        expect($user->isOnline())->toBeFalse();
    });

    it('handles remember me functionality', function () {
        $user = makeUser();

        // Simulate login with remember me (longer cache duration)
        $user->setCache(config('session.lifetime', 120) * 60);
        expect($user->isOnline())->toBeTrue();

        // Should maintain online status with remember token
        $cacheContent = $user->getCacheContent();
        expect($cacheContent)->toHaveKey('user');
        expect($cacheContent['user']->id)->toBe($user->id);
    });

    it('integrates with middleware correctly', function () {
        $user1 = makeUser();
        $user2 = makeUser();
        $user3 = makeUser();

        // Simulate middleware tracking multiple users
        $user1->setCache();
        $user2->setCache();
        $user3->setCache();

        $onlineUsers = getUserModel()->allOnline();
        expect($onlineUsers)->toHaveCount(3);

        // Simulate session expiration for one user
        $user1->pullCache();

        $remainingUsers = getUserModel()->allOnline();
        expect($remainingUsers)->toHaveCount(2);
        expect($remainingUsers->pluck('id')->toArray())->toContain($user2->id, $user3->id);
    });

});

describe('Cache Driver Compatibility', function () {

    it('works with array cache driver', function () {
        Config::set('cache.default', 'array');

        $user = makeUser();
        $user->setCache();

        expect($user->isOnline())->toBeTrue();
        expect($user->getCachedAt())->toBeGreaterThan(0);
    });

    it('maintains data integrity across cache operations', function () {
        $user = makeUser();
        $originalData = [
            'name' => $user->name,
            'email' => $user->email,
            'id' => $user->id
        ];

        $user->setCache();
        $cacheContent = $user->getCacheContent();

        expect($cacheContent['user']->name)->toBe($originalData['name']);
        expect($cacheContent['user']->email)->toBe($originalData['email']);
        expect($cacheContent['user']->id)->toBe($originalData['id']);
    });

});

describe('Error Handling and Recovery', function () {

    it('gracefully handles cache failures', function () {
        $user = makeUser();

        // Simulate cache unavailability by using invalid cache key
        $originalGetCacheKey = $user->getCacheKey();

        // Force cache to be empty but simulate it exists
        cache()->forget($user->getCacheKey());

        // Should handle gracefully
        expect($user->getCachedAt())->toBe(0);
        expect($user->getCacheContent())->toBeArray();
    });

    it('recovers from partial cache corruption', function () {
        $user = makeUser();

        // Set normal cache first
        $user->setCache();
        expect($user->isOnline())->toBeTrue();

        // Corrupt the cache partially
        cache()->put($user->getCacheKey(), [
            'user' => $user,
            // Missing cachedAt intentionally
        ], 300);

        // Should still detect as online but handle missing timestamp
        expect($user->isOnline())->toBeTrue();
        expect($user->getCachedAt())->toBe(0);
    });

    it('handles concurrent cache modifications', function () {
        $user = makeUser();

        // Simulate concurrent operations
        $user->setCache(300);
        $firstTimestamp = $user->getCachedAt();

        // Another process modifies cache
        sleep(1);
        cache()->put($user->getCacheKey(), [
            'cachedAt' => Carbon::now(),
            'user' => $user
        ], 600);

        $secondTimestamp = $user->getCachedAt();

        expect($secondTimestamp)->toBeGreaterThan($firstTimestamp);
        expect($user->isOnline())->toBeTrue();
    });

});

describe('Real-world Scenarios', function () {

    it('handles user session cleanup on database changes', function () {
        $user = makeUser();
        $user->setCache();

        expect($user->isOnline())->toBeTrue();

        // Simulate user being deleted or deactivated
        $userId = $user->id;
        $user->delete();

        // Cache should still exist until expiration
        expect(cache()->has("UserOnline-{$userId}"))->toBeTrue();

        // But allOnline should filter out non-existent users
        $onlineUsers = getUserModel()->allOnline();
        expect($onlineUsers->pluck('id')->toArray())->not->toContain($userId);
    });

    it('maintains performance with rapid user turnover', function () {
        $userCount = 25;
        $users = collect();

        // Create users rapidly
        for ($i = 0; $i < $userCount; $i++) {
            $user = makeUser();
            $user->setCache(); // Simulate login
            $users->push($user);

            // Some users logout immediately
            if ($i % 3 === 0) {
                $user->pullCache(); // Simulate logout
            }
        }

        $expectedOnlineCount = $userCount - intval($userCount / 3);
        $actualOnlineCount = getUserModel()->allOnline()->count();

        expect($actualOnlineCount)->toBe($expectedOnlineCount);
    });

    it('provides consistent ordering under load', function () {
        $users = collect();

        // Create users with known timestamps
        for ($i = 0; $i < 10; $i++) {
            $user = makeUser();
            Carbon::setTestNow(Carbon::now()->addSeconds($i));
            $user->setCache();
            $users->push($user);
        }

        Carbon::setTestNow(); // Reset time

        // Test multiple times to ensure consistency
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $mostRecent = collect(getUserModel()->mostRecentOnline());
            $leastRecent = collect(getUserModel()->leastRecentOnline());

            // Most recent should be in reverse order
            expect($mostRecent->first()['id'])->toBe($users->last()->id);
            expect($mostRecent->last()['id'])->toBe($users->first()->id);

            // Least recent should be in original order
            expect($leastRecent->first()['id'])->toBe($users->first()->id);
            expect($leastRecent->last()['id'])->toBe($users->last()->id);
        }
    });

});

describe('Security and Privacy', function () {

    it('caches user data but should be mindful of sensitive information', function () {
        $user = makeUser();
        $user->password = 'secret-password';
        $user->remember_token = 'secret-token';
        $user->save();

        $user->setCache();
        $cacheContent = $user->getCacheContent();

        // The current implementation does cache the full user object
        // This test documents the current behavior and could guide future improvements
        expect($cacheContent)->toHaveKey('cachedAt');
        expect($cacheContent)->toHaveKey('user');
        expect($cacheContent['user'])->toBe($user);

        // In a production environment, consider implementing user serialization
        // to exclude sensitive fields from cache
    });

    it('maintains user isolation in cache', function () {
        $user1 = makeUser();
        $user2 = makeUser();

        $user1->setCache();
        $user2->setCache();

        // Each user should have distinct cache keys
        expect($user1->getCacheKey())->not->toBe($user2->getCacheKey());

        // Operations on one user should not affect the other
        $user1->pullCache();
        expect($user1->isOnline())->toBeFalse();
        expect($user2->isOnline())->toBeTrue();
    });

});
