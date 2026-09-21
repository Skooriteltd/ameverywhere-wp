=== AmEveryWhere ===
Contributors: ameverywhereteam
Tags: seo, schema, sitemap, ai seo, technical seo
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 8.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AmEverywhere-wp is a WordPress SEO & Discovery Intelligence toolkit for modern web.

== Description ==

AmEveryWhere is a comprehensive WordPress SEO plugin covering every aspect of modern search optimisation — from technical SEO and structured data to AI-powered content assistance, image SEO, and indexing control.

Source code and frontend build instructions are available at https://github.com/Skooriteltd/ameverywhere-wp.

= Core Features =

**Technical SEO**
* Intelligent canonical URL management and robots meta control
* 301/302/307/308 redirect manager with loop detection and CSV import/export
* 404 monitor with one-click redirect creation
* .htaccess safe visual editor with auto-backup and restore
* Orphaned content finder — identifies pages with zero internal links
* XML sitemap with per-post-type priority and changefreq controls
* News sitemap, video sitemap, and HTML sitemap shortcode `[ameverywhere_sitemap]`

**Schema & Structured Data**
* Article, BreadcrumbList, FAQPage, HowTo, LocalBusiness, Product and more
* WooCommerce Product schema with variable product and AggregateRating support
* ImageObject schema injection for every image in post content
* Schema output validator against Google's Rich Results guidelines

**Image SEO**
* SEO-friendly filename enforcement on upload (hyphens, no generic patterns)
* Keyword-aware alt text generation from focus keyword
* Alt text audit with bulk keyword-mismatch fix
* Bulk image compression via WordPress media library

**Content Intelligence**
* LLM-powered writing assistant: paragraph improvement, meta suggestions
* Keyword density analysis with morphological matching (inflections, stems)
* Content gap analysis: identify sub-topics competitors rank for
* Search intent classifier: informational / commercial / navigational / transactional
* Inclusive language checker with configurable exceptions
* Word complexity scorer integrated with readability metrics
* Internal link suggestions based on keyword overlap
* Stale cornerstone content detector with email alerts

**Indexing & Visibility**
* IndexNow auto-submit on publish (Bing, Yandex)
* Google Search Console integration — impressions, clicks, CTR, position, declining keywords
* Keyword rank tracker with 180-day history (requires SerpApi key)
* PageSpeed / Core Web Vitals dashboard (requires Google PageSpeed API key)
* Post index status checker via Google URL Inspection API

**AI & Compliance**
* llms.txt generator with cornerstone content and Disallow/Allow controls
* FTC-compliant AI disclosure labels on AI-generated content
* AI training data opt-out (noai / noimageai, TDM-Reservation header)
* Editable AI crawler bot list with runtime merge
* Privacy tools: WordPress data export/deletion integration, GPC honour, retention policy
* AI token usage metering with configurable limits per user

**Enterprise & Platform**
* Granular SEO user roles: `manage_seo`, `view_seo_reports`, `manage_redirects`, `edit_seo_meta`
* WordPress Multisite support with network-level defaults and per-site overrides
* Headless WordPress REST SEO endpoints (`GET/PUT /ameverywhere/v1/seo/{id}`)
* Competitor SEO importer: migrate from Yoast SEO and All in One SEO
* System-wide audit history log with CSV export
* Keyword cannibalization detector
* Front-end SEO inspector overlay (Shadow DOM isolated, editor-only)

= External Services =

AmEveryWhere connects to the following external services when features are configured by the user:

* **OpenAI API** (api.openai.com) — used by the LLM Writing Assistant and Content Gap Analyser when an API key is provided. [Privacy Policy](https://openai.com/privacy)
* **Google PageSpeed Insights API** (googleapis.com) — used by the PageSpeed Dashboard when an API key is provided. [Privacy Policy](https://policies.google.com/privacy)
* **Google Search Console API** (googleapis.com) — used when OAuth2 is configured by the admin. [Privacy Policy](https://policies.google.com/privacy)
* **Google Indexing API** (indexing.googleapis.com) — used only when the administrator enables it for pages that meet Google's JobPosting or eligible livestream requirements. [Privacy Policy](https://policies.google.com/privacy)
* **SerpApi** (serpapi.com) — used by the Keyword Rank Tracker when an API key is provided. [Privacy Policy](https://serpapi.com/privacy)
* **IndexNow API** (api.indexnow.org) — used to submit URLs to Bing and Yandex on publish. [Privacy Policy](https://www.indexnow.org/)
* **Anthropic API** (api.anthropic.com) — used by the writing assistant only when the administrator selects Anthropic and supplies a key. [Privacy Policy](https://www.anthropic.com/privacy)
* **Ollama-compatible host** — used only when the administrator selects a custom Ollama endpoint; the administrator is responsible for the selected host and its privacy terms.
* **Meta, X, LinkedIn, and Pinterest APIs** — used only after an administrator connects the corresponding social account to publish selected content. Their privacy terms apply.
* **AmEveryWhere API** (api.ameverywhere.com) — used only when the administrator configures the optional remote service. [Privacy Policy](https://ameverywhere.com/privacy)

No data is sent to any external service without an API key being explicitly configured by the site administrator. No telemetry or usage data is collected by AmEveryWhere itself.

== Installation ==

1. Upload the `ameverywhere` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** screen in WordPress
3. Navigate to **AmEveryWhere** in the admin sidebar to complete setup
4. Run the Setup Wizard to configure your focus keywords, schema defaults, and API keys

== Frequently Asked Questions ==

= Does AmEveryWhere require any API keys to work? =

No. Core SEO features (meta tags, schema, sitemaps, redirects, image SEO, robots.txt) work without any API keys. API keys unlock AI-powered features (OpenAI), rank tracking (SerpApi), Google Search Console, and PageSpeed Insights.

= Is AmEveryWhere compatible with WooCommerce? =

Yes. AmEveryWhere automatically generates Product structured data (including AggregateRating from WC reviews) for all WooCommerce product types when WooCommerce is active.

= Does it support WordPress Multisite? =

Yes. Network administrators can set defaults from the Network Admin panel. Individual site admins can override them if permitted.

= What happens when I deactivate or delete the plugin? =

Deactivation stops plugin background work and preserves data. Deletion also preserves data by default. An administrator may explicitly enable “Delete AmEveryWhere data when the plugin is deleted” in Settings before uninstalling if permanent removal is intended.

= Can I import settings from Yoast SEO or All in One SEO? =

Yes. Go to **AmEveryWhere → Import** and choose your source plugin. A dry-run preview shows exactly what will be imported before you commit.

== Screenshots ==

1. Dashboard overview with SEO health score
2. Meta tags and focus keyword editor in Gutenberg sidebar
3. Redirect manager with loop detection
4. Technical SEO audit results grouped by severity
5. Image SEO dashboard — filename enforcement and alt text audit
6. Schema output with live validation
7. Keyword rank tracker with 180-day trend chart
8. Content gap analysis with one-click draft creation

== Changelog ==

= 1.0.0 =
* Initial release
* Technical SEO: canonical management, robots meta, 404 monitor, redirect manager (loop detection, CSV import/export), .htaccess editor, orphaned content finder
* Image SEO: filename enforcement, keyword alt text, ImageObject schema, alt text audit
* Schema: Article, Product, BreadcrumbList, FAQPage, HowTo, LocalBusiness, WooCommerce Product
* Content: LLM writing assistant, search intent classifier, content gap analysis, internal link suggestions, keyword density, morphological matching, inclusive language checker, word complexity scorer
* Indexing: IndexNow, Google Search Console OAuth2, keyword rank tracker, PageSpeed / CWV dashboard, index status checker
* Sitemap: XML with priority/changefreq per post-type, News, Video, HTML shortcode
* Compliance: privacy export/erasure tools, AI disclosure labels, LLM training opt-out, AI bot blocking, llms.txt generator
* Enterprise: Multisite, headless REST endpoints, Yoast/AIOSEO importer, custom roles, audit history log, keyword cannibalization detector

== Upgrade Notice ==

= 1.0.0 =
First release — no upgrade path needed.
