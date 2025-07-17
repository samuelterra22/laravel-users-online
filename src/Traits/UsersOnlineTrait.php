<?php
declare(strict_types=1);

namespace SamuelTerra22\UsersOnline\Traits;

use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

trait UsersOnlineTrait
{
    private const FALLBACK_CACHE_PREFIX = 'UserOnline';
    private const FALLBACK_DEFAULT_DURATION = 300;

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
        try {
            $cacheStore = $this->getCacheStore();
            return $cacheStore->has($this->getCacheKey());
        } catch (Exception $e) {
            logger()->warning('Error checking online status', [
                'user_id' => $this->id ?? 'unknown',
                'error'   => $e->getMessage()
            ]);
            return false;
        }
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
        try {
            $cacheStore = $this->getCacheStore();
            $cache = $cacheStore->get($this->getCacheKey());

            if (!isset($cache['cachedAt'])) {
                return 0;
            }

            $cachedAt = $cache['cachedAt'];

            if ($cachedAt instanceof Carbon) {
                return $cachedAt->getTimestamp();
            }

            return (int)$cachedAt;
        } catch (Exception $e) {
            logger()->warning('Error getting cached timestamp', [
                'user_id' => $this->id ?? 'unknown',
                'error'   => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Get the cached Carbon instance for sorting purposes.
     */
    private function getCachedAtForSorting(): Carbon
    {
        try {
            $cacheStore = $this->getCacheStore();
            $cache = $cacheStore->get($this->getCacheKey());

            if (!isset($cache['cachedAt'])) {
                return Carbon::createFromTimestamp(0);
            }

            $cachedAt = $cache['cachedAt'];

            if ($cachedAt instanceof Carbon) {
                return $cachedAt;
            }

            return Carbon::createFromTimestamp((int)$cachedAt);
        } catch (Exception $e) {
            logger()->warning('Error getting cached at for sorting', [
                'user_id' => $this->id ?? 'unknown',
                'error'   => $e->getMessage()
            ]);
            return Carbon::createFromTimestamp(0);
        }
    }

    /**
     * Set the cache for the user.
     */
    public function setCache(int $seconds = null): bool
    {
        $duration = $seconds ?? $this->getDefaultDuration();

        if ($duration <= 0) {
            throw new InvalidArgumentException('Cache duration must be greater than 0');
        }

        try {
            $cacheStore = $this->getCacheStore();
            return $cacheStore->put(
                $this->getCacheKey(),
                $this->buildCacheContent(),
                $duration
            );
        } catch (Exception $e) {
            logger()->error('Error setting cache', [
                'user_id' => $this->id ?? 'unknown',
                'seconds' => $duration,
                'error'   => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get the content to be cached for the user.
     */
    public function getCacheContent(): array
    {
        try {
            $cacheStore = $this->getCacheStore();
            $existingCache = $cacheStore->get($this->getCacheKey());
            return $existingCache ?? $this->buildCacheContent();
        } catch (Exception $e) {
            logger()->warning('Error getting cache content', [
                'user_id' => $this->id ?? 'unknown',
                'error'   => $e->getMessage()
            ]);
            return $this->buildCacheContent();
        }
    }

    /**
     * Remove the cache for the user.
     */
    public function pullCache(): void
    {
        try {
            $cacheStore = $this->getCacheStore();
            $cacheStore->forget($this->getCacheKey());
        } catch (Exception $e) {
            logger()->warning('Error pulling cache', [
                'user_id' => $this->id ?? 'unknown',
                'error'   => $e->getMessage()
            ]);
        }
    }

    /**
     * Get the cache key for the user.
     */
    public function getCacheKey(): string
    {
        if (!$this->id) {
            throw new InvalidArgumentException('User ID is required for cache key');
        }

        $prefix = config('users-online.cache_prefix', self::FALLBACK_CACHE_PREFIX);
        return sprintf('%s-%s', $prefix, $this->id);
    }

    /**
     * Set cache using configuration values.
     */
    public function setCacheWithConfig(?int $seconds = null): bool
    {
        $duration = $seconds ?? $this->getDefaultDuration();
        return $this->setCache($duration);
    }

    /**
     * Build fresh cache content with minimal user data.
     */
    private function buildCacheContent(): array
    {
        return [
            'cachedAt' => Carbon::now(),
            'user'     => $this->getOnlineUserData(),
        ];
    }

    /**
     * Get minimal user data for cache (excludes sensitive information).
     * Override this method to customize what user data is cached.
     */
    protected function getOnlineUserData(): static
    {
        $fields = config('users-online.user_fields', ['id', 'name', 'email']);
        $userData = $this->only($fields);

        // Create a new instance to avoid caching the full model with all relationships
        $cleanUser = new static();
        $cleanUser->forceFill($userData);
        $cleanUser->exists = true;

        return $cleanUser;
    }

    /**
     * Get the default cache duration from config.
     */
    private function getDefaultDuration(): int
    {
        return config('users-online.default_duration', self::FALLBACK_DEFAULT_DURATION);
    }

    /**
     * Get the cache store instance.
     */
    private function getCacheStore(): Repository
    {
        $store = config('users-online.cache_store');

        if ($store) {
            return Cache::store($store);
        }

        return Cache::store();
    }
}
