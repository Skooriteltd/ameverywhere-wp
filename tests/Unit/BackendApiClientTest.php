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

        $this->assertFalse($this->client->isConfigured());
    }

    public function testTestConnectionReturnsFallbackWhenNotConfigured(): void
    {
        delete_option('ameverywhere_api_key');

        $result = $this->client->testConnection();
        $this->assertFalse($result['success']);
        $this->assertSame('fallback', $result['mode']);
    }

    public function testGenerateAiFallsBackWhenNotConfigured(): void
    {
        delete_option('ameverywhere_api_key');

        $result = $this->client->generateAi(['prompt' => 'test']);
        $this->assertFalse($result['success']);
        $this->assertTrue($result['fallback']);
    }

    public function testApiKeyConfiguration(): void
    {
        delete_option('ameverywhere_api_key');
        $rawKey = 'sk-ameverywhere-valid-api-key';
        $encryptedKey = \AmEveryWhere\Core\Security\KeyVault::encrypt($rawKey);
        update_option('ameverywhere_api_key', $encryptedKey);

        $this->assertTrue($this->client->isConfigured());
        $this->assertSame($rawKey, $this->client->getApiKey());

        delete_option('ameverywhere_api_key');
    }
}
