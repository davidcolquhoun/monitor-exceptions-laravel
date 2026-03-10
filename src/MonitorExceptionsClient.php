<?php

declare(strict_types=1);

namespace Monitor\Exceptions;

use Symfony\Component\HttpFoundation\Request;
use Throwable;
use ErrorException;

class MonitorExceptionsClient
{
    private const SEND_EXCEPTIONS_TO = 'https://system.datasmugglers.com/api/v1/monitor/exception';

    private const SENSITIVE_HEADERS = [
        'authorization',
        'cookie',
        'cookie2',
        'x-api-key',
        'x-auth-token',
        'x-csrf-token',
        'x-csrftoken',
        'x-forwarded-for',
        'x-real-ip',
        'proxy-authorization',
    ];

    private const TIMEOUT = 1.0;

    public static function register(object $exceptions): void
    {
        $environmentId = trim((string) config('monitor-exceptions.environment_id', ''));
        $environmentKey = trim((string) config('monitor-exceptions.environment_key', ''));
        if ($environmentId === '' || $environmentKey === '') {
            return;
        }
        $exceptions->reportable(fn (Throwable $e) => self::reportException($e, $environmentId, $environmentKey));
    }

    private static function reportException(Throwable $e, string $environmentId, string $environmentKey): void
    {
        if (PHP_SAPI === 'cli') {
            $requestUrl = null;
            $requestMethod = null;
            $requestHeaders = [];
        } else {
            $httpRequest = Request::createFromGlobals();
            $requestUrl = $httpRequest->getSchemeAndHttpHost() . $httpRequest->getPathInfo();
            $requestMethod = $httpRequest->getMethod();
            $requestHeaders = [];
            foreach ($httpRequest->headers->all() as $name => $values) {
                if (in_array(strtolower($name), self::SENSITIVE_HEADERS, true)) {
                    continue;
                }
                $requestHeaders[$name] = is_array($values) ? implode(', ', $values) : (string) $values;
            }
        }

        $payload = [
            'environmentId' => $environmentId,
            'environmentKey' => $environmentKey,
            'reportedByHandler' => 'laravel',
            'errorSeverity' => $e instanceof ErrorException ? $e->getSeverity() : null,
            'exceptionClass' => $e::class,
            'errorMessage' => $e->getMessage(),
            'errorCode' => $e->getCode() !== 0 ? (string) $e->getCode() : null,
            'errorFile' => $e->getFile(),
            'errorLine' => $e->getLine(),
            'stackTrace' => $e->getTraceAsString(),
            'requestUrl' => $requestUrl,
            'requestMethod' => $requestMethod,
            'requestHeaders' => $requestHeaders,
        ];

        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/json',
                    'content' => json_encode($payload),
                    'timeout' => self::TIMEOUT,
                ],
            ]);
            @file_get_contents(self::SEND_EXCEPTIONS_TO, false, $context);
        } catch (Throwable) {
            // Don't let reporting failure affect the error response
        }
    }
}
