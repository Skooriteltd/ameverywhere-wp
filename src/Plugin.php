<?php

namespace RankSavvy;

use RankSavvy\Core\Container\Container;
use RankSavvy\Modules\Seo\SeoModule;
use RankSavvy\Modules\Seo\MetaTagsGenerator;
use RankSavvy\Modules\Seo\OpenGraphGenerator;
use RankSavvy\Modules\Admin\AdminModule;
use RankSavvy\Modules\Admin\AdminMenu;
use RankSavvy\Modules\Sitemap\SitemapModule;
use RankSavvy\Modules\Sitemap\SitemapRouteManager;
use RankSavvy\Modules\Sitemap\SitemapGenerator;
use RankSavvy\Modules\Sitemap\NewsSitemapGenerator;
use RankSavvy\Modules\Indexing\IndexingModule;
use RankSavvy\Modules\TechnicalSeo\TechnicalSeoModule;
use RankSavvy\Modules\TechnicalSeo\RedirectManager;
use RankSavvy\Modules\TechnicalSeo\ErrorMonitor;
use RankSavvy\Modules\Schema\SchemaModule;
use RankSavvy\Modules\Schema\SchemaGenerator;
use RankSavvy\Modules\ContentAssistant\ContentAssistantModule;
use RankSavvy\Modules\Trends\TrendsModule;
use RankSavvy\Modules\Breadcrumbs\BreadcrumbRenderer;
use RankSavvy\Modules\ImageSeo\ImageSeoModule;
use RankSavvy\Modules\TechnicalSeo\RobotsTxtEditor;
use RankSavvy\Modules\Migration\MigrationManager;
use RankSavvy\Modules\Onboarding\SetupWizard;

class Plugin
{
    private static ?Plugin $instance = null;
    private Container $container;

    private function __construct()
    {
        $this->container = new Container();
    }

    public static function getInstance(): Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function boot(): void
    {
        $this->registerServices();
        $this->bootModules();
    }

    private function registerServices(): void
    {
        // Core services will be registered here (Events, Queues, etc.)
        $this->container->singleton('event_manager', \RankSavvy\Core\Event\EventManager::class);
        
        // Register SEO Module Dependencies
        $this->container->singleton('meta_tags_generator', MetaTagsGenerator::class);
        $this->container->singleton('open_graph_generator', OpenGraphGenerator::class);
        
        $this->container->singleton('seo_module', function() {
            return new SeoModule(
                $this->container->get('event_manager'),
                $this->container->get('meta_tags_generator'),
                $this->container->get('open_graph_generator')
            );
        });

        // Register Admin Module Dependencies
        $this->container->singleton('admin_menu', AdminMenu::class);
        $this->container->singleton('admin_module', function() {
            return new AdminModule(
                $this->container->get('event_manager'),
                $this->container->get('admin_menu')
            );
        });

        // Register Sitemap Module Dependencies
        $this->container->singleton('sitemap_generator', SitemapGenerator::class);
        $this->container->singleton('news_sitemap_generator', NewsSitemapGenerator::class);
        
        $this->container->singleton('sitemap_route_manager', function() {
            return new SitemapRouteManager(
                $this->container->get('sitemap_generator'),
                $this->container->get('news_sitemap_generator')
            );
        });

        $this->container->singleton('sitemap_module', function() {
            return new SitemapModule(
                $this->container->get('event_manager'),
                $this->container->get('sitemap_route_manager')
            );
        });

        // Register Indexing Module Dependencies
        $this->container->singleton('queue_manager', \RankSavvy\Core\Queue\QueueManager::class);
        $this->container->singleton('indexing_module', function() {
            return new IndexingModule(
                $this->container->get('event_manager'),
                $this->container->get('queue_manager')
            );
        });

        // Register Technical SEO Module Dependencies
        $this->container->singleton('redirect_manager', RedirectManager::class);
        $this->container->singleton('error_monitor', ErrorMonitor::class);
        
        $this->container->singleton('technical_seo_module', function() {
            return new TechnicalSeoModule(
                $this->container->get('event_manager'),
                $this->container->get('redirect_manager'),
                $this->container->get('error_monitor')
            );
        });

        // Register Schema Module Dependencies
        $this->container->singleton('schema_generator', SchemaGenerator::class);
        $this->container->singleton('schema_module', function() {
            return new SchemaModule(
                $this->container->get('event_manager'),
                $this->container->get('schema_generator')
            );
        });

        // Register Content Assistant Module
        $this->container->singleton('content_assistant_module', function() {
            return new ContentAssistantModule(
                $this->container->get('event_manager')
            );
        });

        // Register Trends Module
        $this->container->singleton('trends_module', function() {
            return new TrendsModule(
                $this->container->get('event_manager')
            );
        });

        // Register Breadcrumb Renderer
        $this->container->singleton('breadcrumb_renderer', function() {
            return new BreadcrumbRenderer();
        });

        // Register Image SEO Module
        $this->container->singleton('image_seo_module', function() {
            return new ImageSeoModule(
                $this->container->get('event_manager')
            );
        });

        // Register Robots.txt Editor
        $this->container->singleton('robots_txt_editor', function() {
            return new RobotsTxtEditor();
        });

        // Register Migration Manager
        $this->container->singleton('migration_manager', function() {
            return new MigrationManager();
        });

        // Register Setup Wizard
        $this->container->singleton('setup_wizard', function() {
            return new SetupWizard();
        });
    }

    private function bootModules(): void
    {
        // Modules will be booted here
        /** @var SeoModule $seoModule */
        $seoModule = $this->container->get('seo_module');
        $seoModule->boot();

        /** @var AdminModule $adminModule */
        $adminModule = $this->container->get('admin_module');
        $adminModule->boot();

        /** @var SitemapModule $sitemapModule */
        $sitemapModule = $this->container->get('sitemap_module');
        $sitemapModule->boot();

        /** @var IndexingModule $indexingModule */
        $indexingModule = $this->container->get('indexing_module');
        $indexingModule->boot();

        /** @var TechnicalSeoModule $technicalSeoModule */
        $technicalSeoModule = $this->container->get('technical_seo_module');
        $technicalSeoModule->boot();

        /** @var SchemaModule $schemaModule */
        $schemaModule = $this->container->get('schema_module');
        $schemaModule->boot();

        /** @var ContentAssistantModule $contentAssistantModule */
        $contentAssistantModule = $this->container->get('content_assistant_module');
        $contentAssistantModule->boot();

        /** @var TrendsModule $trendsModule */
        $trendsModule = $this->container->get('trends_module');
        $trendsModule->boot();

        /** @var BreadcrumbRenderer $breadcrumbRenderer */
        $breadcrumbRenderer = $this->container->get('breadcrumb_renderer');
        $breadcrumbRenderer->register();

        /** @var ImageSeoModule $imageSeoModule */
        $imageSeoModule = $this->container->get('image_seo_module');
        $imageSeoModule->boot();

        /** @var RobotsTxtEditor $robotsTxtEditor */
        $robotsTxtEditor = $this->container->get('robots_txt_editor');
        $robotsTxtEditor->register();

        // Register Robots.txt REST routes
        add_action('rest_api_init', [$robotsTxtEditor, 'registerRoutes']);

        /** @var MigrationManager $migrationManager */
        $migrationManager = $this->container->get('migration_manager');
        add_action('rest_api_init', [$migrationManager, 'registerRoutes']);

        /** @var SetupWizard $setupWizard */
        $setupWizard = $this->container->get('setup_wizard');
        $setupWizard->register();
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    public static function activate(): void
    {
        // Activation logic (e.g., creating custom tables)
    }

    public static function deactivate(): void
    {
        // Deactivation logic
    }
}
