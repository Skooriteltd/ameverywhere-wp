# AmEveryWhere — Engineering Production-Readiness Backlog

> **Release target:** First public WordPress.org release
> **Status:** Not release-ready
> **Source:** Senior engineering review, 18 September 2026
> **Authority:** This document supersedes the release-readiness status claims in `production-readiness-backlog.md`. Completed status requires the evidence defined in each item, not a code change alone.

## Release decision

**Do not submit the plugin to WordPress.org or distribute the current ZIP.** The working tree contains an activation blocker; the ZIP in `dist/` does not match the working tree; sensitive data is exposed by a public REST endpoint; and privacy, lifecycle, performance, and feature-claim defects remain.

### Scope decision: page builders

Elementor, Divi, WPBakery, and other page-builder integrations are **out of scope for v1.0**. Do not ship placeholder integrations.

- Remove the `PageBuilderSeoWrappers` registration and implementation from the v1.0 code path.
- Remove page-builder compatibility claims from `readme.txt`, screenshots, feature lists, and product copy.
- Reconsider the feature only when a supported-builder matrix, an owner, automated integration tests, and a maintained UX design are approved.

## Definition of done for release

All P0 and P1 items must be complete. A release candidate must:

1. Activate, deactivate, uninstall, and upgrade successfully on clean single-site and multisite installations.
2. Pass Plugin Check, the defined WPCS baseline, PHP lint, unit tests, WordPress integration tests, and browser smoke tests.
3. Have no public REST response containing credentials, personal data, or administrative configuration.
4. Produce a reproducible ZIP from a clean commit; every packaged file must correspond to that commit.
5. Make no destructive content, media, SEO, crawl, or network change without explicit administrator opt-in and a reversible path.
6. Match all public claims, screenshots, readme content, and external-service disclosures.

## Priority and ownership

| Priority | Meaning | Release gate |
| --- | --- | --- |
| P0 | Security exposure, crash, data loss, invalid release artifact, or policy blocker | Must complete before any RC |
| P1 | Reliability, privacy, functionality, scale, or documentation issue likely to cause harm or review rejection | Must complete before submission |
| P2 | Hardening, maintainability, or deferred product work | Schedule after v1.0 unless noted |

Suggested owners: **Platform** (bootstrap, release, storage), **Security** (authorization and secrets), **SEO** (sitemaps/indexing), **Privacy** (consent/data rights), **UX** (admin flows/docs), and **QA** (test gates).

---

## P0 — release blockers

### PR-001 — Repair activation fatal and test plugin lifecycle

- **Owner:** Platform
- **Affected:** `src/Plugin.php`, `src/Modules/Admin/TechnicalSeoAuditEngine.php`, lifecycle tests
- **Problem:** `Plugin::activate()` calls `TechnicalSeoAuditEngine::createTable()`, but the class has no such method. Activation of the current working tree fatals.
- **Work:** Remove the call if results are option-backed, or implement the intended idempotent schema migration. Audit every lifecycle call for missing methods/classes.
- **Acceptance criteria:**
  - [ ] Fresh activation succeeds with `WP_DEBUG` enabled and no PHP notices, warnings, or fatals.
  - [ ] Re-activation is idempotent.
  - [ ] Automated tests cover activation, deactivation, uninstall, and upgrade on a real WordPress database.

### PR-002 — Remove public disclosure of secrets and personal data

- **Owner:** Security
- **Affected:** `src/Modules/Api/HeadlessSeoEndpoints.php`
- **Problem:** The public `/seo/global` endpoint returns `admin_email` and the raw `indexnow_key`.
- **Work:** Define a minimal public schema. Remove credential, email, internal configuration, and operational-flag fields; add a separate authenticated admin endpoint only where necessary.
- **Acceptance criteria:**
  - [ ] Unauthenticated requests contain only deliberately public SEO data.
  - [ ] IndexNow, OAuth, AI, social, and backend credentials never appear in REST, HTML, logs, exports, or error messages.
  - [ ] Add regression tests for anonymous requests and authenticated role boundaries.

### PR-003 — Create a clean, reproducible release artifact

- **Owner:** Platform / QA
- **Affected:** `package-zip.sh`, `deploy-to-wporg.sh`, `dist/`, CI
- **Problem:** The current `dist/ameverywhere-1.0.0.zip` differs from source and contains legacy Ranksavvy code absent from the working tree.
- **Work:** Build only from a clean, tagged commit in CI. Generate a manifest of file hashes, test the ZIP in a temporary WordPress install, and fail when tracked changes are present.
- **Acceptance criteria:**
  - [ ] ZIP hash manifest maps to the release commit.
  - [ ] ZIP excludes development caches, tests, source screenshots, local tooling, and unrelated files.
  - [ ] ZIP includes required runtime dependencies, generated assets, licenses, and source/disclosure material required by WordPress.org.
  - [ ] CI installs the ZIP in an empty WordPress instance and exercises activation.

### PR-004 — Make media optimization safe, opt-in, and reversible

- **Owner:** Platform / UX
- **Affected:** `src/Modules/ImageSeo/ImageCompressor.php`, `src/Modules/ImageSeo/ImageSeoModule.php`, settings UI, docs
- **Problem:** Compression is enabled by default, modifies uploads in place, strips metadata, and ignores the `preserve` setting during upload. It can irreversibly change user originals.
- **Work:** Disable by default; add an explicit consent screen and per-operation confirmation. Preserve originals for every mode or use WordPress-generated derivative files. Track generated files and provide restore/delete actions.
- **Acceptance criteria:**
  - [ ] No upload is changed until an administrator opts in.
  - [ ] Original bytes and metadata can be restored after both upload-time and bulk processing.
  - [ ] WebP delivery is implemented safely or generation is removed; orphaned `.webp` files are not left behind.
  - [ ] Test JPEG, PNG transparency, GIF, WebP, EXIF orientation, failed writes, and unavailable Imagick/GD.

### PR-005 — Repair privacy erasure and retention behavior

- **Owner:** Privacy / Platform
- **Affected:** `src/Modules/Compliance/CcpaPrivacyTools.php`, `src/Core/Database/Installer.php`, tests
- **Problem:** The WordPress privacy eraser calls the REST handler with an empty request, reports removal without deleting a user’s records, and retention queries a nonexistent `created_at` column on 404 logs.
- **Work:** Implement direct private data access methods used by both REST and WordPress privacy callbacks. Use `last_hit` for 404 retention or add a valid creation timestamp through a migration. Paginate exporter/eraser results correctly.
- **Acceptance criteria:**
  - [ ] WordPress’s privacy tools export and erase data for the requested email only.
  - [ ] Erasure responses accurately report removed and retained records.
  - [ ] Retention jobs execute without SQL errors and are covered by integration tests.
  - [ ] Add privacy-policy suggested text covering logs, AI requests, API credentials, and external services.

### PR-006 — Remove invalid automatic Google Indexing API usage

- **Owner:** SEO
- **Affected:** `src/Modules/Indexing/IndexingModule.php`, `IndexingJob.php`, `GoogleIndexingApi.php`, admin UI, readme
- **Problem:** Every published URL is sent to Google’s Indexing API. That API is restricted to JobPosting and eligible livestream pages, not ordinary posts/pages.
- **Work:** Remove generic Google indexing submission. If retained for eligible content, enforce the documented schema/type conditions, explicit administrator enablement, quota observability, and user-facing eligibility messaging.
- **Acceptance criteria:**
  - [ ] Generic WordPress content is never submitted to the Google Indexing API.
  - [ ] Eligible submissions are validated before dispatch and logged without secrets.
  - [ ] Documentation accurately describes supported use and does not promise immediate indexing.

### PR-007 — Secure REST object authorization and mutation endpoints

- **Owner:** Security
- **Affected:** all `src/Modules/**` REST routes, especially `ImageAltAudit.php`, `HeadlessSeoEndpoints.php`, `PageBuilderSeoWrappers.php`, schema/import endpoints
- **Problem:** Several routes use broad capabilities such as `edit_posts` or `upload_files` before mutating a specific post/media object. This permits users to alter objects they may not own.
- **Work:** Build a route inventory. Require `current_user_can( 'edit_post', $object_id )` for every object mutation and least-privilege capabilities for site-wide data. Define REST argument schemas, validation, and standard errors.
- **Acceptance criteria:**
  - [ ] Every route has a documented authorization model and automated anonymous/subscriber/author/editor/admin test.
  - [ ] Media bulk updates perform per-attachment capability checks.
  - [ ] State-changing cookie-authenticated routes require standard REST nonce handling.
  - [ ] Public routes have a documented data-classification review.

### PR-008 — Make uninstall and deactivation complete, safe, and multisite-correct

- **Owner:** Platform / Privacy
- **Affected:** `uninstall.php`, `src/Plugin.php`, queue/cron modules
- **Problem:** Uninstall deletes all SEO data without a retention choice, misses tables and cron hooks, and reuses main-site table names after `switch_to_blog()`. Deactivation does not clear many scheduled jobs or rewrite rules.
- **Work:** Offer an explicit “remove data on uninstall” setting defaulting to retain data. Recompute table names inside each blog context. Centralize scheduled-hook registration and cleanup; clear Action Scheduler jobs and fallback cron events. Flush rewrites on lifecycle transitions.
- **Acceptance criteria:**
  - [ ] Deactivation stops all plugin work and restores Core sitemap/rewrite behavior.
  - [ ] Uninstall removes all and only plugin data when deletion is opted in.
  - [ ] Multisite tests verify every site’s options, tables, transients, roles, and schedules.
  - [ ] Documentation accurately states behavior; no nonexistent “confirmation” is claimed.

---

## P1 — required before WordPress.org submission

### PR-009 — Remove page-builder integrations from v1.0

- **Owner:** Product / Platform
- **Affected:** `src/Modules/Admin/PageBuilderSeoWrappers.php`, `src/Plugin.php`, readme/features/tasklist/screenshots
- **Decision:** Out of scope for v1.0.
- **Work:** Remove registration and placeholder methods rather than implying support. Remove Elementor, Divi, and WPBakery claims and screens.
- **Acceptance criteria:**
  - [ ] No page-builder code, settings, API endpoints, or public compatibility claim ships in v1.0.
  - [ ] A future feature proposal defines supported versions and end-to-end tests before reintroduction.

### PR-010 — Fix cookie banner assets and re-scope compliance claims

- **Owner:** Privacy / UX
- **Affected:** `CookieBannerModule.php`, `src/assets/js/cookie-banner.js`, `build/`, readme
- **Problem:** Asset URLs point under `/src/build/`; “GDPR mode” merely displays a banner and does not block third-party tracking or retain proof of consent.
- **Work:** Use `AMEVERYWHERE_PLUGIN_URL` and asset metadata/versioning. Either implement a genuine consent-management integration with script categorization and withdrawal, or label the feature as a lightweight notice and remove GDPR/CCPA compliance claims.
- **Acceptance criteria:**
  - [ ] Browser tests confirm CSS/JS load from the production ZIP.
  - [ ] Consent behavior and limitations are explicit in UI and docs.
  - [ ] No legal/compliance guarantee is made without the technical controls to support it.

### PR-011 — Replace deprecated sitemap pings and safely coexist with SEO plugins

- **Owner:** SEO
- **Affected:** `SitemapModule.php`, `SitemapRouteManager.php`, settings/migration UI
- **Problem:** The plugin pings Google’s retired sitemap endpoint and unconditionally disables WordPress Core sitemaps, creating conflicts with established SEO plugins.
- **Work:** Remove Google pinging. Preserve sitemap discovery through `robots.txt` and provide optional Search Console guidance. Detect active sitemap/SEO providers; default to non-invasive mode and require an explicit migration/disable decision.
- **Acceptance criteria:**
  - [ ] No call is made to deprecated Google sitemap ping URLs.
  - [ ] Sitemap activation/deactivation does not leave stale rewrite rules.
  - [ ] Coexistence tests cover Core sitemaps and leading installed SEO-plugin states.

### PR-012 — Harden redirects and outbound fetches

- **Owner:** Security / Platform
- **Affected:** `RedirectManager.php`, `BrokenLinkChecker.php`, `CompetitorScraper.php`, `SchemaOutputValidator.php`, `PageSpeedDashboard.php`
- **Problem:** Redirect targets are not constrained to safe destinations; the link checker uses non-safe remote requests; remote scans can produce queue floods and SSRF-style internal network requests.
- **Work:** Validate redirect targets and use `wp_safe_redirect` where appropriate. Use safe HTTP APIs, protocol/host/IP allow/deny rules, response-size limits, request budgets, and administrator-visible rate limits.
- **Acceptance criteria:**
  - [ ] Tests reject loopback, link-local, private-network, unsupported-scheme, and redirect-chain fetch targets.
  - [ ] External redirect behavior is intentional, documented, and protected against open redirects.
  - [ ] Fetch jobs have cancellation, locking, bounded concurrency, retries/backoff, and observability.

### PR-013 — Make scans, queues, and cron scalable and observable

- **Owner:** Platform
- **Affected:** `QueueManager.php`, `BrokenLinkChecker.php`, `AuditScheduler.php`, rank/staleness/usage/privacy schedulers
- **Problem:** The link scan loads all IDs and schedules many events without a lock. Queue fallback ignores requested recurring intervals, failed jobs are rethrown without recovery, and monthly auditing uses an unregistered recurrence.
- **Work:** Standardize on Action Scheduler when available with a robust native fallback. Add locks, batch cursors, resumability, cancellation, retention, failure reporting, and a registered monthly schedule or an explicit alternative.
- **Acceptance criteria:**
  - [ ] A 10,000-post site scan has bounded memory, no duplicate concurrent run, and predictable request limits.
  - [ ] Cron failure/retry paths are tested.
  - [ ] All schedules are visible in a diagnostics screen and cleared on deactivation/uninstall.

### PR-014 — Preserve editorial and accessibility intent

- **Owner:** UX / SEO
- **Affected:** `ImageSeoModule.php`, `ImageAltAudit.php`, editor UI
- **Problem:** Empty alt attributes for decorative images are overwritten, and titles/alt text can change automatically on upload/save.
- **Work:** Stop mutating existing content automatically. Provide suggestions in the editor and bulk tools with preview, selection, undo, and an explicit “decorative” state.
- **Acceptance criteria:**
  - [ ] `alt=""` is preserved unless an authorized editor explicitly changes it.
  - [ ] Automatic filename-based alt generation is disabled by default or clearly consented to.
  - [ ] Accessibility tests cover decorative, informative, linked, and Gutenberg image cases.

### PR-015 — Repair WPCS/database findings and establish a static-analysis gate

- **Owner:** Platform / Security
- **Affected:** PHP sources, `phpcs.xml.dist` or equivalent, CI
- **Problem:** Focused WPCS security/database analysis reports 69 errors and 218 warnings. Some table-name diagnostics may be false positives, but they need safe construction and narrow documented suppressions.
- **Work:** Fix input handling, output escaping, nonce verification, safe redirects, queries, and direct DB access. Add a project ruleset and baseline only for reviewed unavoidable exceptions.
- **Acceptance criteria:**
  - [ ] CI runs WPCS with zero unreviewed errors.
  - [ ] Any suppression identifies why table identifiers are trusted and scoped.
  - [ ] Static analysis results are attached to every release candidate.

### PR-016 — Complete external-service governance and readme disclosure

- **Owner:** Privacy / Product
- **Affected:** `readme.txt`, privacy-policy text, all API clients and settings UI
- **Problem:** The readme omits services the code can call, including the AmEveryWhere backend, Anthropic, Ollama/custom hosts, Meta/Facebook, X, LinkedIn, Pinterest, and Google Trends. A fake Google Trends cookie is also shipped.
- **Work:** Remove unsupported/scraped services or replace them with official APIs and valid user configuration. Document each service, endpoint/domain, purpose, data transferred, trigger, account/key requirement, and privacy policy. Add consent where needed.
- **Acceptance criteria:**
  - [ ] No fake cookies, undocumented scraping, or undisclosed external requests remain.
  - [ ] External Services readme section exactly matches code and defaults.
  - [ ] Features requiring a paid or third-party account clearly say so before configuration.

### PR-017 — Reconcile product claims with implemented behavior

- **Owner:** Product / UX
- **Affected:** `readme.txt`, `README.md`, `features.md`, `about.md`, `tasklist.md`, `roadmap.md`, screenshots
- **Problem:** Documentation claims missing classes/features as “fully executed,” promises unimplemented builder support and compliance behavior, and mixes optional SaaS features with local behavior.
- **Work:** Build a feature inventory from executable code and tests. Remove unsupported claims; label beta/deferred functionality; document limits and compatibility.
- **Acceptance criteria:**
  - [ ] Every readme feature has a testable implementation and owner.
  - [ ] Absent classes such as `ProductTaxonomySeo`, `MultiLocationSchema`, `NapShortcodes`, `GoogleBusinessProfileOptimization`, `ChatbotCitationTracker`, `OrphanContentRemediationWorkflow`, and `TaxonomyBaseRemover` are removed from “complete” claims.
  - [ ] Screenshots show current shipped behavior only.

### PR-018 — Make admin experience safe and conflict-aware

- **Owner:** UX / SEO
- **Affected:** onboarding, admin settings, migration/import, upgrade prompts
- **Problem:** Core SEO changes are made with broad defaults, while user-facing controls do not adequately warn about conflicts, API costs, data mutation, or what is disabled.
- **Work:** Add a first-run compatibility scan, reversible migration plan, per-module enablement, clear defaults, change preview, and rollback instructions. Ensure upgrade prompts do not obstruct normal administration.
- **Acceptance criteria:**
  - [ ] A site with another SEO plugin receives a clear non-destructive coexistence choice.
  - [ ] Risky modules are disabled until explicitly enabled.
  - [ ] Admin notices are contextual, dismissible, accessible, and do not recur after dismissal.

### PR-019 — Validate schema and SEO output in real themes and plugins

- **Owner:** SEO / QA
- **Affected:** `MetaTagsGenerator.php`, `OpenGraphGenerator.php`, `SchemaGenerator.php`, WooCommerce schema, REST SEO endpoint
- **Problem:** Multiple components emit overlapping metadata/schema, and the REST endpoint invokes `wp_head` to scrape output without establishing the queried post context.
- **Work:** Generate structured payloads directly rather than scraping hook output. Prevent duplicate canonical, robots, Open Graph, and JSON-LD tags. Validate output with fixtures and supported schema requirements.
- **Acceptance criteria:**
  - [ ] Each tested page has one intended canonical and robots policy.
  - [ ] Schema payload is correct for the requested post, not global query state.
  - [ ] Tests cover default themes, block themes, WooCommerce products, archives, noindex, and common SEO-plugin coexistence.

### PR-020 — Build correct data migrations and import reliability

- **Owner:** Platform / SEO
- **Affected:** migration/import modules, onboarding, tests
- **Problem:** The importer is synchronous and high-volume, migration claims exceed actual source coverage, and conflict/rollback behavior is inadequate for production sites.
- **Work:** Add capability checks, batching, dry-run summaries, idempotency markers, backup/rollback, and source-specific field mapping tests. Do not enable conflicting output until the import review is accepted.
- **Acceptance criteria:**
  - [ ] Imports of 50k posts are resumable and do not time out.
  - [ ] Preview counts exactly match executed writes.
  - [ ] Rollback restores pre-import metadata.

### PR-021 — Correct lifecycle timing and rewrite handling

- **Owner:** Platform
- **Affected:** `Plugin.php`, sitemap route manager, activation hooks
- **Problem:** Dynamic listeners for `ameverywhere_activation` are registered only during normal boot, so they are not reliably present when activation hooks execute. Rewrite flushes therefore depend on later setup activity.
- **Work:** Invoke required activation work directly from the activation callback, including a controlled rewrite registration/flush. Do not rely on listeners that are not registered in activation context.
- **Acceptance criteria:**
  - [ ] Sitemap/IndexNow routes work immediately after activation without visiting setup.
  - [ ] Deactivation flushes routes once and leaves no plugin routes active.
  - [ ] Lifecycle tests validate pretty and plain permalink modes.

---

## P2 — post-release hardening and product discipline

### PR-022 — Reduce default frontend and request-path cost

- **Owner:** Platform
- **Affected:** plugin boot graph, frontend hooks, asset loading
- **Work:** Measure queries, memory, hooks, and outbound calls on front-end/admin requests. Lazy-load admin-only modules, gate optional features, avoid full-site scans in request paths, and add performance budgets.
- **Acceptance criteria:**
  - [ ] Benchmark report compares baseline WordPress with plugin enabled on representative content.
  - [ ] No network request or expensive scan occurs during ordinary page views without a user-enabled feature.
  - [ ] Regression budgets run in CI.

### PR-023 — Improve observability without exposing sensitive data

- **Owner:** Platform / Support
- **Affected:** queue, external clients, diagnostics UI
- **Work:** Add structured local diagnostics for job state, API response class, retries, migration state, asset checks, and configuration health. Redact API keys, tokens, URLs containing credentials, and personal data.
- **Acceptance criteria:**
  - [ ] Administrators can diagnose a failed integration without enabling `WP_DEBUG`.
  - [ ] Logs have retention controls and privacy documentation.

### PR-024 — Normalize capability architecture

- **Owner:** Security / Product
- **Affected:** `CustomSeoUserRoles.php`, modules using `manage_options`/`edit_posts`
- **Work:** Decide whether custom SEO capabilities are needed; if retained, consistently enforce them. Do not reapply role changes on every `init`, and respect custom role configuration.
- **Acceptance criteria:**
  - [ ] Capability mapping is documented and tested for all WordPress roles.
  - [ ] Role changes occur during activation/settings updates only.
  - [ ] No endpoint bypasses the documented capability model.

### PR-025 — Improve internationalization, accessibility, and copy quality

- **Owner:** UX / QA
- **Affected:** admin UI, emails, notices, REST errors, POT generation
- **Work:** Replace untranslated hard-coded strings, test keyboard and screen-reader flows, remove emoji-only meaning, and ensure generated messages are escaped and localized.
- **Acceptance criteria:**
  - [ ] POT is regenerated from the release source.
  - [ ] Key admin flows pass keyboard and screen-reader smoke testing.
  - [ ] All user-visible operational messages are translatable.

### PR-026 — Formalize support and compatibility policy

- **Owner:** Product / Support
- **Affected:** readme, support docs, CI matrix
- **Work:** Publish supported WordPress/PHP/database/browser versions, hosting prerequisites, cron requirements, external service limitations, and conflict policy. Test the declared matrix.
- **Acceptance criteria:**
  - [ ] `Tested up to` is verified for the released WordPress version.
  - [ ] Support boundaries and rollback guidance are present in the readme.
  - [ ] CI exercises every declared PHP and WordPress version.

---

## Verification plan and required artifacts

| Gate | Required evidence |
| --- | --- |
| Lifecycle | Fresh install, activation, reactivation, deactivation, uninstall, and upgrade tests on single-site and multisite |
| Security | REST route inventory, role matrix, secret-leak regression tests, dependency/license review |
| Privacy | Export/erase/retention integration tests and finalized privacy-policy text |
| SEO correctness | Fixture-based HTML/schema assertions, sitemap validation, coexistence matrix, Google API eligibility tests |
| Performance | 1k/10k-post scan benchmarks, queue limits, cron recovery tests |
| UX | Browser smoke tests for setup, risky-feature opt-in, restore/rollback, cookie notice, migration, and admin accessibility |
| Packaging | Clean-commit ZIP manifest, Plugin Check report, WPCS report, PHP lint, tests, asset URL smoke test |
| Documentation | Feature inventory signed off by engineering/product; external-services and uninstall behavior verified against code |

## Recommended release sequence

1. Complete PR-001 through PR-008; do not create a release candidate before then.
2. Complete PR-009 through PR-021 and assemble the verification artifacts.
3. Freeze scope, build from a clean commit, and run all release gates against the generated ZIP.
4. Submit only the verified ZIP, with WordPress.org assets staged outside plugin trunk as required.
5. Treat PR-022 through PR-026 as the first post-release hardening milestone.
