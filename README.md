# Monitor Exceptions (Laravel)

Lightweight Laravel client for reporting exceptions to the DataSmugglers monitoring API. Events appear in a dashboard where you can filter, search, and inspect them.

## Requirements

- PHP 8.1+
- Laravel 12.x

## Installation

```bash
composer require davidcolquhoun/monitor-exceptions-laravel
```

The package registers its service provider automatically. It merges default config from `config/monitor-exceptions.php`; you can override values in your app by publishing the config or setting env vars.

## Configuration

Set these in your `.env`:

```env
MONITOR_EXCEPTIONS_ENVIRONMENT_ID=myapp-prod
MONITOR_EXCEPTIONS_ENVIRONMENT_KEY=your-secret-key
```

- **environment_id**: Identifies the environment (e.g. `myapp-dev`, `myapp-prod`) for grouping in the DataSmugglers UI.
- **environment_key**: Secret key that authenticates this app with the DataSmugglers API.

If either is missing or empty, the client does not register and no exceptions are sent.

To publish the config file and edit it in your project:

```bash
php artisan vendor:publish --tag=monitor-exceptions-config
```

That creates `config/monitor-exceptions.php`, which reads from the same env vars by default.

## Usage

In your Laravel app, register the client in the exception handler. In `bootstrap/app.php`:

```php
use Illuminate\Foundation\Configuration\Exceptions;
use Monitor\Exceptions\MonitorExceptionsClient;

return Application::configure(basePath: dirname(__DIR__))
    // ... routing, middleware, etc.
    ->withExceptions(function (Exceptions $exceptions) {
        MonitorExceptionsClient::register($exceptions);
    })
    ->create();
```

Once registered, every exception reported to Laravel (e.g. via `report($e)`) is also sent to the monitoring API.

## What gets sent

The client does not send request bodies, client IP, or query strings. It sends a JSON payload with:

| Field               | Type         | Description |
|---------------------|-------------|-------------|
| `environmentId`     | string      | Your environment identifier. |
| `environmentKey`    | string      | Your environment key. |
| `reportedByHandler` | string      | Always `laravel`. |
| `errorSeverity`     | int \| null | PHP error level when available (e.g. `ErrorException`). |
| `exceptionClass`    | string      | Exception class name. |
| `errorMessage`      | string      | Exception message. |
| `errorCode`         | string \| null | Exception code when set. |
| `errorFile`         | string      | File where the exception was thrown. |
| `errorLine`         | int         | Line number. |
| `stackTrace`        | string      | Stack trace as a string. |
| `requestUrl`        | string \| null | Request URL without query string (null in CLI). |
| `requestMethod`     | string \| null | HTTP method (null in CLI). |
| `requestHeaders`    | array       | Request headers with sensitive ones stripped (empty in CLI). |

Sensitive headers (e.g. `authorization`, `cookie`, `x-api-key`) are removed before sending.

## Behaviour

- **Registration**: If `environment_id` or `environment_key` is empty, `register()` does nothing and no handler is attached.
- **CLI**: When running in CLI, `requestUrl`, `requestMethod`, and `requestHeaders` are null/empty.
- **Failures**: Errors inside the client (e.g. network) are caught and ignored so your app’s error handling is not affected.
- **Blocking**: The HTTP send is blocking (up to the configured timeout) so the report is sent before the request ends when possible.

## Testing

After configuring env vars, trigger an exception (e.g. `throw new \RuntimeException('Test');`) and check the DataSmugglers dashboard for the event.
