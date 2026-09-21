# AmEveryWhere Features

AmEveryWhere is a WordPress SEO plugin built to help websites rank well on traditional search engines like Google and Bing, as well as modern AI search tools.

Below is a complete list of features currently implemented and working in the plugin.

---

## 1. Content & Editor Tools (Gutenberg)

- **On-Page SEO Checklist:** Analyzes your post as you write, checking title length, meta description length, keyword usage, headings, and image alt text.
- **Readability Checker:** Measures how easy your text is to read using the Flesch-Kincaid scale. Highlights long sentences and excessive passive voice.
- **Search Result Preview:** Shows an accurate preview of how your page will look in Google search results on both desktop and mobile screens.
- **Social Media Previews:** Shows how your post will look when shared on Facebook and Twitter/X.
- **Custom Canonical URLs:** Allows you to set a custom canonical URL to prevent duplicate content issues.
- **Robots Meta Controls:** Set individual pages to `noindex`, `nofollow`, `noarchive`, `nosnippet`, or `noimageindex` directly from the editor sidebar.
- **FAQ Schema Builder:** Add questions and answers directly in the sidebar to automatically create structured FAQ schema for rich search results.
- **AI Writing Assistant:** Suggests SEO titles and meta descriptions using your connected AI key (OpenAI, Anthropic, Ollama, or the AmEveryWhere remote API).
- **Internal Link Suggestions:** Suggests relevant articles on your site that you can link to from the current post.
- **Cornerstone Content:** Flag your most important articles as cornerstone content. The plugin notifies you if these articles have not been updated in over 180 days.

---

## 2. Structured Data & Schema Markup

AmEveryWhere automatically adds valid Schema.org JSON-LD code to your pages so search engines can better understand your content.

Supported schema types include:
- **Article & Blog Post:** Includes author, publication date, last modified date, and featured image.
- **News Article:** Formatted specifically for Google News standards.
- **WebPage & WebSite:** Includes site search box markup.
- **Breadcrumb Navigation:** Displays clear breadcrumb trails in search results.
- **FAQ Page:** Displays accordion-style answers in search snippets.
- **How-To:** Formats step-by-step instructions.
- **Local Business:** Adds business name, address, phone number, and operating hours.
- **Recipe:** Formats recipe ingredients, cooking time, and nutrition info.
- **Event:** Formats event dates, venue, and attendance mode.
- **Product:** Integrates with WooCommerce to output price, currency, and stock availability.
- **Custom Schema:** Paste any custom JSON-LD schema directly into a page when you need specialized markup.

---

## 3. XML & HTML Sitemaps

- **XML Sitemap Index (`/sitemap.xml`):** Generates clean, search-engine-ready sitemaps with automatic monthly chunking for sites with many posts.
- **Google News Sitemap (`/sitemap-news.xml`):** Automatically creates a news sitemap containing articles published in the last 48 hours, following Google News technical guidelines.
- **Video Sitemap (`/video-sitemap.xml`):** Finds embedded videos (YouTube, Vimeo, self-hosted MP4) and includes them in a dedicated video sitemap.
- **Sitemap Controls:** Choose which post types and taxonomies to include or exclude, and exclude specific post IDs.
- **HTML Sitemap Shortcode:** Use `[ameverywhere_sitemap]` to display a clean, organized site map on any page for your visitors.

---

## 4. Instant Search Engine Indexing

- **IndexNow Integration:** Instantly notifies Bing, Yandex, and Seznam whenever you publish, update, or delete a post.
- **Google Indexing API:** Optional submission for pages that meet Google's documented JobPosting or eligible livestream requirements; it is disabled by default and does not guarantee indexing.
- **Indexing Dashboard:** View submission logs, daily quota usage, and response statuses.

---

## 5. Technical SEO & Server Tools

- **Redirect Manager:** Set up 301 (permanent), 302 (temporary), and 307 redirects with full support for regular expressions (Regex).
- **404 Error Monitor:** Automatically logs missing pages that visitors or bots try to open. See the URL, hit count, referrer, and turn any 404 into a 301 redirect with one click.
- **Robots.txt Editor:** View and customize your site's `robots.txt` file directly inside WordPress with live syntax checking.
- **.htaccess Editor:** Safely edit your Apache `.htaccess` file from the admin area, with automatic backups made before every save.
- **Favicon & Icon Checker:** Checks that your site has the required icons for browser tabs, mobile bookmarks, and web apps.

---

## 6. AI Crawler Guide (`llms.txt`)

- **Dynamic `llms.txt` and `llms-full.txt`:** Generates standard text files at your domain root that guide AI models and web crawlers (like ChatGPT, Claude, and Perplexity) on how to understand and cite your site's content.
- **Customizable Summary:** Set a concise description of your website, list key pages, and define guidelines for AI scrapers.

---

## 7. Link Health & Broken Link Monitoring

- **Internal Link Index:** Maps all internal links across your site into an organized table to analyze site architecture.
- **Orphaned Content Finder:** Detects published posts and pages that have zero incoming internal links, helping you find and fix neglected content.
- **Broken Link Checker:** Scans your published content for broken internal and external links in small batches, showing HTTP error codes and letting you re-check or fix them.

---

## 8. Analytics & Performance

- **Google Search Console Integration:** View search clicks, impressions, click-through rates (CTR), and average rankings right in your WordPress dashboard.
- **Keyword Cannibalization Alert:** Highlights queries where multiple pages on your site compete against each other for the same search term.
- **Google Analytics 4 (GA4) Reports:** View session trends, top landing pages, and traffic channels without leaving your site.
- **PageSpeed Insights:** Check mobile and desktop performance scores and Core Web Vitals directly from WordPress, with results cached for 7 days.
- **Keyword Rank Tracker:** Add target keywords to track their Google ranking position over time via SerpApi, with 7-day, 30-day, and 90-day history charts.

---

## 9. Media & Image SEO

- **Image Alt Text Audit:** Identifies all images in your media library that are missing alt text and provides quick links to add them.
- **Clean Filename Enforcer:** Automatically cleans and renames uploaded media files into lowercase, hyphen-separated filenames for better image search performance.
- **Image Schema:** Automatically includes image dimensions and metadata in structured data.

---

## 10. Privacy & Compliance

- **Cookie Notice:** Built-in first-party notice that records a visitor choice. It does not discover or block third-party scripts and is not a substitute for a consent-management platform.
- **AI Content Disclosure:** Add clear notices when content is generated or assisted by artificial intelligence.

---

## 11. User Roles & Team Access

- **Granular SEO Permissions:** Control which user roles (Administrator, Editor, Author, Contributor) can manage redirects, edit meta tags, or view SEO reports.
- **Audit History Log:** Keeps a clear record of every change made to SEO titles, descriptions, and plugin settings, showing which user made each change and when.

---

## 12. WordPress Multisite Support

- Manage SEO settings and verification codes across all sites in a WordPress Multisite network from one place.

---

## 13. Headless WordPress REST API

- Clean REST endpoints (`/ameverywhere/v1/seo/{post_id}` and `/ameverywhere/v1/seo/global`) to read and write SEO data in headless setups (Next.js, Gatsby, Nuxt).

---

## 14. Setup Wizard & Competitor Migration

- **Guided Setup:** A simple 3-step setup process to configure site type, social links, and basic defaults in under a minute.
- **One-Click Migration:** Automatically detects and imports existing titles, descriptions, and settings from:
  - Yoast SEO
  - Rank Math
  - All in One SEO (AIOSEO)
