<?php

declare(strict_types=1);

/**
 * OpenTelemetry Auto-Instrumentation Configuration
 * 
 * Auto-instrumentation is configured via environment variables:
 * - OTEL_PHP_AUTOLOAD_ENABLED=true
 * - OTEL_SERVICE_NAME=menu-service
 * - OTEL_TRACES_EXPORTER=otlp
 * - OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
 * - OTEL_EXPORTER_OTLP_ENDPOINT=http://otel-collector:4318
 * - OTEL_PROPAGATORS=tracecontext,baggage
 * 
 * The auto-instrumentation packages automatically instrument:
 * - Symfony HTTP kernel (requests/responses)
 * - Symfony HTTP client (outgoing HTTP calls)
 * - PSR-18 HTTP clients
 * - Doctrine (database queries)
 * 
 * No manual code required - traces are created automatically!
 */

// Auto-instrumentation is handled by the OpenTelemetry SDK
// via environment variables set in docker-compose.yml
// 
// The following packages provide automatic instrumentation:
// - open-telemetry/opentelemetry-auto-symfony: Symfony framework
// - open-telemetry/opentelemetry-auto-psr18: PSR-18 HTTP clients
// - open-telemetry/opentelemetry-auto-http-async: Async HTTP clients
