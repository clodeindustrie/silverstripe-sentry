# Usage

Once setup, everytime an `Exception` is thrown or PHP itself shuts down via `trigger_error()` etc or you "manually" trigger a log message to be sent to your Sentry-compatible server, all available data is automatically sent to the server.s

In addition to the module reporting all thrown `Exception`s which result in a stacktrace within Sentry or Glitchtip, the module can be used as a simple logger with all the benefits of Sentry's tagging and grouping. See the examples below.

## Environment

For "manual" error-reporting, you can augment your message with some context. If an environment is not specified,
the default is to use the return value of `Director::get_environment_type()`.

Silverstripe v4+ uses `Monolog` and individual handlers for logging. Once you instantiate a `Logger` object, you have access to `Monolog`'s public API.

    $config = ['env' => 'live'];
    $logger = Injector::inst()->createWithArgs(Logger::class, ['error-log'])
        ->pushHandler(SentryHandler::create());

    // Send an `ERROR` level message
    $logger->error($message, $config);

    // Send a `WARN` level message
    $logger->warning($message, $config);

    // Send an `INFO` level message
    $logger->info($message, $config);

## Log Levels

You can set the minimum log-level you're interested in, using the `log_level` config, the module default is to report anything more severe than a `DEBUG`:

```
PhpTek\Sentry\Handler\SentryHandler:
  # One of the permitted severities: DEBUG|INFO|WARNING|ERROR|FATAL
  log_level: ERROR
```

Building on top of the "manual" logging examples above, you can configure these to send only errors of a specific severity:

```
$logger = Injector::inst()->createWithArgs(Logger::class, ['error-log'])
    ->pushHandler(SentryHandler::create('INFO')); // Send errors >= INFO

$logger->info('TEST: INFO');    // Sent
$logger->warning('TEST: WARN'); // Sent
$logger->error('TEST: ERROR');  // Sent
```

```
$logger = Injector::inst()->createWithArgs(Logger::class, ['error-log'])
    ->pushHandler(SentryHandler::create('WARNING')); // Send errors >= WARNING

$logger->info('TEST: INFO');    // Not sent
$logger->warning('TEST: WARN'); // Sent
$logger->error('TEST: ERROR');  // Sent
```

```
$logger = Injector::inst()->createWithArgs(Logger::class, ['error-log'])
    ->pushHandler(SentryHandler::create('ERROR')); // Send errors >= ERROR

$logger->info('TEST: INFO');    // Not sent
$logger->warning('TEST: WARN'); // Not sent
$logger->error('TEST: ERROR');  // Sent
```

Further; passing a severity in this way trumps any YML config you have set:

YML:
```
PhpTek\Sentry\Handler\SentryHandler:
  log_level: 'ERROR'
```

PHP:
```
$logger = Injector::inst()->createWithArgs(Logger::class, ['error-log'])
    ->pushHandler(SentryHandler::create()); // Send errors >= ERROR

$logger->info('TEST: INFO');    // Not sent
$logger->warning('TEST: WARN'); // Not sent
$logger->error('TEST: ERROR');  // Sent
```

PHP:
```
$logger = Injector::inst()->createWithArgs(Logger::class, ['error-log'])
    ->pushHandler(SentryHandler::create('INFO)); // Send errors >= INFO (despite what's set in YML)

$logger->info('TEST: INFO');    // Sent
$logger->warning('TEST: WARN'); // Sent
$logger->error('TEST: ERROR');  // Sent
```

You can test how these messages appear in Sentry itself, by running the test task:

    ./vendor/bin/sake dev/tasks/PhpTek-Sentry-Tasks-SentryTestConnectionTask

## Tags and Extras

The Sentry API allows for custom key-value pairs to be recorded against each message that it is sent.
These are known as "tags" and "extras" which allows for fine-grained grouping and filtering of messages via the Sentry or Glitchtip UI.

Note: It makes no sense to send hugely varying data in a tag. If it's unlikely that a tag you wish to send is ever going to be repeated, don't send it as a tag. Look at using the "Extras" feature (also described below)
instead.

    $config = [
        // Appears in server's "Details" tab on RHS and in the lozenges, located at the top
        'env' => 'live',
        // Appears in the "Tags" tab as its own block and in the lozenges on the "Details" tab, located at the top
        'tags' => [
            'Unique-ID' => 44
        ],
        // Appears in the "Details" tab under "Additional Data"
        extra => [
            'Moon-Phase' => 'Full',
            'Tummy-Status' => 'Empty',
            'Cats' => 'Are furry'
        ]
    ];
    $logger = Injector::inst()->createWithArgs(Logger::class, ['error-log'])
        ->pushHandler(SentryHandler::create(null, true, $config));

    // Send an `ERROR` level message
    $logger->error($message);

    // Send a `WARN` level message
    $logger->warning($message);

    // Send an `INFO` level message
    $logger->info($message);

    // Alternative syntax to send an `ERROR` level message
    $logger->log('ERROR', 'Help, my curry is too hot. I only asked for mild.');

The module comes with some pre-defined **tags** and **extras** that are always shown in the "Tags" tab and in the tags "lozenges" at the top of the main "Details" tab within Sentry's UI itself:

### Default Tags

* **app**: A string containing the project's name as defined in `composer.json`, branch-name and commit
* **phptek.sentry.version**: The version of the `phptek/sentry` package installed
* **request.method**: The HTTP method used for the request that generated the Sentry message
* **request.type**: The type of request e.g. "Ajax"
* **silverstripe.framework.version**: The version of `silverstripe/framework` package installed
* **php.sapi**: The PHP SAPI in use at the time the message was sent to Sentry

### Default Extras

* **PHP Peak Memory**: The amount of peak memory (in Mb) consumed at the time the message was sent

### Default User Data

* **Email**: The value of the current Silverstripe user's `Member::Email` field (If not scrubbed in Sentry's settings)
* **ID**: The value of the current Silverstripe user's `Member::ID` field
* **IP Address**: The value of the originating IP address of the request at the time the message was sent to Sentry

## Stacktraces

By default, the module will render Sentry's own stacktraces into the server's UI. However, you can configure the module to skip recording Silverstripe's own debugging internals as well as those of the module:

```
PhpTek\Sentry\Handler\SentryHandler:
  custom_stacktrace: true
```

Note that this feature should be considered experimental/incomplete. It is unable to fully render method/function calls when the module is set in this mode.

## Releases

You can send your Sentry or Glitchtip server your app's release-tag which causes it to attempt to relate bugs to a specific release. This makes it even easier to track the point at which an issue surfaced.

Note: The module's env variable name (below) is clearly not going to fit with your team's standards, regardless of what it's named. In this case it's expected that projects simply comprise two ENV vars which are assigned the same value in the same way within your CI setup.

```
SENTRY_RELEASE_TAG=1.2.3.4
```

## Proxies

Should your app require outgoing traffic to be passed through a proxy, the following config will work:

    # Proxy constants
    PhpTek\Sentry\Adaptor\SentryAdaptor:
      opts:
        http_proxy:
          host: '`MY_OUTBOUND_PROXY`'
          port: '`MY_OUTBOUND_PROXY_PORT`'
