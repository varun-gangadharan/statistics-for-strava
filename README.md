<h1 align="center">Symfony Skeleton</h1>

<p align="center">
<a href="https://github.com/robiningelbrecht/symfony-skeleton/actions/workflows/ci.yml"><img src="https://github.com/robiningelbrecht/symfony-skeleton/actions/workflows/ci.yml/badge.svg" alt="CI"></a>
<a href="https://php.net/"><img src="https://img.shields.io/packagist/dependency-v/robiningelbrecht/symfony-skeleton/php.svg?color=%23777bb3&logo=php&logoColor=white&version=dev-master" alt="PHP"></a>
<a href="https://symfony.com/"><img src="https://img.shields.io/packagist/dependency-v/robiningelbrecht/symfony-skeleton/symfony%2Fframework-bundle?logo=symfony&label=symfony&version=dev-master" alt="Symfony"></a>
<a href="https://phpstan.org/"><img src="https://img.shields.io/badge/PHPStan-level%2010-succes.svg?logo=php&logoColor=white&color=31C652" alt="PHPStan Enabled"></a>
    <a href="https://github.com/robiningelbrecht/symfony-skeleton/blob/master/LICENSE"><img src="https://img.shields.io/github/license/robiningelbrecht/symfony-skeleton?color=428f7e&logo=open%20source%20initiative&logoColor=white" alt="License"></a>
</p>

---

## Bootstrap new project

```bash
> composer create-project robiningelbrecht/symfony-skeleton [app-name] --no-install --ignore-platform-reqs
```

Open `.env` and set following ENV VARIABLES:

```
DOCKER_CONTAINER_BASE_NAME=skeleton
DOCKER_MYSQL_PORT=3306
DOCKER_NGINX_PORT=8081
```

```bash
# Install dependencies
> make composer arg="install"
# Setup project (configure containers and functionality)
> make console arg="app:setup"
```

```bash
# Build docker containers
> make build-containers
```

## Makefile

There are some predefined commands in the `Makefile`:

```bash
# Run test suite
> make phpunit
# Run cs fixer
> make csfix
# Run PHPstan
> make phpstan
# Create and run migrations
> make migrate-diff
> make migrate-run
# Run a console command
> make console arg="your:fancy:command"
```

## Events

### Recording Events

In your entity `use RecordsEvents;` and record events when needed:

```php
class User
{
    use RecordsEvents;
    
    public static function create(
        UserId $id,
    ): self {
        // ...
        $user->recordThat(new UserWasCreated(
            userId: $user->getUserId(),
        ));

        return $user;
    }
}
```

### Publishing Events

In your repository, inject the `EventBus` and publish the recorded events:

```php
final readonly class UserRepository implements CommandHandler
{
    public function __construct(
        private EventBus $eventBus,
    ) {
    }
    
    public function save(User $user): void
    {
        // ...
        $this->eventBus->publishEvents($user->getRecordedEvents());
    }    

```

### Registering Event Listeners

Create a manager / event listener and add the `AsEventListener` attribute:

```php
final readonly class UserEmailManager
{
    #[AsEventListener]
    public function reactToUserWasCreated(UserWasCreated $event): void
    {
        // ...
    }
}
```

More info: [https://symfony.com/doc/current/event_dispatcher.html#defining-event-listeners-with-php-attributes](https://symfony.com/doc/current/event_dispatcher.html#defining-event-listeners-with-php-attributes)

## Rate Limiter

## Create a Rate Limiter

Define a new rate limiter in `config/packages/rate_limiter.yaml`:

```yml
framework:
  rate_limiter:
    anonymous:
      policy: 'sliding_window'
      limit: 100
      interval: '60 minutes'
```

## Apply a limiter to a route

Then apply it to the necessary routes:

```php
    #[RateLimiter('anonymous')]
    #[Route(path: '/your/important/route', methods: ['GET', 'POST'])]
    public function handle(Request $request): Response
    {
      // ...
    }
```

more info: [https://symfony.com/doc/current/rate_limiter.html](https://symfony.com/doc/current/rate_limiter.html)
