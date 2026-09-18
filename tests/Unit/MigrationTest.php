<?php

namespace AmEveryWhere\Tests\Unit;

use PHPUnit\Framework\TestCase;
use AmEveryWhere\Core\Database\Installer;

class MigrationTest extends TestCase
{
    public function testLegacyOptionsMigration(): void
    {
        global $mock_wp_options;
        $mock_wp_options = [];

        // Set legacy options
        update_option('ranksavvy_ai_provider', 'anthropic');
        update_option('ranksavvy_setup_complete', '1');

        $installer = new Installer();
        $installer->migrateLegacyDataAndTables();

        // Verify options were migrated to ameverywhere_
        $this->assertSame('anthropic', get_option('ameverywhere_ai_provider'));
        $this->assertSame('1', get_option('ameverywhere_setup_complete'));
    }
}
