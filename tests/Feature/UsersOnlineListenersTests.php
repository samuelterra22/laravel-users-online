<?php

use Illuminate\Support\Facades\Auth;
use SamuelTerra22\UsersOnline\Providers\UsersOnlineEventServiceProvider;
use Carbon\Carbon;

beforeEach(function () {
    $this->app->register(UsersOnlineEventServiceProvider::class);
});

describe('Auth Event Listeners', function () {

    it('marks user as online when logged in', function () {
        $user = makeUser();

        Auth::login($user);

        expect($user->isOnline())->toBeTrue();
    });

    it('shows user as offline when not logged in', function () {
        $user = makeUser();

        expect($user->isOnline())->toBeFalse();
    });

    it('removes user from online list when logged out', function () {
        $user1 = makeUser();
        Auth::login($user1);

        $user2 = makeUser();
        Auth::login($user2);

        $user3 = makeUser();
        Auth::login($user3);

        $user4 = makeUser();
        Auth::login($user4);

        Auth::logout($user1);

        expect(getUserModel()->allOnline())->toHaveCount(3);
    });

});

describe('Online Users Ordering with Auth', function () {

    it('lists all logged users ordered by least recent online', function () {
        $userTwo = makeUser();
        Auth::login($userTwo);

        $userOne = makeUser();
        Auth::login($userOne);

        $userThree = makeUser();
        Auth::login($userThree);

        Carbon::setTestNow();

        $expectedOrder = [
            $userTwo->id,
            $userOne->id,
            $userThree->id,
        ];

        $leastRecentOnline = collect(getUserModel()->leastRecentOnline());

        expect($leastRecentOnline->pluck('id')->all())->toBe($expectedOrder);
    });

    it('lists all logged users ordered by most recent online', function () {
        $userTwo = makeUser();
        Auth::login($userTwo);

        $userOne = makeUser();
        Auth::login($userOne);

        $userThree = makeUser();
        Auth::login($userThree);

        Carbon::setTestNow();

        $expectedOrder = [
            $userThree->id,
            $userOne->id,
            $userTwo->id,
        ];

        $mostRecentOnline = collect(getUserModel()->mostRecentOnline());

        expect($mostRecentOnline->pluck('id')->all())->toBe($expectedOrder);
    });

    it('returns correct count of all online users', function () {
        makeUser(); // offline user

        $userTwo = makeUser();
        Auth::login($userTwo);

        $userThree = makeUser();
        Auth::login($userThree);

        $userFour = makeUser();
        Auth::login($userFour);

        expect(getUserModel()->allOnline())->toHaveCount(3);
    });

});
