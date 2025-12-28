<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

/**
 * Replaces the _route attribute with the route template path for OpenTelemetry.
 * 
 * The opentelemetry-auto-symfony instrumentation uses $_route for http.route,
 * but OpenTelemetry semantic conventions require the matched route template
 * (low-cardinality) with placeholders, not logical route names.
 * 
 * This subscriber runs after the router but before the auto-instrumentation
 * reads the _route attribute, replacing the route name with the template path.
 * 
 * Examples:
 *   - /v1/billing/payments/{paymentId}/capture
 *   - /v1/billing/payment-intents/{id}
 *   - /v1/billing/refunds
 */
class OpenTelemetryRouteSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RouterInterface $router,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Run after RouterListener (priority 32) resolves the route
        return [
            KernelEvents::CONTROLLER => ['onKernelController', 0],
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $routeName = $request->attributes->get('_route');
        
        if (!$routeName) {
            return;
        }

        // Get the route template (path with placeholders)
        try {
            $routeCollection = $this->router->getRouteCollection();
            $route = $routeCollection->get($routeName);
            
            if ($route) {
                $routeTemplate = $route->getPath();
                
                // Replace _route with the template path so the auto-instrumentation
                // picks it up correctly when it sets http.route
                $request->attributes->set('_route', $routeTemplate);
            }
        } catch (\Throwable $e) {
            // Silently ignore - don't break the request for telemetry
        }
    }
}
