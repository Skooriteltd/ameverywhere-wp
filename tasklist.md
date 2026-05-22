# RankSavvy Master Tasklist & Progress Tracker

> **Document Version:** 1.2  
> **Target Platform:** RankSavvy Full Suite (WordPress, Headless API, Laravel Package)  
> **Linked Specification:** [specification.md](file:///Applications/XAMPP/xamppfiles/htdocs/ranksavvy/specification.md)  
> **Last Updated:** May 2026

---

## 📊 High-Level Development Metrics
- **Total Roadmap Requirements:** 69
- **Fully Executed:** 23
- **Partially Executed (Foundational Scaffold Complete):** 6
- **Pending Implementation:** 40

---

## 🛠️ Phase 0: Core Architecture & Setup
*Establish clean PSR-compliant OOP boundaries, DI container, event router, and database schema.*
  - Conforms to PSR-12 and WordPress standards.
  - Autoload mapping established for namespace `RankSavvy\`.
  - Created dynamic event dispatcher managing core Actions and Filters dynamically.
  - Booted React Admin Panel with styled components.
  - Webpack bundles (`editor.js`, `index.js`) compiled safely.
  - Installer creates custom tables for redirects, 404 monitoring logs, and sitemap settings.
  - Background queue runner using `wp_schedule_single_event` to execute jobs asynchronously without blocking page generation.

---

## 🚀 Phase 1: Core Foundation (MVP)
*Essential technical and content SEO automation.*

### Core SEO Foundation

  - Blocks GPTBot, CCBot, Google-Extended, etc. via user-agent header rules and proactive 403 blocks.

### Content & Readability Checklist

  - Scans keyword matches inside Headings, paragraphs, and ALT attributes.
  - Interactive sidebar offering visual warnings for keyword gaps.
  - Weighted algorithmic scores measuring formatting and density.
  - Computes exact Flesch-Kincaid ease scores.
  - Enforces 1,000+ words and strict internal link checks (minimum 3 links) when marked as cornerstone.

### Point-and-Click Schema markup
  - Interactive forms for Article, Product, FAQPage, HowTo, and LocalBusiness schemas.
  - Renders FAQ dropdown blocks, interactive stars ratings, and price lists.
- [x] **Task 1.16: Rich Snippets Schema Validator** `[SD-003]` *(Priority: P1)*
  - *Status: Fully Executed*
  - Connect custom rendered markup to Google Rich Results API validator to display code health.

### Social Media Integrations
 - integrate popular social media platforms and provide the ability to add multiple accounts/profiles per platform
  - Outputs correct og:title, og:description, and Twitter meta.
- [x] **Task 1.18: Direct Social Sharing API & Dashboard triggers** `[SM-004]`
  - Build active visual sharing buttons inside post editor and dashboard list.
  - Integrate API sharing triggers for Facebook, X (Twitter), LinkedIn, and Pinterest using SDK endpoints.
- [x] **Task 1.19: Live Social Sharing Preview Editor** `[SM-002]` *(Priority: P1)*
  - *Status: Executed*
  - Visual cards exist, but need timeline-faithful visual previews mockups in React admin sidebar.
- [x] **Task 1.20: Global Default Fallback Share Image** `[SM-003]` *(Priority: P1)*
  - File-uploader setting to define fallback image for cards when posts lack featured thumbnails.

### AI Foundation & Subscriptions Prep
- [x] **Task 1.21: Secure API Key Vault** `[AI-INF-001]` *(Priority: P0)*
  - *Status: Executed*
  - Key fields exist in admin settings, but secure AES-256 database-level encryption hooks need implementation.
- [x] **Task 1.22: Model Abstraction Gateway** `[AI-INF-002]` *(Priority: P0)*
  - *Status: Executed*
  - Create standardized PHP class to route prompts and fallback elegantly across providers.
- [/] **Task 1.23: Feature Flag Gating System** `[AI-INF-003, BILL-001, BILL-002]` *(Priority: P0)*
  - *Status: Partially Executed*
  - UI options dynamically check capabilities, but need integration with SaaS subscriptions sync.
- [x] **Task 1.24: Local Ollama Endpoint integration** `[AI-INF-004]` *(Priority: P1)*
  - *Status: Executed*
  - Support custom local server URLs with test connection ping test.
- [ ] **Task 1.25: Usage Metering and Limits** `[AI-INF-005, BILL-004]` *(Priority: P1)*
  - *Status: Pending*
  - Tracks raw token usage and limits weekly counts based on admin restrictions.
- [ ] **Task 1.26: Subscription Upgrade Prompt Flows** `[BILL-003]` *(Priority: P1)*
  - *Status: Pending*
  - In-dashboard alerts and guides linking users to premium upgrades.

### US Compliance Foundation
- [ ] **Task 1.27: CCPA Privacy Tools** `[COMP-001]` *(Priority: P1)*
  - *Status: Pending*
  - Automated export/deletion tools for user search tracking metrics.
- [/] **Task 1.28: FTC AI-Disclosure Labels** `[COMP-002]` *(Priority: P1)*
  - *Status: Partially Executed*
  - Basic editor controls exist, but dynamic labeling script output is pending.

---

## 📈 Phase 2: Growth & Optimization
*Advanced vertical and technical features.*

### Advanced Content Intelligence
- [ ] **Task 2.1: LLM-Driven Real-time Writing Assistant** `[ACO-001]` *(Priority: P1)*
  - *Status: Pending (⚠️ Guardrails Required)*
  - *Note: Performance Risk. Requires 3-second minimum typing debounce or strictly on-demand click triggers to prevent API/server thread saturation.*
- [ ] **Task 2.2: Competitive Content Gap Engine** `[ACO-002]` *(Priority: P1)*
  - *Status: Pending*
- [ ] **Task 2.3: Contextual Internal Link Recommendations** `[ACO-004]` *(Priority: P1)*
  - *Status: Pending*
- [ ] **Task 2.4: Search Intent Classifier** `[ACO-003]` *(Priority: P2)*
  - *Status: Pending*
- [ ] **Task 2.5: SEO Metadata Version History Logs** `[ACO-005]` *(Priority: P2)*
  - *Status: Pending*
- [ ] **Task 2.6: Morphological Focus Keyword Recognition** `[ACO-006]` *(Priority: P2)*
  - *Status: Pending*
- [ ] **Task 2.7: Inclusive Language Compliance scanner** `[ACO-007]` *(Priority: P2)*
  - *Status: Pending*
- [ ] **Task 2.8: Word Complexity & Simplicity recommendations** `[ACO-008]` *(Priority: P2)*
  - *Status: Pending*



### Verticals: Local, E-Commerce, & Headless APIs
- [ ] **Task 2.19: WooCommerce Dynamic Schema mapper** `[EC-001]` *(Priority: P1)*
  - *Status: Pending*
- [ ] **Task 2.20: Product Category & Taxonomies SEO settings** `[EC-002]` *(Priority: P1)*
  - *Status: Pending*
- [ ] **Task 2.21: Headless WordPress REST API SEO metadata CRUD** `[HEAD-001]` *(Priority: P1)*
  - *Status: Pending*
- [ ] **Task 2.22: Multi-Location Business Locations schema manager** `[LB-002]` *(Priority: P2)*
  - *Status: Pending*
- [ ] **Task 2.23: Dynamic Business NAP Shortcode helpers** `[LB-003]` *(Priority: P2)*
  - *Status: Pending*
- [ ] **Task 2.24: Google Business Profile optimization insights** `[LB-004]` *(Priority: P2)*
  - *Status: Pending*
- [ ] **Task 2.25: Easy Digital Downloads SEO schemas** `[EC-004]` *(Priority: P2)*
  - *Status: Pending*
- [ ] **Task 2.26: Headless CMS mutation Webhooks hooks** `[HEAD-002]` *(Priority: P2)*
  - *Status: Pending*
- [ ] **Task 2.27: Headless GraphQL Schema fields** `[HEAD-003]` *(Priority: P2)*
  - *Status: Pending*
- [ ] **Task 2.28: Static Site Generator build hooks exporter** `[HEAD-004]` *(Priority: P2)*
  - *Status: Pending*

### Hardened Technical SEO Suite

  - Completed exact/regex database routing safely.
  - Completed buffer cap (100 logs), bot filter, and instant redirect creator.
- [ ] **Task 2.31: Broken Link & Image Scanner** `[TT-003]` *(Priority: P1)*
  - *Status: Pending (⚠️ Deferred / Strict Guardrails Required)*
  - *Note: Severe Performance Risk. On-server crawling triggers timeouts, CPU spikes, and locks database threads. Highly banned by major managed WP hosts. Deferred to external SaaS microservice execution or strictly limited to gated off-peak background queues.*
- [ ] **Task 2.32: Inbound Orphaned Page Finder & linking builder** `[TT-004]` *(Priority: P1)*
  - *Status: Pending*
- [ ] **Task 2.33: Stale Cornerstone Content Age Scanner** `[CS-001]` *(Priority: P2)*
  - *Status: Pending*
- [ ] **Task 2.34: Guided Orphaned Content Remediation Workflow** `[CS-002]` *(Priority: P2)*
  - *Status: Pending*
- [ ] **Task 2.35: Recurring SEO Audits scheduler** `[CS-003]` *(Priority: P2)*
  - *Status: Pending*
- [ ] **Task 2.36: .htaccess Safe visual editor & backups** `[TT-005]` *(Priority: P2)*
  - *Status: Pending*
- [ ] **Task 2.37: Taxonomy base permalinks remover** `[TT-006]` *(Priority: P2)*
  - *Status: Pending*
- [x] **Task 2.38: Parent post media attachment redirect rules** `[TT-007]` *(Priority: P2)*
  - *Status: Fully Executed*
  - Intercepts and redirects thin media attachments cleanly back to their parent pages.
- [ ] **Task 2.39: Live Frontend Overlay SEO Inspector** `[TT-008]` *(Priority: P2)*
  - *Status: Pending (⚠️ Guardrails Required)*
  - *Note: Security & Cache Risk. Scope rendering strictly to active administrators/editor sessions with the 'edit_posts' capability. Render inside a sandboxed Shadow DOM to prevent stylesheet leakage and bypass active frontend static page caching.*
- [ ] **Task 2.40: Live Schema Output health validator** `[TT-009]` *(Priority: P2)*
  - *Status: Pending*

---

## 🤖 Phase 3: Enterprise & AI-First
*Answer Engine Optimization (AEO) and scalable workspace controls.*
- [x] **Task 3.2: Automated `llms.txt` config generator** `[AI-001]` *(Priority: P1)*
  - *Status: Executed*
- [ ] **Task 3.3: Chatbot Citation Tracker (Perplexity, ChatGPT, Gemini)** `[AI-002]` *(Priority: P1)*
  - *Status: Pending*
- [ ] **Task 3.4: LLM Training Data Opt-Out Directives manager** `[AI-008]` *(Priority: P1)*
  - *Status: Pending*
- [ ] **Task 3.5: Multi-Engine Keyword Rank Tracker** `[AR-002]` *(Priority: P1)*
  - *Status: Pending*
- [ ] **Task 3.6: Multi-Point Technical Audit checker** `[AR-003]` *(Priority: P1)*
  - *Status: Pending*
- [ ] **Task 3.7: Granular Custom User Role SEO Manager permissions** `[UM-001]` *(Priority: P1)*
  - *Status: Pending*
- [ ] **Task 3.8: Native page builder editors SEO wrappers (Elementor, WPBakery)** `[IN-001]` *(Priority: P1)*
  - *Status: Pending*
- [ ] **Task 3.9: Multisite network configuration manager** `[IN-003]` *(Priority: P1)*
  - *Status: Pending*
  - Proactive scans detect legacy data and migrate with one-click REST execution.
- [x] **Task 3.11: Bulk Meta Title/Description Editor table** `[PM-005]` *(Priority: P1)*
  - *Status: Executed*
- [x] **Task 3.12: List views inline SEO Quick Editors** `[PM-006]` *(Priority: P1)*
  - *Status: Executed*
  - Color-coded SEO columns display real-time calculated health ratings, but inline form editing is pending.
- [ ] **Task 3.13: Chatbot Prompt Performance Audit checklists** `[AI-003]` *(Priority: P2)*
  - *Status: Pending*
- [ ] **Task 3.14: Centralized Brand Knowledge Base profile** `[AI-004]` *(Priority: P2)*
  - *Status: Pending*
- [ ] **Task 3.15: AI FAQs, Summaries, and Outlines generator** `[AI-005]` *(Priority: P2)*
  - *Status: Pending*
- [ ] **Task 3.16: Competitive Citation Mention Gap engine** `[AI-007]` *(Priority: P2)*
  - *Status: Pending*
- [ ] **Task 3.17: Semantic Cornerstone Keyword cluster planners** `[AI-009]` *(Priority: P2)*
  - *Status: Pending*
- [ ] **Task 3.18: Automated PDF Performance client reports scheduler** `[AR-004]` *(Priority: P2)*
  - *Status: Pending*
- [ ] **Task 3.19: Keyword Rank Cannibalization detector** `[AR-005]` *(Priority: P2)*
  - *Status: Pending*
- [ ] **Task 3.20: Position Rank Drop trigger notifications** `[AR-006]` *(Priority: P2)*
  - *Status: Pending*
- [ ] **Task 3.21: PageSpeed Insights web vitals stats dashboard** `[AR-007]` *(Priority: P2)*
  - *Status: Pending*
- [ ] **Task 3.22: Agency Isolated Multi-Brand Workspace switcher** `[UM-002]` *(Priority: P2)*
  - *Status: Pending*
- [ ] **Task 3.23: Team SEO Comments, Tasks, & Approvals dashboard** `[UM-003]` *(Priority: P2)*
  - *Status: Pending*
- [ ] **Task 3.24: System-wide Actions audit history logs** `[UM-004]` *(Priority: P2)*
  - *Status: Pending*
- [ ] **Task 3.25: Google Analytics 4 traffic integrations** `[IN-006]` *(Priority: P2)*
  - *Status: Pending*
- [ ] **Task 3.26: Multi-provider AI Image prompt generator** `[AI-006]` *(Priority: P3)*
  - *Status: Pending*
- [ ] **Task 3.27: Enterprise Private BYO LLM custom models gateway** `[AI-010]` *(Priority: P3)*
  - *Status: Pending*
- [ ] **Task 3.28: Agency white-label Client Portal wrapper** `[UM-005]` *(Priority: P3)*
  - *Status: Pending*
- [ ] **Task 3.29: Content Editorial Workflow checks gatekeeper** `[UM-006]` *(Priority: P3)*
  - *Status: Pending*
- [ ] **Task 3.30: Automation triggers third-party hooks (Zapier, Make)** `[IN-005]` *(Priority: P3)*
  - *Status: Pending*
- [ ] **Task 3.31: Google Docs SEO synchronization browser extension** `[IN-007]` *(Priority: P3)*
  - *Status: Pending*
- [ ] **Task 3.32: Laravel Package integration framework setup** `[IN-008]` *(Priority: P3)*
  - *Status: Pending*
- [ ] **Task 3.33: Platform-agnostic shared core library extraction** `[PM-007]` *(Priority: P3)*
  - *Status: Pending*

---

## 🧠 Phase 4: Autonomous SEO & Search Intelligence
*Advanced self-remediation agents and semantic indexing.*

- [ ] **Task 4.1: Multi-Agent Autonomous Internal Linking workflows** `[AUTO-001]` *(Priority: P2)*
  - *Status: Pending (⚠️ Guardrails Required)*
  - *Note: Architectural Bottleneck Risk. Do not run heavy recursive LLM reasoning steps natively in single-threaded PHP. Offload agent linking cycles to external SaaS worker microservices.*
- [/] **Task 4.2: Machine-Learning predictive keyword trend forecasting** `[AUTO-002]` *(Priority: P2)*
  - *Status: Partially Executed (⚠️ Guardrails Required)*
  - *Note: Processing Timeout Risk. Google Trends API fetch, token negotiation, and regional data parsing modules are fully implemented. Offload predictive trending models to external cloud instances; client displays pre-calculated forecasts only.*
  - Google Trends API fetch, token negotiation, and regional data parsing modules are fully implemented; predictive forecast layering is pending.
- [ ] **Task 4.3: Continuous AEO dynamic chatbot citation adaptation** `[AUTO-003]` *(Priority: P3)*
  - *Status: Pending*

---

## ⚠️ Architectural Guardrails & Core Technical Exclusions

Based on production-grade WordPress scaling patterns, the following tasks require strict guardrails or deferred off-server handling to protect client server resources:

### 1. Broken Link & Image Scanner `[TT-003]` (Deferred / SaaS Gated)
* **Risk Context**: Performing standard, recursive crawling loops natively on your WordPress origin server causes immediate CPU locks, runs into PHP script execution timeouts, and bloats `wp_options`. Furthermore, major hosting companies (WPEngine, Kinsta) ban internal link crawling plugins.
* **Mitigation**: 
  1. *Primary Strategy*: Offload crawling completely to a decoupled SaaS API service. The plugin registers a webhook to receive reports, rather than running recursive curl checks natively.
  2. *Fallback Strategy*: If local execution is forced, scans must be throttled to a maximum of 5 URL check pings per minute via low-priority `wp_schedule_single_event` triggers.

### 2. Autonomous Agent Linking Loops `[AUTO-001]` & Predictions `[AUTO-002]` (Decoupled execution)
* **Risk Context**: Running machine-learning pipelines or recursive multi-prompt GPT cycles in PHP freezes browser tabs, locks standard DB connections, and generates immediate browser 504 Gateway Timeouts.
* **Mitigation**: Decouple reasoning from PHP. The plugin strictly publishes data endpoints (like the structured `/schemamap` feed) and consumes pre-compiled agent decisions computed in safe cloud worker queues.

### 3. Editor Real-Time Assistant `[ACO-001]` (Debouncing / On-Demand UI)
* **Risk Context**: Dispatching API calls to LLM endpoints on every keystroke causes extreme billing costs for the user and floods the server connection gateway.
* **Mitigation**:
  1. Debounce Gutenberg client state changes by at least 3,000ms after a user halts typing before calling the assistant API.
  2. Maintain a prominent manual "Click to Analyze" button inside the React editor sidebar to ensure requests are primarily user-initiated.

### 4. Live Frontend Overlay SEO Inspector `[TT-008]` (Security Scope)
* **Risk Context**: Injecting script markers or diagnostic debuggers onto public-facing page loads exposes sensitive path details and can easily break third-party CDN / plugin static cache engines.
* **Mitigation**:
  1. Encapsulate the entire live overlay DOM cleanly within a sandboxed shadow root (`ShadowDOM`).
  2. Wrap initialization checks strictly within `current_user_can('edit_posts')` capability boundaries, preventing any visitor script load.

---
