<?php

namespace RankSavvy\Modules\TechnicalSeo;

use RankSavvy\Core\Event\EventManager;

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
    }
}
