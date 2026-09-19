<?php

namespace AmEveryWhere\Tests\Unit;

use PHPUnit\Framework\TestCase;
use AmEveryWhere\Modules\Migration\MigrationManager;

class MigrationTest extends TestCase
{
    public function testCompetitorPluginDetection(): void
    {
        $manager = new MigrationManager();
        $detected = $manager->detectPlugins();

        $this->assertIsArray($detected);
    }

    public function testUnknownPluginMigrationReturnsError(): void
    {
        $manager = new MigrationManager();
        $result = $manager->migrate('nonexistent_plugin');

        $this->assertFalse($result['success']);
        $this->assertSame('Unknown plugin.', $result['message']);
    }
}
