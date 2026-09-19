<?php

namespace AmEveryWhere\Tests\Unit;

use PHPUnit\Framework\TestCase;
use AmEveryWhere\Modules\TechnicalSeo\RobotsTxtEditor;

class RobotsTxtEditorTest extends TestCase
{
    private RobotsTxtEditor $editor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->editor = new RobotsTxtEditor();

        global $mock_wp_options;
        $mock_wp_options = [];
    }

    public function testGetMergedBotListDefault(): void
    {
        $bots = $this->editor->getMergedBotList();
        $this->assertIsArray($bots);
        $this->assertContains('GPTBot', $bots);
        $this->assertContains('ClaudeBot', $bots);
        $this->assertContains('Google-Extended', $bots);
        $this->assertContains('PerplexityBot', $bots);
    }

    public function testGetMergedBotListWithCustomBots(): void
    {
        update_option('ameverywhere_custom_ai_bots', ['CustomBot-1', 'DeepSeek-Crawler']);

        $bots = $this->editor->getMergedBotList();
        $this->assertContains('CustomBot-1', $bots);
        $this->assertContains('DeepSeek-Crawler', $bots);
        $this->assertContains('GPTBot', $bots); // Built-in remains
    }

    public function testUpdateAiBotsSanitizationAndValidation(): void
    {
        $request = new \WP_REST_Request('POST', '/ameverywhere/v1/ai-bots');
        $request->set_json_params([
            'custom_bots' => [
                'ValidBot-1',
                'invalid bot with spaces',
                'valid_underscore_bot.v2',
                'injection<script>',
            ]
        ]);

        $response = $this->editor->updateAiBots($request);
        $data = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertContains('ValidBot-1', $data['merged']);
        $this->assertContains('valid_underscore_bot.v2', $data['merged']);
        $this->assertNotContains('invalid bot with spaces', $data['merged']);
        $this->assertNotContains('injection<script>', $data['merged']);
    }

    public function testFilterRobotsTxtDefault(): void
    {
        $output = $this->editor->filterRobotsTxt('', true);
        $this->assertStringContainsString('User-agent: *', $output);
        $this->assertStringContainsString('Disallow: /wp-admin/', $output);
        $this->assertStringNotContainsString('Block AI Crawlers', $output);
    }

    public function testFilterRobotsTxtWithAiBlocking(): void
    {
        update_option('ameverywhere_block_ai_bots', 'yes');
        update_option('ameverywhere_custom_ai_bots', ['MyTestCrawler']);

        $output = $this->editor->filterRobotsTxt('', true);

        $this->assertStringContainsString('# Block AI Crawlers and LLM Bots', $output);
        $this->assertStringContainsString("User-agent: GPTBot\nDisallow: /", $output);
        $this->assertStringContainsString("User-agent: ClaudeBot\nDisallow: /", $output);
        $this->assertStringContainsString("User-agent: MyTestCrawler\nDisallow: /", $output);
    }

    public function testFilterRobotsTxtWithAiTrainingOptOut(): void
    {
        update_option('ameverywhere_ai_training_optout', 'yes');

        $output = $this->editor->filterRobotsTxt('', true);

        $this->assertStringContainsString('# AI Training Data Opt-Out (AmEveryWhere)', $output);
        $this->assertStringContainsString('NoAI: 1', $output);
        $this->assertStringContainsString('NoImageAI: 1', $output);
    }

    public function testFilterRobotsTxtPreservesCustomDirectives(): void
    {
        $customRobots = "User-agent: *\nDisallow: /private/\nAllow: /public/\n";
        update_option('ameverywhere_robots_txt', $customRobots);

        $output = $this->editor->filterRobotsTxt('', true);
        $this->assertStringContainsString('Disallow: /private/', $output);
        $this->assertStringContainsString('Allow: /public/', $output);
    }
}
