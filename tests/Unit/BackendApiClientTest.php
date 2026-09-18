<?php

namespace AmEveryWhere\Tests\Unit;

use PHPUnit\Framework\TestCase;
use AmEveryWhere\Core\Api\BackendApiClient;

class BackendApiClientTest extends TestCase
{
    private BackendApiClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new BackendApiClient();
    }

    public function testDefaultApiUrl(): void
    {
        $this->assertSame('https://api.ameverywhere.com/v1', $this->client->getApiUrl());
    }

    public function testIsConfiguredReturnsFalseWhenNoKey(): void
    {
        delete_option('ameverywhere_api_key');
        delete_option('ranksavvy_api_key');

        $this->assertFalse($this->client->isConfigured());
    }

    public function testTestConnectionReturnsFallbackWhenNotConfigured(): void
    {
        delete_option('ameverywhere_api_key');
        delete_option('ranksavvy_api_key');

        $result = $this->client->testConnection();
        $this->assertFalse($result['success']);
        $this->assertSame('fallback', $result['mode']);
    }

    public function testGenerateAiFallsBackWhenNotConfigured(): void
    {
        delete_option('ameverywhere_api_key');
        delete_option('ranksavvy_api_key');

        $result = $this->client->generateAi(['prompt' => 'test']);
        $this->assertFalse($result['success']);
        $this->assertTrue($result['fallback']);
    }
}
