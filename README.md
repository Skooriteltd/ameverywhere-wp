# RankSavvy MVP (Phase 1)

RankSavvy is a next-generation WordPress SEO and AEO plugin engineered for the AI-first search era. This plugin represents the **Phase 1 MVP**, aggressively optimized for News Publishers and Bloggers.

## Features Included in MVP

### 1. Core SEO Foundation (`RankSavvy\Modules\Seo`)
- **Meta Tags**: Dynamically generates `<title>` and `<meta name="description">` tags, hooking securely into `wp_head`.
- **Canonical URLs**: Intelligent fallback and override system for canonicalization.
- **Social Metadata**: Automated Open Graph (`og:`) and Twitter (`twitter:`) card generation.
- **Robots Directives**: Page-level `noindex` and `nofollow` handling.

### 2. Custom XML Sitemap Engine (`RankSavvy\Modules\Sitemap`)
- Disables default WordPress sitemaps for performance.
- Generates dynamic, virtual standard Sitemaps via `/ranksavvy-sitemap.xml`.
- Generates **Google News compliant Sitemaps** via `/ranksavvy-news-sitemap.xml` filtering for fresh 48-hour content.

### 3. Instant Indexing Engine (`RankSavvy\Modules\Indexing`)
- Background Queue Processor to prevent post-save blocking.
- Automatically pings the **Google Indexing API** and **Bing IndexNow API** upon post publication or update.

### 4. Technical SEO Automation (`RankSavvy\Modules\TechnicalSeo`)
- Intercepts requests early (`template_redirect`) to process high-speed 301/302 regex redirects.
- Monitors and logs valid 404 errors (safely excluding missing image/css assets).

### 5. Automated AI-Ready Schema (`RankSavvy\Modules\Schema`)
- Generates JSON-LD schema dynamically.
- Supported Types: `Article`, `NewsArticle` (via toggle), and `BreadcrumbList`.

### 6. Gutenberg Content Assistant (`RankSavvy\Modules\ContentAssistant`)
- React-based Sidebar integrated directly into the Block Editor (`edit-post`).
- Allows editorial teams to define Custom Meta Titles, Meta Descriptions, and toggle News Optimization/Indexation on the fly.

### 7. Modern Publisher Dashboard (`RankSavvy\Modules\Admin`)
- React/Tailwind-powered administrative interface.
- Built via `@wordpress/scripts` to ensure WP UI/UX standards.

## Architecture

Built using modern PHP 8.2+ practices:
- **Service Container**: Lightweight Dependency Injection.
- **Event Manager**: Decoupled WordPress hook management.
- **PSR-4 Autoloading**: Powered by Composer.
- **Queue Manager**: Background job stubbing.

## Installation & Setup

1. Clone or download to `wp-content/plugins/ranksavvy`.
2. Run `composer install` to generate the autoloader.
3. Run `npm install && npm run build` to compile the React/Tailwind frontend assets.
4. Activate the plugin in WordPress.
