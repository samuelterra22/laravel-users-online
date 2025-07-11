<?php

use Carbon\Carbon;

describe('Users Online Basic Features', function () {

    it('returns false when cache was not created', function () {
        $user = makeUser();

        expect($user->isOnline())->toBeFalse();
    });

    it('returns correct cache key format', function () {
        $user = makeUser();

        expect($user->getCacheKey())->toBe('UserOnline-1');
    });

    it('shows user as online when cache is set', function () {
        $user = makeUser();
        $user->setCache();

        expect($user->isOnline())->toBeTrue();
    });

    it('logs out user after time expiration', function () {
        $user = makeUser();
        $user->setCache(300);

        Carbon::setTestNow(Carbon::now()->addMinutes(10));

        expect($user->isOnline())->toBeFalse();
    });

    it('clears cache when user explicitly logs out', function () {
        $user = makeUser();
        $user->setCache();
        $user->pullCache();

        expect($user->isOnline())->toBeFalse();
    });

});

describe('Multiple Users Online', function () {

    it('returns all users currently online', function () {
        $user1 = makeUser();
        $user1->setCache();

        $user2 = makeUser();
        $user2->setCache();

        $user3 = makeUser();
        $user3->setCache();
        $user3->pullCache(); // This user goes offline

        expect(getUserModel()->allOnline())->toHaveCount(2);
    });

    it('orders users by most recent login', function () {
        $user1 = makeUser();
        $user1->setCache();

        $user2 = makeUser();
        $user2->setCache();

        $user3 = makeUser();
        $user3->setCache();

        $mostRecent = collect(getUserModel()->mostRecentOnline());
        $expectedOrder = [$user3->id, $user2->id, $user1->id];

        expect($mostRecent->pluck('id')->all())->toBe($expectedOrder);
    });

    it('orders users by least recent login', function () {
        $user1 = makeUser();
        $user1->setCache();

        $user2 = makeUser();
        $user2->setCache();

        $user3 = makeUser();
        $user3->setCache();

        Carbon::setTestNow();

        $leastRecent = collect(getUserModel()->leastRecentOnline());
        $expectedOrder = [$user1->id, $user2->id, $user3->id];

        expect($leastRecent->pluck('id')->all())->toBe($expectedOrder);
    });

});

describe('Cache Management', function () {

    it('returns zero when no cache exists', function () {
        $user = makeUser();

        expect($user->getCachedAt())->toBe(0);
    });

    it('returns cached info when available', function () {
        Carbon::setTestNow(Carbon::create('2017', 2, 22, 13, 50, 22));

        $user = makeUser();
        $user->setCache();

        $cacheContent = $user->getCacheContent();

        expect($cacheContent)->toHaveKey('cachedAt')
            ->and($cacheContent)->toHaveKey('user')
            ->and($cacheContent['cachedAt'])->toBeInstanceOf(Carbon::class)
            ->and($cacheContent['cachedAt']->toDateTimeString())->toBe(Carbon::now()->toDateTimeString())
            ->and($cacheContent['user'])->toBe($user);
    });

});
