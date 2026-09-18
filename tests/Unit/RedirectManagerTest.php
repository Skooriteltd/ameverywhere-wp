<?php

namespace AmEveryWhere\Tests\Unit;

use PHPUnit\Framework\TestCase;
use AmEveryWhere\Modules\TechnicalSeo\RedirectManager;

class RedirectManagerTest extends TestCase
{
    private RedirectManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new RedirectManager();
    }

    public function testNormalizePathWithVariousFormats(): void
    {
        $reflection = new \ReflectionClass(RedirectManager::class);
        $method = $reflection->getMethod('normalizePath');
        $method->setAccessible(true);

        // Relative paths
        $this->assertSame('/old-page', $method->invoke($this->manager, 'old-page'));
        $this->assertSame('/old-page', $method->invoke($this->manager, '/old-page/'));
        $this->assertSame('/category/sub', $method->invoke($this->manager, 'category/sub'));

        // Full URLs
        $this->assertSame('/about-us', $method->invoke($this->manager, 'https://example.com/about-us'));
        $this->assertSame('/about-us', $method->invoke($this->manager, 'http://example.com/about-us/'));
    }

    public function testRegexPatternValidation(): void
    {
        $validPattern = '^/blog/(\d{4})/(.*)$';
        $compiled = '/' . str_replace('/', '\\/', ltrim($validPattern, '/')) . '/i';
        
        $this->assertNotFalse(@preg_match($compiled, ''));
        $this->assertSame(1, preg_match($compiled, '/blog/2026/my-seo-post'));

        // Test replacement
        $target = '/articles/$2';
        $replaced = preg_replace($compiled, $target, '/blog/2026/my-seo-post');
        $this->assertSame('/articles/my-seo-post', $replaced);
    }
}
