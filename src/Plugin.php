<?php

namespace AmEveryWhere;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AmEveryWhere\Core\Container\Container;
use AmEveryWhere\Core\Api\BackendApiClient;
use AmEveryWhere\Modules\Seo\SeoModule;
use AmEveryWhere\Modules\Seo\MetaTagsGenerator;
use AmEveryWhere\Modules\Seo\OpenGraphGenerator;
use AmEveryWhere\Modules\Admin\AdminModule;
use AmEveryWhere\Modules\Admin\AdminMenu;
use AmEveryWhere\Modules\Sitemap\SitemapModule;
use AmEveryWhere\Modules\Sitemap\SitemapRouteManager;
use AmEveryWhere\Modules\Sitemap\SitemapGenerator;
use AmEveryWhere\Modules\Sitemap\NewsSitemapGenerator;
use AmEveryWhere\Modules\Sitemap\VideoSitemapGenerator;
use AmEveryWhere\Modules\Indexing\IndexingModule;
use AmEveryWhere\Modules\TechnicalSeo\TechnicalSeoModule;
use AmEveryWhere\Modules\TechnicalSeo\RedirectManager;
use AmEveryWhere\Modules\TechnicalSeo\ErrorMonitor;
use AmEveryWhere\Modules\Schema\SchemaModule;
use AmEveryWhere\Modules\Schema\SchemaGenerator;
use AmEveryWhere\Modules\ContentAssistant\ContentAssistantModule;
use AmEveryWhere\Modules\Breadcrumbs\BreadcrumbRenderer;
use AmEveryWhere\Modules\ImageSeo\ImageSeoModule;
use AmEveryWhere\Modules\TechnicalSeo\RobotsTxtEditor;
use AmEveryWhere\Modules\Migration\MigrationManager;
use AmEveryWhere\Modules\Onboarding\SetupWizard;
use AmEveryWhere\Modules\Social\SocialModule;
use AmEveryWhere\Modules\Ai\LlmsTxtGenerator;
use AmEveryWhere\Modules\Admin\BulkMetaEditor;
use AmEveryWhere\Modules\Compliance\CookieBannerModule;

class Plugin {

	private static ?Plugin $instance = null;
	private Container $container;

	private function __construct() {
		$this->container = new Container();
	}

	public static function getInstance(): Plugin {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		// Load translation textdomain
		add_action(
			'init',
			function () {
				load_plugin_textdomain(
					'ameverywhere',
					false,
					dirname( plugin_basename( AMEVERYWHERE_PLUGIN_FILE ) ) . '/languages'
				);
			}
		);

		$this->registerServices();

		/** @var \AmEveryWhere\Core\Queue\QueueManager $queueManager */
		$queueManager = $this->container->get( 'queue_manager' );
		$queueManager->boot();

		$this->bootModules();
	}

	private function registerServices(): void {
		// Core services (Events, Queues, Backend API Client, etc.)
		$this->container->singleton( 'event_manager', \AmEveryWhere\Core\Event\EventManager::class );
		$this->container->singleton( 'backend_api_client', BackendApiClient::class );

		// Register SEO Module Dependencies
		$this->container->singleton( 'meta_tags_generator', MetaTagsGenerator::class );
		$this->container->singleton( 'open_graph_generator', OpenGraphGenerator::class );

		$this->container->singleton(
			'seo_module',
			function () {
				return new SeoModule(
					$this->container->get( 'event_manager' ),
					$this->container->get( 'meta_tags_generator' ),
					$this->container->get( 'open_graph_generator' )
				);
			}
		);

		// Register Admin Module Dependencies
		$this->container->singleton( 'admin_menu', AdminMenu::class );
		$this->container->singleton(
			'admin_module',
			function () {
				return new AdminModule(
					$this->container->get( 'event_manager' ),
					$this->container->get( 'admin_menu' )
				);
			}
		);

		// Register Sitemap Module Dependencies
		$this->container->singleton( 'sitemap_generator', SitemapGenerator::class );
		$this->container->singleton( 'news_sitemap_generator', NewsSitemapGenerator::class );
		$this->container->singleton( 'video_sitemap_generator', VideoSitemapGenerator::class );

		$this->container->singleton(
			'sitemap_route_manager',
			function () {
				return new SitemapRouteManager(
					$this->container->get( 'sitemap_generator' ),
					$this->container->get( 'news_sitemap_generator' ),
					$this->container->get( 'video_sitemap_generator' )
				);
			}
		);

		$this->container->singleton(
			'sitemap_module',
			function () {
				return new SitemapModule(
					$this->container->get( 'event_manager' ),
					$this->container->get( 'sitemap_route_manager' )
				);
			}
		);

		// Register Indexing Module Dependencies
		$this->container->singleton( 'queue_manager', \AmEveryWhere\Core\Queue\QueueManager::class );
		$this->container->singleton(
			'indexing_module',
			function () {
				return new IndexingModule(
					$this->container->get( 'event_manager' ),
					$this->container->get( 'queue_manager' )
				);
			}
		);

		// Register Technical SEO Module Dependencies
		$this->container->singleton( 'redirect_manager', RedirectManager::class );
		$this->container->singleton( 'error_monitor', ErrorMonitor::class );

		$this->container->singleton(
			'technical_seo_module',
			function () {
				return new TechnicalSeoModule(
					$this->container->get( 'event_manager' ),
					$this->container->get( 'redirect_manager' ),
					$this->container->get( 'error_monitor' )
				);
			}
		);

		// Register Schema Module Dependencies
		$this->container->singleton( 'schema_generator', SchemaGenerator::class );
		$this->container->singleton(
			'schema_module',
			function () {
				return new SchemaModule(
					$this->container->get( 'event_manager' ),
					$this->container->get( 'schema_generator' )
				);
			}
		);

		// Register Content Assistant Module
		$this->container->singleton(
			'content_assistant_module',
			function () {
				return new ContentAssistantModule(
					$this->container->get( 'event_manager' )
				);
			}
		);

		// Register Breadcrumb Renderer
		$this->container->singleton(
			'breadcrumb_renderer',
			function () {
				return new BreadcrumbRenderer();
			}
		);

		// Register Image SEO Module
		$this->container->singleton(
			'image_seo_module',
			function () {
				return new ImageSeoModule(
					$this->container->get( 'event_manager' )
				);
			}
		);

		// Register Robots.txt Editor
		$this->container->singleton(
			'robots_txt_editor',
			function () {
				return new RobotsTxtEditor();
			}
		);

		// Register Migration Manager
		$this->container->singleton(
			'migration_manager',
			function () {
				return new MigrationManager();
			}
		);

		// Register Setup Wizard
		$this->container->singleton(
			'setup_wizard',
			function () {
				return new SetupWizard();
			}
		);

		// Register Social Module
		$this->container->singleton(
			'social_module',
			function () {
				return new SocialModule(
					$this->container->get( 'event_manager' )
				);
			}
		);

		// Register llms.txt Generator
		$this->container->singleton(
			'llms_txt_generator',
			function () {
				return new LlmsTxtGenerator();
			}
		);

		// Register Bulk Meta Editor
		$this->container->singleton(
			'bulk_meta_editor',
			function () {
				return new BulkMetaEditor();
			}
		);

		// Register Cookie Banner Module
		$this->container->singleton(
			'cookie_banner_module',
			function () {
				return new CookieBannerModule();
			}
		);

		// ── Backlog Modules ───────────────────────────────────────────────────
		$this->container->singleton( 'orphaned_content_finder', \AmEveryWhere\Modules\TechnicalSeo\OrphanedContentFinder::class );
		$this->container->singleton( 'image_object_schema', \AmEveryWhere\Modules\ImageSeo\ImageObjectSchema::class );
		$this->container->singleton( 'image_filename_enforcer', \AmEveryWhere\Modules\ImageSeo\ImageFilenameEnforcer::class );
		$this->container->singleton( 'technical_seo_audit_engine', \AmEveryWhere\Modules\Admin\TechnicalSeoAuditEngine::class );
		$this->container->singleton( 'ccpa_privacy_tools', \AmEveryWhere\Modules\Compliance\CcpaPrivacyTools::class );
		$this->container->singleton( 'internal_link_suggester', \AmEveryWhere\Modules\ContentAssistant\InternalLinkSuggester::class );
		$this->container->singleton( 'usage_metering_manager', \AmEveryWhere\Modules\Ai\UsageMeteringManager::class );
		$this->container->singleton( 'upgrade_prompt_manager', \AmEveryWhere\Modules\Admin\UpgradePromptManager::class );
		$this->container->singleton( 'htaccess_editor', \AmEveryWhere\Modules\TechnicalSeo\HtaccessEditor::class );
		$this->container->singleton( 'ai_disclosure_manager', \AmEveryWhere\Modules\Compliance\AiDisclosureManager::class );
		$this->container->singleton( 'index_status_checker', \AmEveryWhere\Modules\Indexing\IndexStatusChecker::class );
		$this->container->singleton( 'schema_output_validator', \AmEveryWhere\Modules\Schema\SchemaOutputValidator::class );
		$this->container->singleton( 'stale_cornerstone_detector', \AmEveryWhere\Modules\ContentAssistant\StaleCornerStoneDetector::class );
		$this->container->singleton( 'audit_scheduler', \AmEveryWhere\Modules\ContentAssistant\AuditScheduler::class );
		$this->container->singleton( 'search_intent_classifier', \AmEveryWhere\Modules\ContentAssistant\SearchIntentClassifier::class );
		$this->container->singleton( 'inclusive_language_checker', \AmEveryWhere\Modules\ContentAssistant\InclusiveLanguageChecker::class );
		$this->container->singleton( 'word_complexity_scorer', \AmEveryWhere\Modules\ContentAssistant\WordComplexityScorer::class );
		$this->container->singleton( 'bing_indexnow_api', \AmEveryWhere\Modules\Indexing\BingIndexNowApi::class );

		// ── Phase 2 backlog services ───────────────────────────────────────────
		$this->container->singleton( 'morphological_keyword_matcher', \AmEveryWhere\Modules\ContentAssistant\MorphologicalKeywordMatcher::class );
		$this->container->singleton( 'woocommerce_product_schema', \AmEveryWhere\Modules\Schema\WooCommerceProductSchema::class );
		$this->container->singleton( 'headless_seo_endpoints', \AmEveryWhere\Modules\Api\HeadlessSeoEndpoints::class );
		$this->container->singleton( 'content_gap_analyser', \AmEveryWhere\Modules\ContentAssistant\ContentGapAnalyser::class );
		$this->container->singleton( 'frontend_seo_inspector', \AmEveryWhere\Modules\Admin\FrontendSeoInspector::class );
		$this->container->singleton( 'sitemap_priority_manager', \AmEveryWhere\Modules\Sitemap\SitemapPriorityManager::class );
		$this->container->singleton( 'llm_writing_assistant', \AmEveryWhere\Modules\ContentAssistant\LlmWritingAssistant::class );
		$this->container->singleton( 'keyword_cannibalization_detector', \AmEveryWhere\Modules\Analytics\KeywordCannibalizationDetector::class );
		$this->container->singleton( 'seo_audit_history_log', \AmEveryWhere\Modules\Admin\SeoAuditHistoryLog::class );
		$this->container->singleton( 'custom_seo_user_roles', \AmEveryWhere\Modules\Admin\CustomSeoUserRoles::class );
		$this->container->singleton( 'pagespeed_dashboard', \AmEveryWhere\Modules\Analytics\PageSpeedDashboard::class );
		$this->container->singleton( 'competitor_seo_importer', \AmEveryWhere\Modules\Import\CompetitorSeoImporter::class );
		$this->container->singleton( 'multisite_network_seo', \AmEveryWhere\Modules\Analytics\MultisiteNetworkSeo::class );
		$this->container->singleton( 'google_search_console', \AmEveryWhere\Modules\Analytics\GoogleSearchConsoleIntegration::class );
		$this->container->singleton( 'bing_webmaster', \AmEveryWhere\Modules\Analytics\BingWebmasterIntegration::class );
		$this->container->singleton( 'keyword_rank_tracker', \AmEveryWhere\Modules\Analytics\KeywordRankTracker::class );
	}

	private function bootModules(): void {
		/** @var SeoModule $seoModule */
		$seoModule = $this->container->get( 'seo_module' );
		$seoModule->boot();

		/** @var AdminModule $adminModule */
		$adminModule = $this->container->get( 'admin_module' );
		$adminModule->boot();

		/** @var SitemapModule $sitemapModule */
		$sitemapModule = $this->container->get( 'sitemap_module' );
		$sitemapModule->boot();

		/** @var IndexingModule $indexingModule */
		$indexingModule = $this->container->get( 'indexing_module' );
		$indexingModule->boot();

		/** @var TechnicalSeoModule $technicalSeoModule */
		$technicalSeoModule = $this->container->get( 'technical_seo_module' );
		$technicalSeoModule->boot();

		/** @var SchemaModule $schemaModule */
		$schemaModule = $this->container->get( 'schema_module' );
		$schemaModule->boot();

		/** @var ContentAssistantModule $contentAssistantModule */
		$contentAssistantModule = $this->container->get( 'content_assistant_module' );
		$contentAssistantModule->boot();

		/** @var BreadcrumbRenderer $breadcrumbRenderer */
		$breadcrumbRenderer = $this->container->get( 'breadcrumb_renderer' );
		$breadcrumbRenderer->register();

		/** @var ImageSeoModule $imageSeoModule */
		$imageSeoModule = $this->container->get( 'image_seo_module' );
		$imageSeoModule->boot();

		/** @var RobotsTxtEditor $robotsTxtEditor */
		$robotsTxtEditor = $this->container->get( 'robots_txt_editor' );
		$robotsTxtEditor->register();

		// Register Robots.txt REST routes
		add_action( 'rest_api_init', array( $robotsTxtEditor, 'registerRoutes' ) );

		/** @var MigrationManager $migrationManager */
		$migrationManager = $this->container->get( 'migration_manager' );
		add_action( 'rest_api_init', array( $migrationManager, 'registerRoutes' ) );

		/** @var SetupWizard $setupWizard */
		$setupWizard = $this->container->get( 'setup_wizard' );
		$setupWizard->register();

		/** @var SocialModule $socialModule */
		$socialModule = $this->container->get( 'social_module' );
		$socialModule->boot();

		/** @var LlmsTxtGenerator $llmsTxtGenerator */
		$llmsTxtGenerator = $this->container->get( 'llms_txt_generator' );
		$llmsTxtGenerator->boot();
		add_action( 'rest_api_init', array( $llmsTxtGenerator, 'registerRestRoutes' ) );

		/** @var BulkMetaEditor $bulkMetaEditor */
		$bulkMetaEditor = $this->container->get( 'bulk_meta_editor' );
		add_action( 'rest_api_init', array( $bulkMetaEditor, 'registerRestRoutes' ) );

		/** @var CookieBannerModule $cookieBannerModule */
		$cookieBannerModule = $this->container->get( 'cookie_banner_module' );
		$cookieBannerModule->boot();

		// HTML Sitemap shortcode: [ameverywhere_sitemap]
		$htmlSitemapShortcode = new \AmEveryWhere\Modules\Sitemap\HtmlSitemapShortcode();
		$htmlSitemapShortcode->register();

		/** @var \AmEveryWhere\Modules\TechnicalSeo\OrphanedContentFinder $orphanedFinder */
		$orphanedFinder = $this->container->get( 'orphaned_content_finder' );
		$orphanedFinder->register();

		/** @var \AmEveryWhere\Modules\ImageSeo\ImageObjectSchema $imageObjectSchema */
		$imageObjectSchema = $this->container->get( 'image_object_schema' );
		$imageObjectSchema->register();

		/** @var \AmEveryWhere\Modules\ImageSeo\ImageFilenameEnforcer $imageFilenameEnforcer */
		$imageFilenameEnforcer = $this->container->get( 'image_filename_enforcer' );
		$imageFilenameEnforcer->register();

		/** @var \AmEveryWhere\Modules\Admin\TechnicalSeoAuditEngine $auditEngine */
		$auditEngine = $this->container->get( 'technical_seo_audit_engine' );
		$auditEngine->register();

		/** @var \AmEveryWhere\Modules\Compliance\CcpaPrivacyTools $ccpaTools */
		$ccpaTools = $this->container->get( 'ccpa_privacy_tools' );
		$ccpaTools->boot();

		/** @var \AmEveryWhere\Modules\ContentAssistant\InternalLinkSuggester $linkSuggester */
		$linkSuggester = $this->container->get( 'internal_link_suggester' );
		$linkSuggester->register();

		/** @var \AmEveryWhere\Modules\Ai\UsageMeteringManager $usageMetering */
		$usageMetering = $this->container->get( 'usage_metering_manager' );
		$usageMetering->boot();

		/** @var \AmEveryWhere\Modules\Admin\UpgradePromptManager $upgradePrompt */
		$upgradePrompt = $this->container->get( 'upgrade_prompt_manager' );
		$upgradePrompt->boot();

		/** @var \AmEveryWhere\Modules\TechnicalSeo\HtaccessEditor $htaccessEditor */
		$htaccessEditor = $this->container->get( 'htaccess_editor' );
		$htaccessEditor->register();

		/** @var \AmEveryWhere\Modules\Compliance\AiDisclosureManager $aiDisclosure */
		$aiDisclosure = $this->container->get( 'ai_disclosure_manager' );
		$aiDisclosure->register();

		/** @var \AmEveryWhere\Modules\Indexing\IndexStatusChecker $indexStatusChecker */
		$indexStatusChecker = $this->container->get( 'index_status_checker' );
		$indexStatusChecker->register();

		/** @var \AmEveryWhere\Modules\Schema\SchemaOutputValidator $schemaValidator */
		$schemaValidator = $this->container->get( 'schema_output_validator' );
		$schemaValidator->register();

		/** @var \AmEveryWhere\Modules\ContentAssistant\StaleCornerStoneDetector $staleDetector */
		$staleDetector = $this->container->get( 'stale_cornerstone_detector' );
		$staleDetector->register();

		/** @var \AmEveryWhere\Modules\ContentAssistant\AuditScheduler $auditScheduler */
		$auditScheduler = $this->container->get( 'audit_scheduler' );
		$auditScheduler->register();

		/** @var \AmEveryWhere\Modules\ContentAssistant\SearchIntentClassifier $intentClassifier */
		$intentClassifier = $this->container->get( 'search_intent_classifier' );
		$intentClassifier->register();

		/** @var \AmEveryWhere\Modules\ContentAssistant\InclusiveLanguageChecker $inclusiveChecker */
		$inclusiveChecker = $this->container->get( 'inclusive_language_checker' );
		$inclusiveChecker->register();

		/** @var \AmEveryWhere\Modules\ContentAssistant\WordComplexityScorer $complexityScorer */
		$complexityScorer = $this->container->get( 'word_complexity_scorer' );
		$complexityScorer->register();

		/** @var \AmEveryWhere\Modules\Indexing\BingIndexNowApi $bingIndexNow */
		$bingIndexNow = $this->container->get( 'bing_indexnow_api' );
		$bingIndexNow->register();

		// ── Boot Phase 2 Backlog Modules ─────────────────────────────────────
		/** @var \AmEveryWhere\Modules\ContentAssistant\MorphologicalKeywordMatcher $morphoMatcher */
		$morphoMatcher = $this->container->get( 'morphological_keyword_matcher' );
		$morphoMatcher->register();

		/** @var \AmEveryWhere\Modules\Schema\WooCommerceProductSchema $wooSchema */
		$wooSchema = $this->container->get( 'woocommerce_product_schema' );
		$wooSchema->register();

		/** @var \AmEveryWhere\Modules\Api\HeadlessSeoEndpoints $headlessEndpoints */
		$headlessEndpoints = $this->container->get( 'headless_seo_endpoints' );
		$headlessEndpoints->register();

		/** @var \AmEveryWhere\Modules\ContentAssistant\ContentGapAnalyser $contentGap */
		$contentGap = $this->container->get( 'content_gap_analyser' );
		$contentGap->register();

		/** @var \AmEveryWhere\Modules\Admin\FrontendSeoInspector $frontendInspector */
		$frontendInspector = $this->container->get( 'frontend_seo_inspector' );
		$frontendInspector->register();

		/** @var \AmEveryWhere\Modules\Sitemap\SitemapPriorityManager $sitemapPriority */
		$sitemapPriority = $this->container->get( 'sitemap_priority_manager' );
		$sitemapPriority->register();

		/** @var \AmEveryWhere\Modules\ContentAssistant\LlmWritingAssistant $llmAssistant */
		$llmAssistant = $this->container->get( 'llm_writing_assistant' );
		$llmAssistant->register();

		/** @var \AmEveryWhere\Modules\Analytics\KeywordCannibalizationDetector $cannibalizationDetector */
		$cannibalizationDetector = $this->container->get( 'keyword_cannibalization_detector' );
		$cannibalizationDetector->register();

		/** @var \AmEveryWhere\Modules\Admin\SeoAuditHistoryLog $auditHistoryLog */
		$auditHistoryLog = $this->container->get( 'seo_audit_history_log' );
		$auditHistoryLog->register();

		/** @var \AmEveryWhere\Modules\Admin\CustomSeoUserRoles $userRoles */
		$userRoles = $this->container->get( 'custom_seo_user_roles' );
		$userRoles->register();

		/** @var \AmEveryWhere\Modules\Analytics\PageSpeedDashboard $pageSpeed */
		$pageSpeed = $this->container->get( 'pagespeed_dashboard' );
		$pageSpeed->register();

		/** @var \AmEveryWhere\Modules\Import\CompetitorSeoImporter $competitorImporter */
		$competitorImporter = $this->container->get( 'competitor_seo_importer' );
		$competitorImporter->register();

		/** @var \AmEveryWhere\Modules\Analytics\MultisiteNetworkSeo $multisiteNetworkSeo */
		$multisiteNetworkSeo = $this->container->get( 'multisite_network_seo' );
		$multisiteNetworkSeo->register();

		/** @var \AmEveryWhere\Modules\Analytics\GoogleSearchConsoleIntegration $gsc */
		$gsc = $this->container->get( 'google_search_console' );
		$gsc->register();

		$bing = $this->container->get( 'bing_webmaster' );
		$bing->register();

		/** @var \AmEveryWhere\Modules\Analytics\KeywordRankTracker $rankTracker */
		$rankTracker = $this->container->get( 'keyword_rank_tracker' );
		$rankTracker->register();

		// Broken Link Checker (BL-018)
		$brokenLinkChecker = new \AmEveryWhere\Modules\TechnicalSeo\BrokenLinkChecker();
		$brokenLinkChecker->register();
	}

	public function getContainer(): Container {
		return $this->container;
	}

	public static function activate( bool $networkWide = false ): void {
		if ( $networkWide && is_multisite() ) {
			$siteIds = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);
			foreach ( $siteIds as $siteId ) {
				switch_to_blog( (int) $siteId );
				self::activateSite();
				restore_current_blog();
			}
			return;
		}

		self::activateSite();
	}

	private static function activateSite(): void {
		// 1. Run schema installation
		$installer = new \AmEveryWhere\Core\Database\Installer();
		$installer->install();

		// 2. Generate persistent encryption salt for KeyVault
		\AmEveryWhere\Core\Security\KeyVault::ensureSaltExists();

		// 3. Cache the 404 table existence flag
		\AmEveryWhere\Modules\TechnicalSeo\ErrorMonitor::markTableExists();

		// 4. Create AI usage metering table
		\AmEveryWhere\Modules\Ai\UsageMeteringManager::createTable();

		// 5. Create SEO Audit History log table
		\AmEveryWhere\Modules\Admin\SeoAuditHistoryLog::createTable();

		// 6. Create keyword rank tracker table
		\AmEveryWhere\Modules\Analytics\KeywordRankTracker::createTable();

		// 7. Create broken link checker results table
		\AmEveryWhere\Modules\TechnicalSeo\BrokenLinkChecker::createTable();

		// 8. Register custom SEO capabilities
		\AmEveryWhere\Modules\Admin\CustomSeoUserRoles::addCapabilities();

		// 9. Register and persist sitemap routes during activation. The normal
		// boot listeners are not registered while an activation hook runs.
		self::sitemapRoutes()->flushRules();
	}

	public static function deactivate( bool $networkWide = false ): void {
		if ( $networkWide && is_multisite() ) {
			$siteIds = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);
			foreach ( $siteIds as $siteId ) {
				switch_to_blog( (int) $siteId );
				self::deactivateSite();
				restore_current_blog();
			}
			return;
		}

		self::deactivateSite();
	}

	private static function deactivateSite(): void {
		// Stop all background work before the plugin is disabled.
		\AmEveryWhere\Modules\TechnicalSeo\ErrorMonitor::deactivate();
		\AmEveryWhere\Core\Queue\QueueManager::deactivate();
		self::clearScheduledWork();

		// Remove plugin-owned rewrite rules before flushing so disabled plugins
		// do not leave virtual endpoints active in the persisted ruleset.
		self::sitemapRoutes()->removeRulesAndFlush();
	}

	private static function sitemapRoutes(): SitemapRouteManager {
		return new SitemapRouteManager(
			new SitemapGenerator(),
			new NewsSitemapGenerator(),
			new VideoSitemapGenerator()
		);
	}

	private static function clearScheduledWork(): void {
		$hooks = array(
			'ameverywhere_process_job',
			'ameverywhere_flush_404_buffer',
			'ameverywhere_scan_orphaned',
			'ameverywhere_stale_cornerstone_check',
			'ameverywhere_cornerstone_staleness_check',
			'ameverywhere_weekly_audit',
			'ameverywhere_monthly_audit',
			'ameverywhere_scheduled_audit',
			'ameverywhere_rank_check',
			'ameverywhere_404_cleanup',
			'ameverywhere_apply_retention',
			'ameverywhere_run_technical_audit',
			'ameverywhere_blc_scan_batch',
			'ameverywhere_weekly_usage_email',
		);

		foreach ( $hooks as $hook ) {
			wp_clear_scheduled_hook( $hook );
			if ( function_exists( 'as_unschedule_all_actions' ) ) {
				as_unschedule_all_actions( $hook );
				as_unschedule_all_actions( $hook, array(), 'ameverywhere' );
			}
		}
	}
}
