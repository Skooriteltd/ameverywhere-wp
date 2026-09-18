# AmEveryWhere (WordPress ClientApp)

**AmEveryWhere** is a next-generation WordPress SEO and Answer Engine Optimization (AEO) platform engineered for the AI-first search era.

## Architecture: Two-Phase Transition

The platform follows a decoupled two-phase architecture:

- **Phase 1 (Current)**:
  - Complete rebrand from legacy RankSavvy to **AmEveryWhere**.
  - Serves as the high-performance **Distribution and Data-Collection Layer** (the clientApp) inside the WordPress ecosystem.
  - Offloads heavy computational workloads (AI content generation, deep site audit algorithms, keyword cannibalization matrix calculations, and SERP rank tracking) to the **AmEveryWhere Backend API** client via `AmEveryWhere\Core\Api\BackendApiClient`.
  - Supports hybrid fallback to direct user API keys (BYOK) for uninterrupted operation.
- **Phase 2 (Upcoming)**:
  - Complete physical separation of the standalone Backend API service.
  - Enables scale for third-party consumers, headless CMS platforms, and external web clients beyond WordPress.

---

## Core Layers in the WordPress Plugin

### 1. Zero-Latency Distribution Layer (Public Facing)
- **Meta Tag & Social Graph Engine** (`AmEveryWhere\Modules\Seo`): Injects SEO meta tags (`title`, `description`, `canonical`, `robots`), OpenGraph, and Twitter cards.
- **Structured Data Engine** (`AmEveryWhere\Modules\Schema`): Automated JSON-LD markup (`Article`, `NewsArticle`, `Product`, `FAQ`, `HowTo`, `LocalBusiness`, `Recipe`, `Event`).
- **XML & HTML Sitemaps** (`AmEveryWhere\Modules\Sitemap`): Generates dynamic standard XML sitemaps, Google News compliant sitemaps, video sitemaps, and shortcode `[ameverywhere_sitemap]`.
- **Server Directives & Compliance** (`AmEveryWhere\Modules\TechnicalSeo` & `Compliance`): Dynamic `robots.txt` generator, `llms.txt`, high-speed regex 301/302 redirects, and CCPA/GDPR cookie banner.

### 2. Data-Collection & Editorial Layer (WordPress Admin)
- **Gutenberg Editor Sidebar** (`AmEveryWhere\Modules\ContentAssistant`): Live on-page SEO score, focus keywords, readability metrics, and instant AI title/meta suggestions.
- **Diagnostic Logging**: 404 error hit logging, broken link checker, and internal link index table.
- **Publisher Dashboard** (`AmEveryWhere\Modules\Admin`): React-based management interface for global rules, indexing quotas, and audit history.

### 3. Backend API Gateway Client (`AmEveryWhere\Core\Api\BackendApiClient`)
- Central communication gateway connecting WordPress to the remote AmEveryWhere computational backend.
- Offloads heavy AI generation, content gap analysis, site health scoring, and SERP position checks with automated fallback to local/BYOK providers.

---

## Backward Compatibility & Migrations

The plugin includes an automated, non-destructive migration engine in `AmEveryWhere\Core\Database\Installer`:
- Renames legacy `wp_ranksavvy_*` database tables to `wp_ameverywhere_*`.
- Migrates `ranksavvy_*` option records to `ameverywhere_*`.
- Migrates `_ranksavvy_*` post meta records to `_ameverywhere_*`.
- Provides backward-compatible wrappers for shortcodes (`[ranksavvy_sitemap]`, `[ranksavvy_breadcrumbs]`) and action hooks (`ranksavvy_activation`, `ranksavvy_deactivation`).

---

## Installation & Setup

1. Place in `wp-content/plugins/ameverywhere`.
2. Run `composer install` to generate autoload mappings for `AmEveryWhere\`.
3. Run `npm run build` to compile the React admin and Gutenberg editor bundles.
4. Activate the plugin in WordPress.
