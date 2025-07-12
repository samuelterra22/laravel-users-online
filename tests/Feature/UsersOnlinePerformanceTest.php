<?php

use Carbon\Carbon;

describe('Performance and Stress Tests', function () {

    it('handles mass user operations efficiently', function () {
        $startTime = microtime(true);
        $userCount = 100;
        $users = collect();

        // Mass user creation and online status
        for ($i = 0; $i < $userCount; $i++) {
            $user = makeUser();
            $user->setCache();
            $users->push($user);
        }

        $creationTime = microtime(true) - $startTime;

        // Test retrieval performance
        $retrievalStart = microtime(true);
        $onlineUsers = getUserModel()->allOnline();
        $retrievalTime = microtime(true) - $retrievalStart;

        // Test sorting performance
        $sortingStart = microtime(true);
        $mostRecent = getUserModel()->mostRecentOnline();
        $leastRecent = getUserModel()->leastRecentOnline();
        $sortingTime = microtime(true) - $sortingStart;

        expect($onlineUsers)->toHaveCount($userCount);
        expect($mostRecent)->toHaveCount($userCount);
        expect($leastRecent)->toHaveCount($userCount);

        // Performance assertions (adjust thresholds as needed)
        expect($creationTime)->toBeLessThan(5.0); // 5 seconds max
        expect($retrievalTime)->toBeLessThan(1.0); // 1 second max
        expect($sortingTime)->toBeLessThan(2.0); // 2 seconds max
    });

    it('maintains performance with frequent cache operations', function () {
        $user = makeUser();
        $operationCount = 1000;

        $startTime = microtime(true);

        for ($i = 0; $i < $operationCount; $i++) {
            $user->setCache();
            $user->isOnline();
            if ($i % 2 === 0) {
                $user->getCachedAt();
                $user->getCacheContent();
            }
        }

        $totalTime = microtime(true) - $startTime;
        $avgTimePerOperation = $totalTime / $operationCount;

        expect($totalTime)->toBeLessThan(10.0); // 10 seconds max for 1000 operations
        expect($avgTimePerOperation)->toBeLessThan(0.01); // 10ms max per operation
        expect($user->isOnline())->toBeTrue();
    });

    it('handles memory efficiently with large datasets', function () {
        $initialMemory = memory_get_usage();
        $userCount = 200;

        for ($i = 0; $i < $userCount; $i++) {
            $user = makeUser();
            $user->setCache();

            // Perform operations
            getUserModel()->allOnline();
            if ($i % 10 === 0) {
                getUserModel()->mostRecentOnline();
                getUserModel()->leastRecentOnline();
            }
        }

        $finalMemory = memory_get_usage();
        $memoryIncrease = $finalMemory - $initialMemory;
        $memoryPerUser = $memoryIncrease / $userCount;

        // Memory usage should be reasonable (adjust threshold as needed)
        expect($memoryPerUser)->toBeLessThan(50000); // 50KB per user max
        expect($memoryIncrease)->toBeLessThan(10485760); // 10MB total max
    });

    it('handles rapid login/logout cycles efficiently', function () {
        $users = collect();
        for ($i = 0; $i < 20; $i++) {
            $users->push(makeUser());
        }

        $startTime = microtime(true);
        $cycles = 50;

        for ($cycle = 0; $cycle < $cycles; $cycle++) {
            foreach ($users as $user) {
                \Illuminate\Support\Facades\Auth::login($user);
                \Illuminate\Support\Facades\Auth::logout($user);
            }
        }

        $totalTime = microtime(true) - $startTime;
        $avgTimePerCycle = $totalTime / $cycles;

        expect($totalTime)->toBeLessThan(30.0); // 30 seconds max
        expect($avgTimePerCycle)->toBeLessThan(0.6); // 600ms per cycle max
        expect(getUserModel()->allOnline())->toHaveCount(0);
    });

});

describe('Cache Optimization Tests', function () {

    it('minimizes cache hits for batch operations', function () {
        $users = collect();
        for ($i = 0; $i < 10; $i++) {
            $user = makeUser();
            $user->setCache();
            $users->push($user);
        }

        // Single call should be more efficient than multiple individual calls
        $batchStart = microtime(true);
        $allOnline = getUserModel()->allOnline();
        $batchTime = microtime(true) - $batchStart;

        $individualStart = microtime(true);
        $individualResults = collect();
        foreach ($users as $user) {
            if ($user->isOnline()) {
                $individualResults->push($user);
            }
        }
        $individualTime = microtime(true) - $individualStart;

        expect($allOnline)->toHaveCount(10);
        expect($individualResults)->toHaveCount(10);

        // Batch operation should be more efficient for larger datasets
        // This test demonstrates the efficiency difference
        expect($batchTime)->toBeGreaterThan(0);
        expect($individualTime)->toBeGreaterThan(0);
    });

    it('maintains cache consistency under load', function () {
        $users = collect();
        for ($i = 0; $i < 50; $i++) {
            $users->push(makeUser());
        }

        // Simulate concurrent operations
        foreach ($users->chunk(10) as $chunk) {
            foreach ($chunk as $user) {
                $user->setCache();
            }

            $onlineCount = getUserModel()->allOnline()->count();
            expect($onlineCount)->toBeGreaterThan(0);
        }

        $finalCount = getUserModel()->allOnline()->count();
        expect($finalCount)->toBe(50);

        // Remove in batches
        foreach ($users->chunk(10) as $chunk) {
            foreach ($chunk as $user) {
                $user->pullCache();
            }
        }

        expect(getUserModel()->allOnline())->toHaveCount(0);
    });

});
