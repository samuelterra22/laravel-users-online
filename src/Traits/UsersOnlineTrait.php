<?php
declare(strict_types=1);

namespace SamuelTerra22\UsersOnline\Traits;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

trait UsersOnlineTrait
{
    /**
     * Get all users online.
     * @return mixed
     */
    public function allOnline()
    {
        return $this->all()->filter->isOnline();
    }

    /**
     * Check if the user is online.
     * @return bool
     */
    public function isOnline(): bool
    {
        return Cache::has($this->getCacheKey());
    }

    /**
     * Get the least recent online users.
     * @return mixed
     */
    public function leastRecentOnline()
    {
        $sorted = $this->allOnline()
            ->sortBy(function ($user) {
                return $user->getCachedAt();
            });

        return $sorted->values()->all();
    }

    /**
     * Get the most recent online users.
     * @return mixed
     */
    public function mostRecentOnline()
    {
        $sorted = $this->allOnline()
            ->sortByDesc(function ($user) {
                return $user->getCachedAt();
            });

        return $sorted->values()->all();
    }

    /**
     * Get the cached at timestamp for the user.
     * @return int|mixed
     */
    public function getCachedAt()
    {
        if (empty($cache = Cache::get($this->getCacheKey()))) {
            return 0;
        }

        return $cache['cachedAt'];
    }

    /**
     * Set the cache for the user.
     *
     * @param int $seconds
     *
     * @return bool
     */
    public function setCache(int $seconds = 300): bool
    {
        return Cache::put(
            $this->getCacheKey(),
            $this->getCacheContent(),
            $seconds
        );
    }

    /**
     * Get the content to be cached for the user.
     * @return array|mixed
     */
    public function getCacheContent()
    {
        if (!empty($cache = Cache::get($this->getCacheKey()))) {
            return $cache;
        }
        $cachedAt = Carbon::now();

        return [
            'cachedAt' => $cachedAt,
            'user'     => $this,
        ];
    }

    /**
     * Remove the cache for the user.
     * @return void
     */
    public function pullCache()
    {
        Cache::pull($this->getCacheKey());
    }

    /**
     * @return string
     */
    public function getCacheKey(): string
    {
        return sprintf('%s-%s', 'UserOnline', $this->id);
    }
}
