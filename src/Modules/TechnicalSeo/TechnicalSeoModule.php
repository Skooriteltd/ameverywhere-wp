<?php

namespace RankSavvy\Modules\TechnicalSeo;

use RankSavvy\Core\Event\EventManager;

/**
 * Boots technical SEO routines including redirects, 404 monitoring, and their admin REST routes.
 */
class TechnicalSeoModule
{
    private EventManager $eventManager;
    private RedirectManager $redirectManager;
    private ErrorMonitor $errorMonitor;

    public function __construct(
        EventManager $eventManager, 
        RedirectManager $redirectManager,
        ErrorMonitor $errorMonitor
    ) {
        $this->eventManager = $eventManager;
        $this->redirectManager = $redirectManager;
        $this->errorMonitor = $errorMonitor;
    }

    public function boot(): void
    {
        // Hook into template_redirect to catch redirects and 404s before the template loads
        $this->eventManager->addAction('template_redirect', [$this->redirectManager, 'handleRedirects'], 1);
        $this->eventManager->addAction('template_redirect', [$this->errorMonitor, 'log404Errors'], 99);

        // Register custom REST routes for redirect management and 404 monitors
        $this->eventManager->addAction('rest_api_init', [$this->redirectManager, 'registerRoutes']);
        $this->eventManager->addAction('rest_api_init', [$this->errorMonitor, 'registerRoutes']);
    }
}
