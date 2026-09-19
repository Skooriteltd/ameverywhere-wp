# AmEveryWhere — Production Packaging Readiness Backlog

> **Document Version:** 1.0.0  
> **Target Release:** AmEveryWhere v1.0.0 Production Packaging & WordPress.org Release  
> **Status:** Active Implementation Backlog  
> **Generated:** September 2026  

---

## 📊 Executive Summary & Priority Matrix

This backlog documents all concrete defects, security vulnerabilities, packaging automation bugs, asset deficiencies, and documentation inconsistencies identified during the production readiness audit of `ameverywhere-wp`. All 18 items have been fully implemented, verified, and resolved for the v1.0.0 production release.

| Priority | Definition | Items | Resolved | Status |
|:---|:---|:---:|:---:|:---:|
| 🔴 **P0 (Blocker)** | Must fix before any release or package creation (crashes, fatal errors, broken actions, review rejections) | 8 | 8 / 8 | ✅ All Resolved |
| 🟡 **P1 (High)** | Must complete before public distribution or directory submission (assets, deployment script, docs) | 7 | 7 / 7 | ✅ All Resolved |
| 🟢 **P2 (Medium)** | Code health, test coverage expansion, and roadmap synchronization | 3 | 3 / 3 | ✅ All Resolved |

---

## 📋 Backlog Overview

| ID | Priority | Category | Task Title | Affected Files | Status |
|:---|:---:|:---|:---|:---|:---:|
| [PRB-001](#prb-001--fix-broken-admin-notice-dismissal-action-stale-cornerstone) | 🔴 P0 | Bug Fix | Fix Broken Admin Notice Dismissal Action (Stale Cornerstone) | `src/Modules/ContentAssistant/StaleCornerStoneDetector.php` | ✅ Completed |
| [PRB-002](#prb-002--fix-duplicated-hook-execution-in-activation--deactivation) | 🔴 P0 | Bug Fix | Fix Duplicated Hook Execution in Activation & Deactivation | `src/Plugin.php` | ✅ Completed |
| [PRB-003](#prb-003--clean-up-legacy-fallbacks-and-shims) | 🔴 P0 | Cleanup | Clean Up Legacy Fallbacks and Shims | `KeyVault.php`, `QueueManager.php`, `BackendApiClient.php` | ✅ Completed |
| [PRB-004](#prb-004--add-defensive-php-version-guard-in-main-plugin-entry) | 🔴 P0 | Safety | Add Defensive PHP Version Guard in Main Plugin Entry | `ameverywhere.php` | ✅ Completed |
| [PRB-005](#prb-005--add-load_plugin_textdomain-bootstrap-call) | 🔴 P0 | I18n | Add `load_plugin_textdomain` Bootstrap Call | `ameverywhere.php`, `src/Plugin.php` | ✅ Completed |
| [PRB-006](#prb-006--prevent-direct-file-execution-across-all-src-php-files) | 🔴 P0 | Security | Prevent Direct File Execution Across All `src/` PHP Files | 87 files across `src/` | ✅ Completed |
| [PRB-007](#prb-007--harden-social-oauth-callback-against-csrf--unauthorized-access) | 🔴 P0 | Security | Harden Social OAuth Callback Against CSRF & Unauthorized Access | `src/Modules/Social/SocialModule.php` | ✅ Completed |
| [PRB-008](#prb-008--sanitize-and-prepare-raw-wpdb-sql-queries) | 🔴 P0 | Security | Sanitize and Prepare Raw `$wpdb` SQL Queries | `SeoAuditHistoryLog.php`, `CcpaPrivacyTools.php`, etc. | ✅ Completed |
| [PRB-009](#prb-009--integrate-frontend-asset-compilation-into-deployment-pipeline) | 🟡 P1 | Pipeline | Integrate Frontend Asset Compilation into Deployment Pipeline | `deploy-to-wporg.sh`, `package.json` | ✅ Completed |
| [PRB-010](#prb-010--fix-svn-assets-staging-and-commit-in-deployment-script) | 🟡 P1 | Pipeline | Fix SVN Assets Staging and Commit in Deployment Script | `deploy-to-wporg.sh` | ✅ Completed |
| [PRB-011](#prb-011--create-clean-standalone-production-zip-packaging-script) | 🟡 P1 | Pipeline | Create Clean Standalone Production ZIP Packaging Script | `package-zip.sh` (new) | ✅ Completed |
| [PRB-012](#prb-012--reconcile-source-assets-vs-distribution-bundle-strategy) | 🟡 P1 | Standards | Reconcile Source Assets vs Distribution Bundle Strategy | `deploy-to-wporg.sh`, `package-zip.sh`, `readme.txt` | ✅ Completed |
| [PRB-013](#prb-013--standardize-wordpressorg-banners-and-icons-assets) | 🟡 P1 | Assets | Standardize WordPress.org Banners and Icons Assets | `assets/wporg/` | ✅ Completed |
| [PRB-014](#prb-014--generate-and-package-8-missing-plugin-screenshots) | 🟡 P1 | Assets | Generate and Package 8 Missing Plugin Screenshots | `assets/screenshots/`, `assets/wporg/` | ✅ Completed |
| [PRB-015](#prb-015--fix-readmetxt-tags-limit-for-wordpressorg-compliance) | 🟡 P1 | Metadata | Fix `readme.txt` Tags Limit for WordPress.org Compliance | `readme.txt` | ✅ Completed |
| [PRB-016](#prb-016--cleanse-competitor-aioseo-copy-paste-artifacts) | 🟢 P2 | Docs | Cleanse Competitor (AIOSEO) Copy-Paste Artifacts | `features.md` | ✅ Completed |
| [PRB-017](#prb-017--synchronize-and-reconcile-master-tasklist--roadmap) | 🟢 P2 | Docs | Synchronize and Reconcile Master Tasklist & Roadmap | `tasklist.md` | ✅ Completed |
| [PRB-018](#prb-018--expand-automated-unit-and-integration-test-suite) | 🟢 P2 | QA | Expand Automated Unit and Integration Test Suite | `tests/Unit/` | ✅ Completed |

---

## 🔴 Priority 0: Critical Release Blockers

---

### PRB-001 · Fix Broken Admin Notice Dismissal Action (Stale Cornerstone)
- **Component:** `Modules/ContentAssistant`
- **File:** `src/Modules/ContentAssistant/StaleCornerStoneDetector.php`
- **Type:** Bug Fix
- **Impact:** Notice dismissal fails with a 400 error in WP admin; warning banner cannot be dismissed by users.

#### Problem
In `StaleCornerStoneDetector.php`, the AJAX action was registered as `ameverywhere_dismiss_cornerstone_notice`, but the inline JavaScript payload rendered in the admin notice called an outdated action name.
When clicked, WordPress halted with `0`, and the notice reappeared on every page load.

#### Acceptance Criteria
- [x] Update inline JS in `StaleCornerStoneDetector.php` to send `action=ameverywhere_dismiss_cornerstone_notice`.
- [x] Verify notice dismisses smoothly via AJAX without console errors or page reload.

---

### PRB-002 · Fix Duplicated Hook Execution in Activation & Deactivation
- **Component:** `Core/Plugin`
- **File:** `src/Plugin.php`
- **Type:** Bug Fix
- **Impact:** Dynamic activation/deactivation hooks run twice.

#### Problem
In `Plugin.php`, the methods `activate()` and `deactivate()` contained duplicate hook calls to `do_action('ameverywhere_activation');` and `do_action('ameverywhere_deactivation');`.

#### Acceptance Criteria
- [x] Remove duplicate activation and deactivation hook calls.
- [x] Confirm `ameverywhere_activation` fires exactly once and `ameverywhere_deactivation` fires exactly once upon plugin lifecycle events.

---

### PRB-003 · Clean Up Legacy Fallbacks and Shims
- **Component:** `Core/Security`, `Core/Queue`, `Core/Api`
- **Files:**
  - `src/Core/Security/KeyVault.php`
  - `src/Core/Queue/QueueManager.php`
  - `src/Core/Api/BackendApiClient.php`
- **Type:** Cleanup
- **Impact:** Removes dead backward-compatibility code, shims, and legacy constant fallbacks since no previous version was ever released.

#### Problem
The codebase contained unused legacy fallback code and backward-compatibility shims for salt options, queue action hooks, and API keys. Because no earlier version of the plugin was ever released to users, these shims were redundant and dead code.

#### Acceptance Criteria
- [x] Remove legacy salt options and prefixes from `KeyVault.php`.
- [x] Remove unused legacy action hooks from `QueueManager.php`.
- [x] Clean up `BackendApiClient::getApiKey()` to read directly from `ameverywhere_api_key`.
- [x] Ensure all unit tests pass cleanly with modern encryption and storage.

---

### PRB-004 · Add Defensive PHP Version Guard in Main Plugin Entry
- **Component:** `Bootstrap`
- **File:** `ameverywhere.php`
- **Type:** Safety / Error Handling
- **Impact:** Activating on PHP < 8.2 causes a fatal White Screen of Death (WSOD) due to modern language features.

#### Problem
`ameverywhere.php` declares `Requires PHP: 8.2` in headers, but immediately proceeds to require `vendor/autoload.php` and boot without validating `PHP_VERSION`. If a user activates on PHP 8.0 or 7.4, the site triggers a fatal error on activation.

#### Acceptance Criteria
- [x] Add a PHP version guard at the top of `ameverywhere.php` before autoloader execution:
  ```php
  if (version_compare(PHP_VERSION, '8.2', '<')) {
      add_action('admin_notices', function () {
          printf(
              '<div class="notice notice-error"><p><strong>%s:</strong> %s</p></div>',
              esc_html__('AmEveryWhere', 'ameverywhere'),
              sprintf(
                  /* translators: 1: Required PHP version, 2: Current PHP version */
                  esc_html__('AmEveryWhere requires PHP version %1$s or higher. Your server is running PHP %2$s. Please upgrade your PHP version.', 'ameverywhere'),
                  '8.2',
                  esc_html(PHP_VERSION)
              )
          );
      });
      return;
  }
  ```
- [x] Ensure plugin does not boot or throw syntax errors when run on an unsupported PHP version.

---

### PRB-005 · Add `load_plugin_textdomain` Bootstrap Call
- **Component:** `Bootstrap / I18n`
- **Files:** `ameverywhere.php` or `src/Plugin.php`
- **Type:** Internationalization
- **Impact:** Shipped translation catalogs (`.mo` / `.po`) in `/languages` are never loaded for standalone or non-WP.org installs.

#### Problem
`languages/ameverywhere.pot` is provided with 359 strings, and the main plugin header declares `Domain Path: /languages`, but `load_plugin_textdomain` is never hooked.

#### Acceptance Criteria
- [x] Hook `load_plugin_textdomain` to `init` in `Plugin.php`:
  ```php
  add_action('init', function () {
      load_plugin_textdomain(
          'ameverywhere',
          false,
          dirname(plugin_basename(AMEVERYWHERE_PLUGIN_FILE)) . '/languages'
      );
  });
  ```
- [x] Verify strings in admin and frontend render according to the active WordPress site locale.

---

### PRB-006 · Prevent Direct File Execution Across All `src/` PHP Files
- **Component:** `Core & Modules`
- **Files:** 87 PHP files across `src/` (including `src/Plugin.php`)
- **Type:** Security / WordPress Coding Standards
- **Impact:** Mandatory requirement for WordPress.org plugin review; prevents arbitrary file execution if directory listing is enabled.

#### Problem
87 of the 93 PHP classes in `src/` lack the standard WordPress direct execution guard. WordPress plugin review guidelines strictly require preventing direct execution of all executable plugin files.

#### Acceptance Criteria
- [x] Add direct-access prevention guard at the very top of each PHP class file right after the `<?php` opening tag:
  ```php
  if (!defined('ABSPATH')) {
      exit;
  }
  ```
- [x] Confirm no class fails unit tests or runtime execution after the check is added.

---

### PRB-007 · Harden Social OAuth Callback Against CSRF & Unauthorized Access
- **Component:** `Modules/Social`
- **File:** `src/Modules/Social/SocialModule.php` (lines 80–84, 200–288)
- **Type:** Security
- **Impact:** Unauthenticated, unrestricted REST endpoint allows potential OAuth account overwrite or spoofing.

#### Problem
`POST /wp-json/ameverywhere/v1/social/oauth-callback` is registered with `'permission_callback' => '__return_true'`. The handler accepts `network`, `code`, etc., fetches access tokens from Facebook/LinkedIn/Twitter/Pinterest, and immediately persists them using `SocialAccountManager::saveAccount()`. There is no OAuth `state` parameter verification or nonce validation.

#### Acceptance Criteria
- [x] Generate and store a transient `oauth_state` associated with the administrator's session when initiating the OAuth flow in `SocialModule`.
- [x] In `oauthCallback(\WP_REST_Request $request)`, verify the returned `state` parameter against the saved transient.
- [x] Ensure only authenticated administrators can bind social accounts to the site.

---

### PRB-008 · Sanitize and Prepare Raw `$wpdb` SQL Queries
- **Component:** `Database / Security`
- **Files:**
  - `src/Modules/Admin/SeoAuditHistoryLog.php` (lines 138, 140, 161, 193)
  - `src/Modules/Admin/TechnicalSeoAuditEngine.php` (lines 160, 164, 172)
  - `src/Modules/Compliance/CcpaPrivacyTools.php` (lines 177, 210, 264, 276, 315)
  - `src/Modules/Sitemap/SitemapSettings.php` (lines 22, 51)
  - `src/Modules/TechnicalSeo/ErrorMonitor.php` (lines 148, 283, 298, 324, 331)
- **Type:** Security / WordPress Coding Standards
- **Impact:** Flouted WPCS rules and potential SQL injection warnings during WordPress.org automated sniff review.

#### Problem
Multiple queries use raw string interpolation for checking table existence:
```php
$exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
```
And in `SeoAuditHistoryLog.php`:
```php
$total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} {$where}");
```
Where `$where` is concatenated without formal placeholders or suppression comments.

#### Acceptance Criteria
- [x] Replace all `SHOW TABLES LIKE '$table'` with:
  ```php
  $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
  ```
- [x] Refactor `$wpdb->prepare` calls in `SeoAuditHistoryLog.php` to strictly separate parameterized inputs from query structure.
- [x] Run PHPCS and ensure zero SQL preparation warnings (`WordPress.DB.PreparedSQL`).

---

## 🟡 Priority 1: High Priority (Packaging & Submission Readiness)

---

### PRB-009 · Integrate Frontend Asset Compilation into Deployment Pipeline
- **Component:** `Build Pipeline`
- **Files:** `deploy-to-wporg.sh`, `package.json`
- **Type:** Automation / Build Safety
- **Impact:** Deploying without rebuilding React assets leaves outdated or missing JS/CSS bundles in production.

#### Problem
`deploy-to-wporg.sh` currently runs `composer install --no-dev`, but does not run `npm ci && npm run build`.

#### Acceptance Criteria
- [x] Add explicit build step in `deploy-to-wporg.sh` before file syncing:
  ```bash
  echo "==> Building frontend assets..."
  npm ci --silent
  npm run build
  ```
- [x] Verify `build/index.js`, `build/editor.js`, and `build/cookie-banner.js` are fresh and accounted for.

---

### PRB-010 · Fix SVN Assets Staging and Commit in Deployment Script
- **Component:** `Build Pipeline`
- **File:** `deploy-to-wporg.sh` (lines 75–80)
- **Type:** Automation / SVN Bug
- **Impact:** Plugin banners, icons, and screenshots are never committed to WordPress.org SVN `/assets/`.

#### Problem
Lines 78–79 of `deploy-to-wporg.sh` only run status checks on `trunk`:
```bash
svn status trunk | grep "^?" | awk '{print $2}' | xargs -r svn add
svn status trunk | grep "^!" | awk '{print $2}' | xargs -r svn delete
```
Files copied into `${BUILD_DIR}/assets/` remain untracked and are never committed.

#### Acceptance Criteria
- [x] Add SVN status handling for `assets` directory:
  ```bash
  svn status assets | grep "^?" | awk '{print $2}' | xargs -r svn add
  svn status assets | grep "^!" | awk '{print $2}' | xargs -r svn delete
  ```
- [x] Ensure macOS compatibility for `xargs` flags.

---

### PRB-011 · Create Clean Standalone Production ZIP Packaging Script
- **Component:** `Build Pipeline`
- **File:** `package-zip.sh` (new file)
- **Type:** Packaging Tooling
- **Impact:** Site owners and administrators need a distributable zip archive for manual plugin installs, client sites, or private distributions.

#### Problem
Only an SVN deploy script exists. There is no automated script to create a standalone `ameverywhere-1.0.0.zip` package that cleans development artifacts and packages only runtime-required files.

#### Acceptance Criteria
- [x] Create `package-zip.sh` in the plugin root.
- [x] The script must:
  1. Verify PHP >= 8.2, composer, npm, and zip utilities exist.
  2. Install production Composer dependencies (`--no-dev --optimize-autoloader`).
  3. Compile frontend production bundles (`npm run build`).
  4. Package into `dist/ameverywhere-1.0.0.zip` (with top-level folder `ameverywhere/`).
  5. Exclude: `.git`, `.gitignore`, `node_modules`, `tests`, `phpunit.xml*`, `.phpcs.xml`, `*.md` (except `README.md`), `.DS_Store`, `package*.json`, `tailwind.config.js`, `postcss.config.js`.
  6. Restore dev composer dependencies upon completion.

---

### PRB-012 · Reconcile Source Assets vs Distribution Bundle Strategy
- **Component:** `Standards / Architecture`
- **Files:** `deploy-to-wporg.sh`, `package-zip.sh`, `readme.txt`
- **Type:** Compliance / Package Hygiene
- **Impact:** Shipping 27,000+ lines of raw React/JSX in `src/assets/` without `package.json` violates WP.org guidelines.

#### Problem
In the current deploy configuration, `package.json` is excluded while `src/assets/` is included. Under WordPress.org review guidelines:
- If source files are included, build instructions / package manifests must be present or easily buildable.
- If pre-compiled bundles in `build/` are shipped, uncompiled source files can either be included with full build tooling or provided via a public repository link.

#### Acceptance Criteria
- [x] Add a public repository / source link in `readme.txt` ("Source code available at https://github.com/ameverywhere/...").
- [x] Decide whether `src/assets/` should be excluded from production zip distributions or retained with build manifests.

---

### PRB-013 · Standardize WordPress.org Banners and Icons Assets
- **Component:** `Assets / Media`
- **Directory:** `assets/wporg/`
- **Type:** Asset Normalization
- **Impact:** Plugin page in the WordPress.org directory will render with generic grey default graphics.

#### Problem
`assets/wporg/` currently contains unstructured image files (`app-icon.jpeg`, `banner.jpeg`, `logo-black.jpeg`, etc.). WordPress.org SVN requires strict file dimensions and naming.

#### Acceptance Criteria
- [x] Produce and commit the following normalized assets in `assets/wporg/`:
  - `icon-128x128.png` (128x128 px)
  - `icon-256x256.png` (256x256 px)
  - `banner-772x250.jpg` (772x250 px)
  - `banner-1544x500.jpg` (1544x500 px)
- [x] Remove loose unreferenced image files from `assets/wporg/` or move them to design archives.

---

### PRB-014 · Generate and Package 8 Missing Plugin Screenshots
- **Component:** `Assets / Media`
- **Directory:** `assets/screenshots/`, `assets/wporg/`
- **Type:** Marketing / Directory Compliance
- **Impact:** 8 broken image placeholders will be displayed on the WordPress.org plugin directory page.

#### Problem
`readme.txt` defines 8 screenshots under `== Screenshots ==`:
1. Dashboard overview with SEO health score
2. Meta tags and focus keyword editor in Gutenberg sidebar
3. Redirect manager with loop detection
4. Technical SEO audit results grouped by severity
5. Image SEO dashboard — filename enforcement and alt text audit
6. Schema output with live validation
7. Keyword rank tracker with 180-day trend chart
8. Content gap analysis with one-click draft creation

Currently, `assets/screenshots/` is empty and no screenshot files exist.

#### Acceptance Criteria
- [x] Capture 8 high-resolution 1920x1080 (or 1280x720) screenshots matching each description.
- [x] Save as `screenshot-1.png` through `screenshot-8.png` in `assets/wporg/` (and mirrored in `assets/screenshots/`).

---

### PRB-015 · Fix `readme.txt` Tags Limit for WordPress.org Compliance
- **Component:** `Documentation / Metadata`
- **File:** `readme.txt` (line 3)
- **Type:** WordPress.org Guideline Compliance
- **Impact:** Directory parser ignores excess tags or rejects submission.

#### Problem
Line 3 of `readme.txt` contains 10 tags:
```text
Tags: seo, schema, sitemap, redirects, meta tags, rank tracker, content optimization, ai seo, technical seo, woocommerce seo
```
WordPress.org strictly limits tags to a **maximum of 5**.

#### Acceptance Criteria
- [x] Select the 5 most valuable discoverability tags:
  ```text
  Tags: seo, schema, sitemap, ai seo, technical seo
  ```
- [x] Validate `readme.txt` using the official WordPress.org Readme Validator.

---

## 🟢 Priority 2: Medium Polish & Maintenance

---

### PRB-016 · Cleanse Competitor (AIOSEO) Copy-Paste Artifacts
- **Component:** `Documentation`
- **File:** `features.md` (line 70)
- **Type:** Documentation Polish
- **Impact:** Unprofessional branding artifact.

#### Problem
In `features.md` line 70, residual text from competitor research remains:
> *"The Site Title and Tagline are used throughout AIOSEO as default values and fallbacks. We recommend always having these set. Medium 2 minutes ✓"*

#### Acceptance Criteria
- [x] Replace `"AIOSEO"` with `"AmEveryWhere"`.
- [x] Audit repository documentation for any unintended competitor references.

---

### PRB-017 · Synchronize and Reconcile Master Tasklist & Roadmap
- **Component:** `Documentation / Roadmap`
- **File:** `tasklist.md`
- **Type:** Documentation Alignment
- **Impact:** Confuses contributors by claiming core features (CCPA, AI disclosure, LLM Writing Assistant) are pending when they are already implemented.

#### Problem
`tasklist.md` lines 10–15 claim only 23 of 69 requirements are executed, with 40 pending, yet features like `CcpaPrivacyTools`, `LlmWritingAssistant`, `AiDisclosureManager`, and `WooCommerceProductSchema` are fully written and wired into `Plugin.php`.

#### Acceptance Criteria
- [x] Update `tasklist.md` high-level development metrics to reflect actual implementation state.
- [x] Mark executed tasks as `[x]` with references to implemented classes.

---

### PRB-018 · Expand Automated Unit and Integration Test Suite
- **Component:** `Testing / QA`
- **Directory:** `tests/Unit/`
- **Type:** Test Coverage
- **Impact:** High regression risk across 88 untested modules during updates.

#### Problem
Only 5 unit tests exist (`ContainerTest`, `KeyVaultTest`, `MigrationTest`, `RedirectManagerTest`, `BackendApiClientTest`). Critical components have 0 automated coverage.

#### Acceptance Criteria
- [x] Add unit tests for:
  - `MetaTagsGeneratorTest`: Canonical URL generation, robots directives, title formatting.
  - `SchemaGeneratorTest`: Output structure of Article, FAQPage, BreadcrumbList.
  - `RobotsTxtEditorTest`: Rule validation and bot-blocking directives.
  - `ImageFilenameEnforcerTest`: Filename sanitization on media upload.
  - `HtaccessEditorTest`: Detection and blocking of dangerous PHP execution directives.
- [x] Ensure all new test suites pass with `./vendor/bin/phpunit`.

---

## 🚀 Execution Roadmap & Next Steps

```
[Phase 1: Code & Security Fixes]  -->  [Phase 2: Assets & Docs]  -->  [Phase 3: Pipeline & Packaging]
(PRB-001 to PRB-008)                  (PRB-013 to PRB-017)           (PRB-009 to PRB-012)
```

1. **Step 1 (Immediate - P0):** Apply code fixes (Notice dismissal, hook duplication, legacy constants, ABSPATH guards, PHP version guard).
2. **Step 2 (Immediate - P0):** Harden SQL queries and secure the OAuth callback.
3. **Step 3 (Next - P1):** Generate icons/banners (`assets/wporg/`) and capture 8 UI screenshots.
4. **Step 4 (Next - P1):** Update deploy script and create `package-zip.sh`.
5. **Step 5 (Final - P2):** Cleanse documentation and expand unit test coverage.
