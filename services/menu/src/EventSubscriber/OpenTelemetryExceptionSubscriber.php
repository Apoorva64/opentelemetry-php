<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\StatusCode;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Records exceptions to the current OpenTelemetry span.
 * 
 * This subscriber catches all exceptions thrown during request handling
 * and records them on the active span, including:
 * - Exception message and type
 * - Stack trace
 * - Error status code
 * 
 * This enables exceptions to appear in distributed traces for debugging.
 */
class OpenTelemetryExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        // Run early to capture the exception before other handlers might modify it
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 100],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        
        try {
            $span = Span::getCurrent();
            
            // Record the exception on the span
            $span->recordException($exception, [
                'exception.type' => get_class($exception),
                'exception.message' => $exception->getMessage(),
                'exception.code' => $exception->getCode(),
            ]);
            
            // Set span status to error
            $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
            
            // Add additional context attributes
            $span->setAttribute('error', true);
            $span->setAttribute('exception.type', get_class($exception));
            
            // Include file and line information
            $span->setAttribute('exception.file', $exception->getFile());
            $span->setAttribute('exception.line', $exception->getLine());
            
        } catch (\Throwable $e) {
            // Silently ignore tracing errors - don't break the exception flow
        }
    }
}
