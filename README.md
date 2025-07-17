# Laravel Users Online

[![Latest Version](https://img.shields.io/packagist/v/samuelterra22/laravel-users-online.svg?style=flat-square)](https://packagist.org/packages/samuelterra22/laravel-users-online)
[![Total Downloads](https://img.shields.io/packagist/dt/samuelterra22/laravel-users-online.svg?style=flat-square)](https://packagist.org/packages/samuelterra22/laravel-users-online)
[![License](https://img.shields.io/packagist/l/samuelterra22/laravel-users-online.svg?style=flat-square)](https://packagist.org/packages/samuelterra22/laravel-users-online)
[![Tests](https://img.shields.io/github/actions/workflow/status/samuelterra22/laravel-users-online/test.yml?branch=main&label=tests&style=flat-square)](https://github.com/samuelterra22/laravel-users-online/actions)

Efficiently track and manage online users in your Laravel application using cache-based session management.

## Requirements

- PHP 8.2+
- Laravel 9.x, 10.x, 11.x, or 12.x

## Installation

```bash
composer require samuelterra22/laravel-users-online
```

### Add Trait to User Model

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use SamuelTerra22\UsersOnline\Traits\UsersOnlineTrait;

class User extends Authenticatable
{
    use UsersOnlineTrait;
    
    // Your existing user model code...
}
```

### Publish Configuration (Optional)

```bash
php artisan vendor:publish --provider="SamuelTerra22\UsersOnline\Providers\UsersOnlineEventServiceProvider"
```

## Usage

### Check if User is Online

```php
$user = User::find(1);

if ($user->isOnline()) {
    echo "User is currently online!";
}
```

### Get All Online Users

```php
$onlineUsers = User::allOnline();
```

### Get Users Ordered by Activity

```php
// Most recently active users first
$mostRecent = User::mostRecentOnline();

// Least recently active users first  
$leastRecent = User::leastRecentOnline();
```

### Manual Cache Management

```php
$user = User::find(1);

// Set user as online (5 minutes default)
$user->setCache();

// Set custom duration (in seconds)
$user->setCache(1800); // 30 minutes

// Remove user from online list
$user->pullCache();

// Get cached timestamp
$lastSeen = $user->getCachedAt();
```

### Using with Real-time Facades

```php
use Facades\App\Models\User as UserFacade;

$onlineUsers = UserFacade::mostRecentOnline();
$totalOnline = UserFacade::allOnline()->count();
```

## Automatic Event Handling

The package automatically handles user login/logout events:

- **Login**: User is marked as online using session lifetime configuration
- **Logout**: User is removed from online list

## API Reference

### Available Methods

| Method | Description | Return Type |
|--------|-------------|-------------|
| `isOnline()` | Check if user is currently online | `bool` |
| `allOnline()` | Get collection of all online users | `Collection` |
| `mostRecentOnline()` | Get users ordered by most recent activity | `array` |
| `leastRecentOnline()` | Get users ordered by least recent activity | `array` |
| `setCache($seconds = 300)` | Mark user as online for specified duration | `bool` |
| `pullCache()` | Remove user from online list | `void` |
| `getCachedAt()` | Get timestamp when user was last cached | `int` |
| `getCacheContent()` | Get complete cache data for user | `array` |

## Configuration

The package uses your Laravel session configuration by default. You can customize the cache duration:

```php
// In your service provider or configuration
config(['users-online.default_duration' => 1800]); // 30 minutes
```

## Testing

```bash
# Run tests
composer test

# Run tests with coverage
composer test-coverage

# Generate HTML coverage report
composer test-coverage-html
```

## Security

This package stores minimal user data in cache (ID, name, email) and automatically handles cleanup. For production environments, ensure your cache driver is properly secured.

## Performance

- Uses Laravel's cache system for optimal performance
- Minimal database queries (only for retrieving user collections)
- Efficient memory usage with clean cache data structure
- Supports all Laravel cache drivers

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).

## Support

- **Issues**: [GitHub Issues](https://github.com/samuelterra22/laravel-users-online/issues)
- **Author**: [Samuel Terra](https://samuelterra.dev)

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on recent changes.
