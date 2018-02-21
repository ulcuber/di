# Usage
```php
use Di\App;

/**
 * Get or create, add and return
 */
App::get(SomeClass::class);

/**
 * Get self
 */
App::instance();

/**
 * Add class creator
 */
App::addClass(SomeClass::class, []);

/**
 * Add object of given class
 */
App::addInstance(SomeClass::class, $object);

/**
 * Add Closure that returns object of given class
 */
App::addClosure(SomeClass::class, function ($app) {});

/**
 * Check if class exists in container
 */
App::has(SomeClass::class);
```

Resolves type-hinted dependencies
```php
use Di\App;

class SomeClass
{
    private $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }
}
```
