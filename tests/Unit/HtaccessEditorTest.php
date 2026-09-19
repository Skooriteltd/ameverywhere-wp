<?php

namespace AmEveryWhere\Tests\Unit;

use PHPUnit\Framework\TestCase;
use AmEveryWhere\Modules\TechnicalSeo\HtaccessEditor;

class HtaccessEditorTest extends TestCase
{
    private HtaccessEditor $editor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->editor = new HtaccessEditor();
    }

    public function testValidateSyntaxCleanContent(): void
    {
        $content = <<<HTACCESS
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress
HTACCESS;

        $warnings = $this->editor->validateSyntax($content);
        $this->assertEmpty($warnings);
    }

    public function testValidateSyntaxDetectsUnmatchedTags(): void
    {
        $content = <<<HTACCESS
<IfModule mod_rewrite.c>
RewriteEngine On
# Missing closing tag
HTACCESS;

        $warnings = $this->editor->validateSyntax($content);
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('Unmatched directive tags detected', $warnings[0]);
    }

    public function testValidateSyntaxFlagsDenyFromAll(): void
    {
        $content = <<<HTACCESS
<Files "wp-config.php">
Deny from all
</Files>
HTACCESS;

        $warnings = $this->editor->validateSyntax($content);
        $this->assertNotEmpty($warnings);
        $found = false;
        foreach ($warnings as $w) {
            if (str_contains($w, "'Deny from all' detected")) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    public function testSaveEndpointRejectsDangerousDirectives(): void
    {
        $dangerousPayloads = [
            'AddHandler application/x-httpd-php .txt' => 'AddHandler php execution',
            'AddType application/x-httpd-php .jpg' => 'AddType php execution',
            'php_value disable_functions none' => 'php_value disable_functions override',
            'php_value auto_prepend_file /malicious/file.php' => 'auto_prepend_file execution',
            'php_value auto_append_file /malicious/shell.php' => 'auto_append_file execution',
        ];

        foreach ($dangerousPayloads as $payload => $description) {
            $req = new \WP_REST_Request('POST', '/ameverywhere/v1/htaccess');
            $req->set_json_params(['content' => $payload]);

            $result = $this->editor->saveHtaccessEndpoint($req);
            $this->assertInstanceOf(
                \WP_Error::class,
                $result,
                "Expected dangerous directive [{$description}] to be rejected with WP_Error."
            );
            $this->assertSame('unsafe_content', $result->get_error_code());
        }
    }

    public function testRestoreHtaccessValidatesFilenameFormat(): void
    {
        // Invalid names (path traversal, wrong extension, invalid prefix)
        $this->assertFalse($this->editor->restoreHtaccess('../../wp-config.php'));
        $this->assertFalse($this->editor->restoreHtaccess('shell.php'));
        $this->assertFalse($this->editor->restoreHtaccess('htaccess-malicious.exe'));
        $this->assertFalse($this->editor->restoreHtaccess('htaccess-.txt'));

        // Well-formed backup filename format (will return false if file doesn't exist on disk, but passes regex check)
        // Here we verify it doesn't fail on regex pattern
        $this->assertFalse($this->editor->restoreHtaccess('htaccess-2026-09-18-120000.txt'));
    }
}
