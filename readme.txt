=== AmEverywhere – WordPress SEO & Discovery Intelligence ===
Contributors: skoorite
Tags: seo, ai seo, sitemap, schema, indexing
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AmEverywhere is an enterprise-grade, headless-ready SEO and AI discoverability engine that natively integrates Google Search Console, IndexNow, and advanced JSON-LD Schema.

== Description ==

**★★★★★ "The most technically advanced SEO architecture for modern WordPress."**

**AmEverywhere is the ultimate SEO and AI discoverability engine for modern WordPress, proudly created by Skoorite Limited.** 
Whether you're a local business owner, a high-traffic news publisher, or a headless enterprise agency, AmEveryWhere gives you the exact tools you need to outrank the competition. It takes care of the complex technical SEO out of the box, freeing you up to do what you do best: create killer content.

### What problem does it solve?
Traditional SEO plugins were built a decade ago for simple blogs. Today’s web requires complex JSON-LD structured data, instant indexing, headless API support, and protection against aggressive AI web crawlers. **AmEveryWhere** replaces bloated legacy plugins with a modern, React-based engine that handles traditional SEO, technical SEO, and the new era of AI discoverability all in one place.

### Key Benefits
* **Drop the Bloat:** Replaces 5+ separate plugins (Redirection, Schema, Sitemaps, GSC Dashboards, Image Optimization).
* **AI-Ready:** The only SEO plugin that natively outputs `llms.txt` and machine-readable endpoints (`/schemamap`) to guide AI models like ChatGPT and Claude on how to ingest your content.
* **Instant Indexing:** Pings Google (via Google Indexing API) and Bing/Yandex (via IndexNow) the second you hit publish.
* **Actionable Analytics:** Pulls live Google Search Console and Bing Webmaster data into your dashboard to actively highlight decaying content and keyword cannibalization.
* **Headless-First:** Exposes 130+ secure REST API endpoints, making it the perfect SEO engine for decoupled React/Next.js/Vue frontends.

### Core Features

**🚀 Automate Your Technical SEO**
* Intelligent canonical URL management and robots meta control.
* Advanced Redirect Manager (301/302/307/410) with loop detection.
* 404 monitor and orphaned content finder.
* XML Sitemaps (Standard, News, Video) and HTML sitemap generation.
* Page load speed check via Google PageSpeed Insights API.
* Editable robots.txt and .htaccess.

**✍️ Write Content That Ranks (Editor & On-Page Tools)**
* Real-time SEO Checklist: evaluates keyword usage, title/meta length, headings, and alt text.
* Live SERP Previews: view exactly how your post will look on Google (desktop/mobile).
* Social Media Previews: view Facebook Open Graph and Twitter Card renders before publishing.
* Readability Checker: scores your content using the Flesch-Kincaid scale.
* FAQ Schema Builder: create rich-snippet accordions directly from the post sidebar.

**🛡️ Keep Your Site Safe (Compliance & Auditing)**
* System-wide Audit History Log: tracks every SEO change (who changed it and when).
* FTC-compliant AI disclosure labels on AI-generated content.
* Cookie Notice: built-in lightweight notice banner.

**⚡ Switch in Seconds (Migration & Setup)**
* 1-Minute Setup Wizard.
* One-Click SEO Migration: seamlessly import titles, descriptions, and settings from Yoast SEO, Rank Math, and All in One SEO (AIOSEO).

**✨ Win Rich Snippets (Schema & Structured Data)**
* Comprehensive structured data: Article, BreadcrumbList, LocalBusiness, FAQPage, HowTo, Recipe, and Event.
* Dynamic WooCommerce Product schema with AggregateRating support.
* Automatic ImageObject schema injection for featured and embedded images.
* Advanced schema/entity recommendations via the Content Assistant.

**🖼️ Optimize Every Asset (Content & Images)**
* Image optimization signals: automatic filename enforcement on upload.
* Keyword in the ALT text auditing and bulk mismatch fixing.
* Content opportunity detection and discovery analysis with live scoring.
* Broken link fixes executed safely in the background queue.
* Internal linking relationship mapping and semantic suggestions.

**📈 Command Your Search Visibility (Analytics & Indexing)**
* Google Search Console integration for impressions, clicks, and dying page detection.
* Google Analytics 4 (GA4) Dashboard for session trends and top traffic channels.
* Bing Webmaster Tools API integration to track traffic decay on Bing.
* Social preview images (Open Graph and Twitter Cards) managed easily.
* Favicon / Site Icon auditing.
* Author information explicitly mapped into entity structures.
* AI discoverability checks and crawler blocking tools.

### How it works
AmEveryWhere operates on a highly optimized React interface that talks to 135+ secure REST endpoints. Instead of injecting heavy PHP processing on every page load, it computes SEO scores and schema graphs asynchronously. When you connect it to GSC and Bing Webmaster, it automatically builds a 30-day trailing comparison to proactively warn you when content is losing traffic.

### Use Cases
* **Publishers & News:** Leverage the dedicated News Sitemap generator and instant IndexNow pings to beat competitors to the SERP.
* **WooCommerce Stores:** Automatically generate highly detailed Product JSON-LD to win rich snippets and Merchant Center visibility.
* **Enterprise & Headless Agencies:** Use the secure `ameverywhere/v1` REST API to hydrate Next.js metadata dynamically without writing custom WP GraphQL resolvers.

### Compatibility
AmEveryWhere is strictly tested to coexist beautifully with modern WordPress architectures. It natively supports WooCommerce, WordPress Multisite (with network-level defaults), and is fully isolated from breaking frontend page builders. 

== Installation ==

1. Upload the `ameverywhere` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** screen in WordPress
3. Navigate to **AmEveryWhere** in the admin sidebar.
4. Run the Setup Wizard to configure your focus keywords, schema defaults, and API keys.

== Frequently Asked Questions ==

= Does AmEveryWhere require any API keys to work? =
No. Core SEO features (meta tags, schema, sitemaps, redirects, image SEO, robots.txt) work without any API keys. API keys are strictly optional to unlock advanced features like Google Search Console, Bing Webmaster tools, and the LLM Writing Assistant.

= Is AmEveryWhere compatible with WooCommerce? =
Yes. AmEveryWhere automatically generates Product structured data (including AggregateRating from WC reviews) for all WooCommerce product types when WooCommerce is active.

= What happens when I deactivate or delete the plugin? =
Deactivation stops plugin background work and preserves all data. If you intend to permanently remove it, an administrator can explicitly enable the “Delete AmEveryWhere data when the plugin is deleted” setting before uninstalling.

= Can I import settings from Yoast SEO or All in One SEO? =
Yes. Go to **AmEveryWhere → Import** and choose your source plugin. A dry-run preview shows exactly what will be imported before you commit.

== Support ==

For documentation, bug reports, and operational guidance, please refer to the `docs/support-runbook.md` included in the plugin package. We provide a fully transparent queue visibility dashboard at `/wp-json/ameverywhere/v1/system/queue` for advanced debugging.

== Screenshots ==

1. Dashboard overview with SEO health score and Google Search Console metrics.
2. Advanced Redirect manager with automated loop detection.
3. Content Gap analysis and dying page detection tools.
4. Full XML Sitemap and Schema generator configurations.
5. Headless API and AI discoverability (`llms.txt`) settings.

== Changelog ==

= 1.0.0 =
* Initial Release.
* Core: Technical SEO, XML Sitemaps (News/Video), JSON-LD Schema (WooCommerce support).
* AI & Headless: `llms.txt` generation, 135+ secure REST endpoints, LLM crawler blocking.
* Integrations: Google Search Console API, Bing Webmaster API, IndexNow, Google Indexing API.
* Utilities: Image SEO, Redirects, 404 Monitor, Internal Link Suggester.
