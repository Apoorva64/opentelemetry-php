<?php

declare(strict_types=1);

namespace App\Service;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;

class TracingService
{
    private ?TracerInterface $tracer = null;
    private ?TextMapPropagatorInterface $propagator = null;
    private string $serviceName;

    public function __construct(string $serviceName = 'unknown')
    {
        $this->serviceName = $serviceName;
        
        if ($this->isEnabled()) {
            $this->tracer = Globals::tracerProvider()->getTracer($serviceName, '1.0.0');
            $this->propagator = Globals::propagator();
        }
    }

    public function isEnabled(): bool
    {
        return getenv('OTEL_ENABLED') === 'true';
    }

    public function getTracer(): ?TracerInterface
    {
        return $this->tracer;
    }

    public function startSpan(string $name, int $kind = SpanKind::KIND_INTERNAL, array $attributes = []): ?SpanInterface
    {
        if (!$this->tracer) {
            return null;
        }

        $spanBuilder = $this->tracer->spanBuilder($name)
            ->setSpanKind($kind);

        foreach ($attributes as $key => $value) {
            $spanBuilder->setAttribute($key, $value);
        }

        return $spanBuilder->startSpan();
    }

    public function startServerSpan(string $name, array $headers = [], array $attributes = []): ?SpanInterface
    {
        if (!$this->tracer || !$this->propagator) {
            return null;
        }

        $context = $this->propagator->extract($headers);
        
        $spanBuilder = $this->tracer->spanBuilder($name)
            ->setParent($context)
            ->setSpanKind(SpanKind::KIND_SERVER);

        foreach ($attributes as $key => $value) {
            $spanBuilder->setAttribute($key, $value);
        }

        return $spanBuilder->startSpan();
    }

    public function startClientSpan(string $name, array $attributes = []): ?SpanInterface
    {
        return $this->startSpan($name, SpanKind::KIND_CLIENT, $attributes);
    }

    public function injectContext(array &$headers): void
    {
        if (!$this->propagator) {
            return;
        }

        $this->propagator->inject($headers);
    }

    public function recordException(SpanInterface $span, \Throwable $exception): void
    {
        $span->recordException($exception);
        $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
    }

    public function endSpan(?SpanInterface $span): void
    {
        if ($span) {
            $span->end();
        }
    }

    public function addEvent(?SpanInterface $span, string $name, array $attributes = []): void
    {
        if ($span) {
            $span->addEvent($name, $attributes);
        }
    }

    public function setStatus(?SpanInterface $span, int $code, string $description = ''): void
    {
        if ($span) {
            $span->setStatus($code, $description);
        }
    }

    public function setAttribute(?SpanInterface $span, string $key, $value): void
    {
        if ($span) {
            $span->setAttribute($key, $value);
        }
    }
}
