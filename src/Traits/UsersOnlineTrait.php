<?php
declare(strict_types=1);

namespace SamuelTerra22\UsersOnline\Traits;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

trait UsersOnlineTrait
{
    private const CACHE_PREFIX = 'UserOnline';
    private const DEFAULT_CACHE_DURATION = 300;

    /**
     * Get all users online.
     */
    public function allOnline(): Collection
    {
        return $this->all()->filter->isOnline();
    }

    /**
     * Check if the user is online.
     */
    public function isOnline(): bool
    {
        return Cache::has($this->getCacheKey());
    }

    /**
     * Get the least recent online users.
     */
    public function leastRecentOnline(): array
    {
        $sorted = $this->allOnline()
            ->sortBy(function ($user) {
                return $user->getCachedAtForSorting();
            });

        return $sorted->values()->all();
    }

    /**
     * Get the most recent online users.
     */
    public function mostRecentOnline(): array
    {
        $sorted = $this->allOnline()
            ->sortByDesc(function ($user) {
                return $user->getCachedAtForSorting();
            });

        return $sorted->values()->all();
    }

    /**
     * Get the cached at timestamp for the user.
     */
    public function getCachedAt(): int
    {
        $cache = Cache::get($this->getCacheKey());

        if (!isset($cache['cachedAt'])) {
            return 0;
        }

        $cachedAt = $cache['cachedAt'];

        if ($cachedAt instanceof Carbon) {
            return $cachedAt->getTimestamp();
        }

        return (int)$cachedAt;
    }

    /**
     * Get the cached Carbon instance for sorting purposes.
     */
    private function getCachedAtForSorting()
    {
        $cache = Cache::get($this->getCacheKey());

        if (!isset($cache['cachedAt'])) {
            return Carbon::createFromTimestamp(0);
        }

        $cachedAt = $cache['cachedAt'];

        if ($cachedAt instanceof Carbon) {
            return $cachedAt;
        }

        return Carbon::createFromTimestamp((int)$cachedAt);
    }

    /**
     * Set the cache for the user.
     */
    public function setCache(int $seconds = self::DEFAULT_CACHE_DURATION): bool
    {
        return Cache::put(
            $this->getCacheKey(),
            $this->buildCacheContent(),
            $seconds
        );
    }

    /**
     * Get the content to be cached for the user.
     */
    public function getCacheContent(): array
    {
        $existingCache = Cache::get($this->getCacheKey());

        return $existingCache ?? $this->buildCacheContent();
    }

    /**
     * Remove the cache for the user.
     */
    public function pullCache(): void
    {
        Cache::pull($this->getCacheKey());
    }

    /**
     * Get the cache key for the user.
     */
    public function getCacheKey(): string
    {
        return sprintf('%s-%s', self::CACHE_PREFIX, $this->id);
    }

    /**
     * Build fresh cache content.
     */
    private function buildCacheContent(): array
    {
        return [
            'cachedAt' => Carbon::now(),
            'user'     => $this,
        ];
    }
}
