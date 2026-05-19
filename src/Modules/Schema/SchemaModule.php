<?php

namespace RankSavvy\Modules\Schema;

use RankSavvy\Core\Event\EventManager;

class SchemaModule
{
    private EventManager $eventManager;
    private SchemaGenerator $schemaGenerator;

    public function __construct(EventManager $eventManager, SchemaGenerator $schemaGenerator)
    {
        $this->eventManager = $eventManager;
        $this->schemaGenerator = $schemaGenerator;
    }

    public function boot(): void
    {
        $this->eventManager->addAction('wp_footer', [$this->schemaGenerator, 'outputSchema'], 10);
    }
}
