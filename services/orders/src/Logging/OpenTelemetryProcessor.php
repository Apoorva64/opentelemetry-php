<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\Context\Context;

/**
 * OpenTelemetry Monolog Processor
 * 
 * Adds trace context (trace_id, span_id, trace_flags) to all log records,
 * enabling correlation between logs and distributed traces.
 * 
 * This processor extracts the current OpenTelemetry span context and adds
 * it to the log record's "extra" field. When used with JSON formatting,
 * this allows log aggregation systems (like Loki, Elasticsearch) to
 * correlate logs with traces in Jaeger/Tempo.
 */
class OpenTelemetryProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $span = Span::fromContext(Context::getCurrent());
        $spanContext = $span->getContext();
        
        // Interpolate PSR-3 placeholders like {route} with values from context
        $message = $record->message;
        $context = $record->context;
        if (!empty($context)) {
            $replacements = [];
            foreach ($context as $key => $value) {
                if (is_string($value) || is_numeric($value) || (is_object($value) && method_exists($value, '__toString'))) {
                    $replacements['{' . $key . '}'] = (string) $value;
                }
            }
            if (!empty($replacements)) {
                $message = strtr($message, $replacements);
            }
        }
        
        if (!$spanContext->isValid()) {
            return $record->with(message: $message);
        }

        $extra = $record->extra;
        $extra['trace_id'] = $spanContext->getTraceId();
        $extra['span_id'] = $spanContext->getSpanId();
        $extra['trace_flags'] = sprintf('%02x', $spanContext->getTraceFlags());
        
        // Add service name from environment if available
        $serviceName = getenv('OTEL_SERVICE_NAME');
        if ($serviceName !== false) {
            $extra['service.name'] = $serviceName;
        }

        return $record->with(message: $message, extra: $extra);
    }
}
