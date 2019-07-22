<p align="center"><img src="https://raw.githubusercontent.com/s1mpletru1h/php-events/master/logo.svg?sanitize=true"></p>

<p align="center">
| The foundation of everything.
</p>
<p align="center">
<a href="https://travis-ci.org/s1mpletru1h/php-events"><img src="https://travis-ci.org/s1mpletru1h/php-events.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/s1mpletru1h/events"><img src="https://poser.pugx.org/s1mpletru1h/events/d/total.svg" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/s1mpletru1h/events"><img src="https://poser.pugx.org/s1mpletru1h/events/v/stable.svg" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/s1mpletru1h/events"><img src="https://poser.pugx.org/s1mpletru1h/events/license.svg" alt="License"></a>
</p>

About Events
---
Events allow you to see everything that is happening in your application.

Events do this by allowing you to create syncronous, event-driven functional callback-response graphs in any application, by calling just two simple methods:

`$events->pub($event_name)` and `$events->sub($event_name, $callback)`

Colorful logging allows you to see what is going on in your application through your choice of standard out, text file logs, and in the near future, in-memory and sql/no-sql data stores.

### Events are Simple:
An `EVENT` is just an all-caps text string, with an optional colon separating the `:LEVEL` which can be any of the following strings, `['VERBOSE', 'DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL']`, and an arbitrary array of data. Events are published through the `$events->pub('EVENT:LEVEL', function($event, $data) {...});` function.

### Unlimited Play:
Every event can have unlimited callbacks bound to fire every time it is trigged, or only in certain conditions, with optional filters (coming soon), thus forming a functional directed event->callback graph. The only requirement is that the callback must take two arguments: the event name (arequired string) and the event data (an optional array). Event subscriber callback return values are captured and pushed onto an array, and returned from the pub function in an array that preserves the order in which they were returned.

### Sprinkles on Top:
Colorful logs make running your applicaiton fun to watch in standard out, log files per instance, or combined into a single master log file, or both (database event logging coming soon!).

Usage
---

```
// create an instance of events
$this->events = new Events('main', Events::DEBUG);

// subscribe to an event and trigger a callback that takes the $event and an array of optional $data
$this->events->sub('EVERYTHING_BEGINS', function(string $event, array $data = []) {
   return "$event really did happen";
});

// trigger the event, and all of it's callbacks fired in their subscribe order by publishing the event
$now = $this->events->pub('EVERYTHING_BEGINS', ['with' => 'a bang']);

// see the value of everything by publishing an event to a log level of your choice with 'EVENT:LEVEL'
$this->events->pub('RETURNED_VALUES:DEBUG', $now);
```

Testing
---

```
composer install
phpunit
```
