<?php

declare(strict_types=1);

namespace App\Middleware;

use GuzzleHttp\Promise\PromiseInterface;
use OpenTelemetry\API\Globals;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class OpenTelemetryGuzzleMiddleware
{
    /**
     * Guzzle middleware that extracts custom OpenTelemetry attributes from request options
     * and sets them on the current active span.
     */
    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
            // Check if there are custom otel_attributes in the request options
            if (isset($options['otel_attributes']) && is_array($options['otel_attributes'])) {
                // Get the current active span
                $span = \OpenTelemetry\API\Trace\Span::getCurrent();
                
                // Set each attribute on the span
                foreach ($options['otel_attributes'] as $key => $value) {
                    if ($value !== null) {
                        $span->setAttribute($key, $value);
                    }
                }
            }

            // Continue with the request
            return $handler($request, $options);
        };
    }
}
