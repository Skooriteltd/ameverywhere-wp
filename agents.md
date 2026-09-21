# AGENTS.md — Production WordPress Plugin Engineering Constitution

## 0. Mission

You are building a production-grade WordPress plugin intended for real-world distribution, including possible submission to the official WordPress.org Plugin Directory.

The plugin must be:

1. Secure by design.
2. Fast by default.
3. Stable across supported WordPress/PHP environments.
4. Intuitive enough that users understand what to do without documentation.
5. Native to WordPress rather than fighting WordPress conventions.
6. Accessible.
7. Fully internationalizable.
8. Privacy-conscious.
9. Maintainable by another senior developer.
10. Compliant with WordPress.org Plugin Directory requirements and best practices.
11. Testable and observable.
12. Conservative about external dependencies and third-party services.

Do not optimize for "getting the feature working" at the expense of security,
performance, maintainability, compatibility, accessibility, or user experience.

A feature is not complete merely because it works.

A feature is complete when it works correctly, securely, efficiently, accessibly,
and predictably in the supported WordPress ecosystem.

---

# 1. Non-Negotiable Engineering Principles

## 1.1 Security > functionality > performance > convenience

Never weaken security to make implementation easier.

Never weaken security or correctness merely to ship faster.

When trade-offs exist:

- Security wins over convenience.
- Data integrity wins over speed.
- Correctness wins over cleverness.
- Performance wins over unnecessary abstraction.
- Native WordPress APIs win over custom implementations when they provide equivalent functionality.
- Simplicity wins over unnecessary architectural complexity.

## 1.2 WordPress-native first

Prefer WordPress APIs, hooks, capabilities, metadata APIs, settings APIs,
REST APIs, HTTP APIs, cron APIs, filesystem APIs, caching APIs, enqueue APIs,
privacy APIs, internationalization APIs, and database abstractions.

Do not reinvent functionality that WordPress already provides adequately.

Before introducing a custom mechanism, verify that WordPress does not already
provide an appropriate API.

## 1.3 Never assume the environment

The plugin may run alongside:

- different themes
- different plugins
- object caches
- page caches
- persistent database connections
- multisite
- reverse proxies
- CDNs
- managed WordPress hosts
- shared hosting
- aggressive security plugins
- PHP configuration differences
- localization
- accessibility tools
- browser differences
- incomplete or unusual WordPress installations

Code must therefore be defensive and portable.

Never assume:

- Administrator is the current user.
- `wp-admin` means the current user is authorized.
- pretty permalinks are enabled.
- a specific theme exists.
- jQuery exists unless declared as a dependency.
- REST API authentication exists.
- a particular PHP extension exists.
- object caching exists.
- filesystem access is available.
- external HTTP requests will succeed.
- a database query returns data.
- an option exists.
- a post/user/term exists.
- a plugin is activated merely because its files exist.

---

# 2. WordPress.org Plugin Directory Compliance

The plugin must be designed to satisfy the WordPress.org Plugin Directory
guidelines from the beginning, not retrofitted before submission.

Official requirements and review guidance:

- GPL-compatible licensing.
- No illegal or dishonest functionality.
- No hidden functionality.
- No arbitrary code execution.
- No downloading/executing remote code.
- No obfuscated PHP/JavaScript intended to hide functionality.
- No unauthorized tracking.
- No spam.
- No deceptive admin notices.
- No forced external registration for functionality that does not genuinely require it.
- No unexplained external services.
- No misleading claims.
- No trademark infringement or confusing branding.
- No unnecessary duplication of WordPress core functionality.
- No undisclosed collection or transmission of user/site data.
- All bundled third-party code must have compatible licensing.
- All plugin functionality must be reviewable and understandable.

Do not attempt to circumvent Plugin Review checks.

When uncertain about a directory requirement, consult current official
WordPress.org documentation rather than relying on memory.

The WordPress Plugin Directory guidelines are authoritative:

https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/

---

# 3. Architecture

## 3.1 Recommended baseline

For anything beyond a very small plugin, use a modular architecture.

Preferred structure:

    plugin-slug/
    ├── plugin-slug.php
    ├── uninstall.php
    ├── readme.txt
    ├── LICENSE
    ├── composer.json
    ├── package.json
    ├── phpunit.xml.dist
    ├── phpcs.xml.dist
    ├── languages/
    ├── includes/
    │   ├── Admin/
    │   ├── API/
    │   ├── CLI/
    │   ├── Database/
    │   ├── Frontend/
    │   ├── Integration/
    │   ├── Privacy/
    │   ├── Services/
    │   └── Support/
    ├── admin/
    │   ├── css/
    │   ├── js/
    │   └── views/
    ├── public/
    │   ├── css/
    │   ├── js/
    │   └── views/
    ├── templates/
    ├── assets/
    ├── tests/
    │   ├── Unit/
    │   ├── Integration/
    │   └── Fixtures/
    └── vendor/

The exact structure may vary by plugin complexity.

Do not create architecture for architecture's sake.

## 3.2 Bootstrap must remain lightweight

The main plugin file should:

- contain the plugin header;
- define constants only where useful;
- verify minimum requirements;
- load the autoloader;
- initialize the plugin;
- avoid expensive work.

Do not perform database queries, remote HTTP calls, filesystem scans,
large option loads, or expensive initialization directly from the main
plugin bootstrap.

## 3.3 Namespacing

Use a unique PHP namespace for plugin classes.

Example:

    Vendor\PluginName\

Avoid global functions and generic class names.

If global functions are genuinely necessary, use a unique prefix.

Never use generic names such as:

    Utils
    Helper
    Manager
    Settings
    Admin
    API

without a plugin-specific namespace/prefix.

Avoid collisions with:

- WordPress core
- themes
- other plugins
- Composer packages
- common libraries

WordPress itself explicitly recommends avoiding naming collisions.

---

# 4. Dependency Management

## 4.1 Composer

Use Composer where it materially improves maintainability.

Commit the required production dependencies necessary for the distributed
plugin when appropriate.

Never assume Composer is installed on the customer's server.

The released plugin must function without requiring the site owner to run
Composer.

## 4.2 Third-party libraries

Before adding a dependency, evaluate:

1. License compatibility.
2. Maintenance status.
3. Security history.
4. Bundle size.
5. Runtime overhead.
6. PHP compatibility.
7. WordPress compatibility.
8. Namespace collision risk.
9. Whether WordPress already provides equivalent functionality.

Do not bundle a large library to solve a trivial problem.

Do not include libraries whose licensing cannot be verified.

Do not blindly trust Composer packages.

Audit dependencies before release.

---

# 5. Security Constitution

Security is mandatory at every boundary.

Assume all external data is hostile until validated.

This includes:

- `$_GET`
- `$_POST`
- `$_REQUEST`
- `$_FILES`
- cookies
- REST parameters
- AJAX parameters
- URL parameters
- shortcode attributes
- block attributes
- database values
- metadata
- user input
- third-party API responses
- imported files
- webhook payloads
- HTTP headers
- translated strings
- JavaScript input
- CLI arguments

Official security principle:

Validate and sanitize input and escape output.

https://developer.wordpress.org/apis/security/

---

# 6. Input Handling

## 6.1 Never process entire superglobals

Do not do:

    $_POST
    $_GET
    $_REQUEST

as bulk data.

Read only the specific fields required.

Example:

    $value = isset( $_POST['plugin_value'] )
        ? sanitize_text_field( wp_unslash( $_POST['plugin_value'] ) )
        : '';

## 6.2 Validate before sanitizing where appropriate

Sanitization changes input.

Validation determines whether input is acceptable.

Prefer:

    receive
    → unslash
    → validate
    → sanitize/normalize
    → authorize
    → process

For values with strict domains, reject invalid values rather than silently
converting them.

Examples:

- IDs → validate as positive integers.
- enums → whitelist accepted values.
- booleans → explicit conversion.
- URLs → validate expected protocol/domain where appropriate.
- emails → validate as emails.
- dates → validate expected format.
- UUIDs → validate format.
- MIME types → whitelist.
- file extensions → whitelist.
- numeric ranges → validate boundaries.

---

# 7. Output Escaping

Escape as late as possible.

Every dynamic value rendered into output must use the appropriate escaping
function for its context.

Examples:

HTML:

    esc_html()

HTML attributes:

    esc_attr()

URLs:

    esc_url()

Stored URLs:

    esc_url_raw()

JavaScript contexts:

    wp_json_encode()
    esc_js()

Textareas:

    esc_textarea()

Allowed HTML:

    wp_kses()
    wp_kses_post()

Never concatenate untrusted data into HTML, JavaScript, SQL, CSS, shell
commands, or URLs without appropriate protection.

Do not escape data prematurely and then reuse the escaped value as raw data.

Official guidance:

https://developer.wordpress.org/apis/security/escaping/

---

# 8. Nonces and Authorization

Nonces protect against CSRF.

Nonces are NOT authorization.

Every state-changing operation must separately enforce authorization.

Required pattern:

    capability check
    +
    nonce verification
    +
    input validation
    +
    safe processing

Never use:

    nonce === permission

Use the appropriate capability:

    current_user_can()

Examples:

- settings → appropriate settings capability
- deleting plugin data → explicit capability
- modifying plugin-owned resources → capability appropriate to resource
- administrative operations → appropriate admin capability

Never assume Administrator is the only legitimate authorized role.

WordPress explicitly states that nonces must not be used as authentication or
access control.

https://developer.wordpress.org/apis/security/nonces/

---

# 9. AJAX and REST Security

Every AJAX endpoint must:

1. Validate request method where appropriate.
2. Verify nonce.
3. Verify capability/authorization.
4. Validate all input.
5. Return appropriate errors.
6. Avoid exposing sensitive information.
7. Use WordPress APIs for response formatting.

Every REST endpoint must:

1. Define an explicit `permission_callback`.
2. Validate and sanitize registered arguments.
3. Enforce authorization.
4. Return `WP_Error` with appropriate status codes on failure.
5. Avoid leaking internal implementation details.
6. Return only necessary fields.
7. Avoid exposing personal data by default.

Never use an empty authorization callback merely to make an endpoint work.

Never expose administrative data through a public REST endpoint by accident.

---

# 10. SQL and Database Security

Use `$wpdb->prepare()` for dynamic SQL.

Never concatenate untrusted values into SQL.

Prefer WordPress APIs over direct SQL where practical.

If direct SQL is necessary:

- use `$wpdb`;
- prepare all dynamic values;
- explicitly select required columns;
- avoid `SELECT *`;
- use indexed columns;
- understand query cardinality;
- check return values;
- handle failures;
- avoid N+1 queries.

For custom tables:

- define schema explicitly;
- use `dbDelta()` appropriately for schema changes;
- use `$wpdb->prefix`;
- never hard-code `wp_`;
- document indexes;
- design for expected query patterns;
- use appropriate data types;
- consider multisite implications.

Never store sensitive data in plaintext when protection is required.

---

# 11. Performance Constitution

Performance is a product feature.

Do not use "WordPress is slow" as an excuse.

## 11.1 Default runtime behavior

The plugin should do as little work as possible on requests that do not
need its functionality.

Avoid:

- global queries on every request;
- loading large datasets unnecessarily;
- loading admin assets on the frontend;
- loading frontend assets in wp-admin;
- remote HTTP requests on every page;
- repeated option reads;
- repeated database queries;
- unnecessary hooks;
- expensive initialization;
- filesystem scans;
- large autoloaded options;
- synchronous analytics calls;
- unnecessary cron scheduling.

## 11.2 Conditional loading

Load functionality only where required.

Examples:

- admin-only code → admin context
- specific admin page → specific screen
- frontend assets → pages requiring the feature
- block assets → only where block is rendered
- shortcode assets → only when shortcode is present
- REST logic → REST requests
- CLI logic → CLI
- WooCommerce integration → only when WooCommerce is active

Never enqueue a large JavaScript or CSS bundle globally when only one
screen requires it.

WordPress recommends conditional loading and page-specific asset enqueueing.

---

# 12. Database Performance

Before adding a query, ask:

1. Does this query need to run?
2. Does it need to run on every request?
3. Can WordPress provide the data more efficiently?
4. Can the result be cached?
5. Is the query indexed?
6. Is the result set bounded?
7. Can it be batched?
8. Could it become an N+1 query?
9. What happens with 10 records?
10. What happens with 100,000 records?

Never optimize only against toy datasets.

Use pagination for potentially large datasets.

Do not load an entire table merely to display the first 20 rows.

---

# 13. Options and Autoloading

Be deliberate about options.

Never place large or rarely used data into globally autoloaded options.

Separate:

- frequently needed small configuration;
- administrative configuration;
- large datasets;
- caches;
- transient data.

Do not use WordPress options as a general-purpose database.

For large or relational data, consider:

- post metadata;
- user metadata;
- term metadata;
- custom tables;
- object cache;
- transients;
- custom data structures.

Choose based on access patterns rather than ideology.

---

# 14. Caching

Cache only when there is a measurable benefit.

Every cache must have:

- clear key namespace;
- invalidation strategy;
- expiration strategy where appropriate;
- bounded size;
- graceful cache misses.

Never assume persistent object caching exists.

The plugin must function correctly without persistent caching.

Never allow stale cache data to compromise security or authorization.

Do not cache user-specific data globally.

Avoid cache keys containing uncontrolled user input.

---

# 15. External HTTP Requests

External HTTP requests are expensive and unreliable.

Never make blocking remote requests on normal page loads unless essential.

Use the WordPress HTTP API.

Always define:

- timeout;
- expected response;
- error handling;
- retry behavior where appropriate;
- authentication behavior;
- data minimization;
- privacy implications.

Never download and execute remote PHP/JavaScript/code as a mechanism for
updating plugin functionality.

Remote services must be explicitly documented.

Do not silently transmit site or user data.

---

# 16. Cron and Background Work

Do not perform expensive operations synchronously when they can safely be
processed asynchronously.

Use WordPress cron or another appropriate background mechanism.

Cron jobs must:

- have unique hooks;
- avoid duplicate scheduling;
- be idempotent;
- process bounded batches;
- handle partial failure;
- avoid unbounded loops;
- record useful state;
- clean up after themselves.

Never assume WP-Cron runs exactly at the scheduled second.

For critical timing requirements, document the limitation.

---

# 17. JavaScript

Use WordPress's script enqueueing system.

Never hardcode plugin JS `<script>` tags into page HTML.

Declare dependencies explicitly.

Load scripts only where required.

Use appropriate loading strategies such as `defer` where compatible.

Prefer modern, maintainable JavaScript.

Do not ship an enormous frontend framework for a trivial interaction.

Avoid:

- unnecessary runtime dependencies;
- global variables;
- inline JavaScript where enqueueing is appropriate;
- unnecessary DOM polling;
- excessive event listeners;
- repeated REST requests;
- client-side rendering of data that could be efficiently rendered server-side.

Use WordPress data APIs appropriately.

For REST requests, handle:

- loading;
- success;
- empty state;
- validation errors;
- permission errors;
- network failure;
- retry where appropriate.

WordPress supports deferred/async script loading through its enqueue APIs.

https://developer.wordpress.org/plugins/javascript/enqueuing/

---

# 18. CSS and UI Performance

CSS must be scoped to the plugin.

Do not globally style generic elements such as:

    button
    input
    table
    h1
    .container

unless intentionally scoped.

Prefer:

    .plugin-slug ...

Avoid CSS that breaks the active theme.

Do not ship unnecessary CSS.

Avoid large icon/font libraries when a small inline SVG or targeted asset is
sufficient.

---

# 19. User Experience Constitution

The user must understand the plugin immediately after activation.

The first-run experience should answer:

1. What does this plugin do?
2. What does it need from me?
3. What should I do next?
4. What happens after I do it?
5. How do I undo/change it?

Do not make users hunt through settings.

## 19.1 Activation

Activation should be safe and lightweight.

Do not:

- redirect users unexpectedly;
- open multiple notices;
- launch intrusive onboarding;
- require unnecessary registration;
- immediately request unrelated permissions;
- make network requests without a legitimate reason;
- create unnecessary database tables/options;
- alter existing content without explicit consent.

If setup is required, provide a clear next step.

## 19.2 First-run UX

Prefer:

    Activate
       ↓
    Understand
       ↓
    Configure only what matters
       ↓
    Experience value
       ↓
    Optional advanced configuration

Do not present 30 settings before the user sees value.

Use progressive disclosure.

Advanced settings should remain available without overwhelming new users.

## 19.3 Admin notices

Admin notices must be:

- relevant;
- actionable;
- dismissible where appropriate;
- correctly scoped;
- non-repetitive;
- accessible.

Never display the same notice on every admin page.

Never use notices primarily for marketing.

Do not create notification fatigue.

---

# 20. Deactivation Resistance

Users should keep the plugin because it is useful, reliable, fast, and
pleasant to use.

Never use dark patterns to prevent deactivation.

Never:

- hide the deactivate button;
- intercept deactivation;
- nag users after dismissal;
- create fake errors;
- force reviews;
- repeatedly demand registration;
- disable unrelated WordPress functionality;
- inject promotional content into unrelated admin screens.

The correct strategy is:

    useful product
    +
    excellent defaults
    +
    reliable behavior
    +
    low performance cost
    +
    obvious value

---

# 21. Settings UX

Do not create settings merely because a value is configurable.

Every setting must justify its existence.

Prefer sensible defaults.

Use the WordPress Settings API where appropriate.

Settings must:

- have labels;
- have descriptions where needed;
- validate input;
- sanitize stored values;
- escape displayed values;
- enforce capabilities;
- use nonces;
- preserve user input on validation failure where appropriate;
- provide clear success/error feedback.

Avoid configuration names that require developer knowledge.

Prefer plain language.

Bad:

    Enable asynchronous resource resolution mode.

Better:

    Load resources only when needed.

---

# 22. Accessibility

Accessibility is mandatory.

Target WCAG-compatible behavior and WordPress accessibility conventions.

At minimum:

- keyboard navigation;
- visible focus states;
- semantic HTML;
- correct labels;
- accessible form errors;
- sufficient contrast;
- screen-reader-friendly status messages;
- meaningful button labels;
- no color-only meaning;
- accessible dialogs;
- appropriate ARIA only when necessary;
- logical heading hierarchy.

Do not use ARIA to compensate for incorrect HTML.

Test keyboard-only workflows.

Test with screen readers when practical.

Do not assume mouse interaction.

---

# 23. Internationalization

All user-facing strings must be translatable.

Use the plugin's text domain consistently.

The text domain must match the plugin slug.

Do not construct gettext strings dynamically.

Bad:

    __( $message, 'plugin-slug' )

Good:

    __( 'Something went wrong.', 'plugin-slug' )

Use placeholders for dynamic content.

Add translator comments where context matters.

Example:

    /* translators: %s: number of imported records. */
    printf(
        esc_html__(
            '%s records imported.',
            'plugin-slug'
        ),
        esc_html( $count )
    );

Never assume English word order.

Never concatenate translated fragments when a complete translatable sentence
would be clearer.

Remember that translated strings are not inherently trusted output.

Escape translated output according to its context.

Official guidance:

https://developer.wordpress.org/plugins/internationalization/

---

# 24. Privacy

Privacy must be considered before implementing data collection.

For every piece of data, document:

- what is collected;
- why it is collected;
- where it is stored;
- how long it is retained;
- who can access it;
- whether it leaves the site;
- whether a third-party service receives it;
- how it is deleted;
- whether it is included in exports.

Collect the minimum necessary data.

Do not collect telemetry merely because it might be useful later.

If personal data is stored, implement appropriate WordPress privacy
integration where applicable:

- privacy policy guidance;
- personal data exporter;
- personal data eraser;
- deletion behavior;
- anonymization where appropriate.

Do not claim that a plugin makes a site "100% compliant" with a law or
regulation.

The plugin can assist site administrators with compliance; the site owner
remains responsible for the site's legal compliance.

Official privacy guidance:

https://developer.wordpress.org/plugins/privacy/

---

# 25. Data Lifecycle

Every persistent data structure must have a lifecycle.

Define:

    create
    read
    update
    delete
    uninstall

Ask:

- What happens when the user deletes the associated post?
- What happens when a user account is deleted?
- What happens when the plugin is deactivated?
- What happens when the plugin is uninstalled?
- What happens during migration?
- What happens when a feature is removed in a future version?

Never delete user content on deactivation unless explicitly required and
documented.

Uninstall behavior must be intentional.

---

# 26. Database Migrations

Database changes must be versioned.

Never assume the database is always at the current schema.

Use an explicit schema/database version.

Migrations must:

- be repeatable or safely guarded;
- handle partial execution;
- avoid destructive operations without migration strategy;
- preserve existing data;
- be tested on representative datasets.

Never silently destroy existing data during an upgrade.

---

# 27. Backward Compatibility

Do not unnecessarily break existing sites.

When changing:

- hooks;
- filters;
- functions;
- database schemas;
- option formats;
- REST responses;
- block attributes;
- shortcode behavior;

consider compatibility and migration.

If a public API must change, document the change.

Prefer deprecation paths where practical.

Do not remove an existing filter/action merely because it is inconvenient.

---

# 28. Hooks and Extensibility

Use actions and filters where extensibility provides real value.

Good extension points should be:

- predictable;
- documented;
- named consistently;
- passed useful context;
- stable across releases.

Do not create hooks for every internal line of code.

Do not expose unstable implementation details as if they were public APIs.

Document public hooks.

---

# 29. Error Handling

Never silently swallow meaningful failures.

Errors should be:

- detected;
- handled;
- logged when appropriate;
- communicated to the user when actionable.

Do not expose:

- stack traces;
- database credentials;
- filesystem paths;
- API keys;
- internal server details;
- SQL statements;

to normal users.

Use `WP_Error` for WordPress-style recoverable errors.

Error messages must tell users:

1. What happened.
2. Whether action is required.
3. What they can do next.

Avoid technical error messages for ordinary users.

---

# 30. Logging

Logging must be deliberate.

Do not log:

- passwords;
- API secrets;
- authentication tokens;
- full request payloads containing personal data;
- payment credentials;
- unnecessary personal information.

Use appropriate log levels where a logging mechanism exists.

Development diagnostics must not accidentally become production noise.

Never leave debug output enabled in production releases.

---

# 31. File Uploads

Treat uploads as hostile.

Validate:

- capability;
- nonce;
- file type;
- MIME type;
- extension;
- size;
- expected content.

Do not trust the filename extension.

Use WordPress's upload APIs where possible.

Never execute uploaded files.

Never allow uploaded PHP files to become executable.

Avoid storing sensitive files in publicly accessible locations without an
appropriate access model.

---

# 32. Shortcodes and Blocks

Shortcode attributes are untrusted input.

Validate and sanitize them.

Escape generated output.

For blocks:

- validate attributes;
- avoid unnecessary frontend JavaScript;
- support dynamic rendering where appropriate;
- preserve compatibility with editor and frontend;
- ensure editor UX is understandable;
- ensure frontend output remains accessible.

Do not introduce a block merely because it is fashionable.

---

# 33. REST API Design

REST endpoints should behave like public APIs.

Use:

- stable routes;
- namespaces;
- explicit methods;
- schemas;
- validation;
- sanitization;
- permission callbacks;
- predictable responses.

Do not return unnecessary internal fields.

Do not expose database structure as an API contract.

Avoid coupling frontend JavaScript directly to implementation-specific
database details.

---

# 34. Multisite

Determine explicitly whether the plugin supports:

- single-site;
- network activation;
- per-site activation;
- network-wide configuration.

Never accidentally store network-level settings as site-level settings or
vice versa.

Test activation/deactivation behavior on multisite.

Do not assume `get_current_blog_id()` is always `1`.

---

# 35. Third-Party Services

Any external service must have a clear reason.

Before integrating a service, document:

- service name;
- purpose;
- data transmitted;
- trigger;
- authentication;
- retention implications;
- failure behavior;
- privacy implications;
- terms/license;
- whether the feature remains functional without it.

External service calls should be opt-in when they involve non-essential
telemetry or data sharing.

Do not hide third-party integrations.

Do not send data merely to determine whether a user is using the plugin.

---

# 36. Marketing and Telemetry

Do not confuse product analytics with user surveillance.

No hidden tracking.

No silent telemetry.

No fingerprinting.

No collection of unnecessary site information.

If analytics are included:

- explain them;
- minimize collected data;
- provide appropriate controls;
- avoid collecting personal data unnecessarily;
- respect privacy expectations.

Do not add marketing banners throughout wp-admin.

---

# 37. Performance Budgets

Every feature should have a performance budget.

Consider:

### Frontend

- number of HTTP requests;
- JavaScript size;
- CSS size;
- database queries;
- remote requests;
- DOM complexity;
- runtime execution.

### Admin

- initial page load;
- database queries;
- REST requests;
- JavaScript bundle size;
- table rendering.

### Background

- batch size;
- memory consumption;
- execution time;
- retry behavior.

Performance must be tested with realistic data volumes.

Do not benchmark only on an empty WordPress installation.

---

# 38. Testing

Every production plugin must have automated tests appropriate to its
complexity.

Minimum expectations:

- PHP syntax validation;
- WordPress coding standards;
- Plugin Check;
- unit tests for core business logic;
- integration tests for WordPress behavior;
- security tests for privileged endpoints;
- REST/AJAX tests where applicable;
- migration tests where applicable.

Test both successful and failure paths.

Test permissions.

Test invalid input.

Test empty states.

Test large datasets where relevant.

Test uninstall behavior.

Test activation/deactivation.

Test upgrades from at least one prior version when schema/data migrations
exist.

---

# 39. Required Quality Gates

Before considering a release complete, run:

    composer validate
    composer install --no-dev
    vendor/bin/phpcs
    vendor/bin/phpunit

and the WordPress Plugin Check tool where available.

Also perform:

    PHP syntax checks
    JavaScript lint
    Type checking if applicable
    Build verification
    Security/static analysis where configured
    Production asset build
    Readme validation
    Clean installation test
    Upgrade test
    Deactivation test
    Uninstall test

Do not declare a release production-ready if a required quality gate fails.

Warnings must be investigated rather than blindly suppressed.

WordPress Plugin Check specifically checks many issues relevant to
internationalization, accessibility, performance, security, and Plugin
Directory requirements.

https://developer.wordpress.org/plugins/developer-tools/helper-plugins/

---

# 40. Manual QA Matrix

Before release, test at minimum:

## WordPress

- current supported WordPress version;
- minimum supported WordPress version.

## PHP

- minimum supported PHP;
- current supported PHP.

## Installation

- clean installation;
- activation;
- configuration;
- first-use workflow.

## Lifecycle

- activation;
- deactivation;
- reactivation;
- upgrade;
- uninstall.

## Permissions

- Administrator;
- Editor where applicable;
- custom role where applicable;
- unauthenticated user for public functionality.

## Environment

- standard theme;
- common modern theme;
- plugin conflict scenario;
- no object cache;
- persistent object cache if relevant;
- multisite if supported.

## UX

- desktop;
- mobile;
- keyboard-only;
- accessibility checks;
- empty states;
- error states;
- slow network.

---

# 41. Readme and Documentation

`readme.txt` is part of the product.

It must accurately describe:

- what the plugin does;
- installation;
- configuration;
- usage;
- requirements;
- supported versions;
- external services;
- privacy implications;
- screenshots;
- FAQs;
- changelog;
- support information.

Never exaggerate functionality.

Never claim unsupported compatibility.

Never claim legal compliance that cannot be guaranteed.

Use the official WordPress.org readme format and validator.

https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/

---

# 42. Plugin Assets

Directory assets must follow WordPress.org conventions.

Use:

    assets/
        icon-128x128.*
        icon-256x256.*
        banner-772x250.*
        banner-1544x500.*
        screenshot-1.*
        screenshot-2.*

Use lowercase filenames.

Screenshots should represent the actual plugin UI.

Do not use misleading screenshots.

Keep assets appropriately optimized.

Do not include copyrighted imagery without appropriate rights.

Official asset guidance:

https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/

---

# 43. Code Style

Follow WordPress Coding Standards unless there is a documented technical
reason not to.

Prefer readable code over compressed cleverness.

Avoid:

- unnecessary one-liners;
- deeply nested conditionals;
- giant classes;
- god objects;
- hidden side effects;
- static global state;
- excessive singleton usage;
- premature abstraction.

Classes should have one coherent responsibility.

Methods should do one understandable thing.

Names should explain intent.

Comments should explain WHY, not restate WHAT the code already says.

---

# 44. Dependency Injection and Services

For complex plugins, prefer explicit dependencies.

Avoid global service locators everywhere.

Avoid static access as the default architecture.

A service should have a clear responsibility.

Separate:

- WordPress integration;
- domain/business logic;
- persistence;
- presentation;
- external integrations.

This makes business logic easier to test without booting the entire WordPress
environment.

---

# 45. Business Logic

Do not bury business rules inside:

- templates;
- AJAX callbacks;
- REST controllers;
- WordPress hooks;
- admin page rendering.

Controllers/hooks should orchestrate.

Domain services should perform business logic.

Repositories/data services should handle persistence where complexity warrants
them.

This separation should remain proportional to the plugin's complexity.

---

# 46. Templates

Templates should primarily render data.

Do not perform:

- database migrations;
- remote HTTP requests;
- destructive operations;
- complex business logic;

inside templates.

Escape output in the template according to context.

---

# 47. Feature Development Workflow

For every feature:

## Step 1 — Understand

Identify:

- user problem;
- WordPress integration points;
- data model;
- permissions;
- privacy implications;
- performance implications;
- accessibility requirements;
- compatibility requirements.

## Step 2 — Design

Define:

- user flow;
- data flow;
- failure states;
- security boundaries;
- APIs;
- persistence;
- caching;
- migration strategy.

## Step 3 — Implement

Build the smallest robust implementation.

Do not over-engineer.

Do not bypass WordPress APIs without justification.

## Step 4 — Secure

Review:

- input;
- authorization;
- nonces;
- SQL;
- output;
- REST;
- AJAX;
- uploads;
- external requests;
- secrets;
- personal data.

## Step 5 — Optimize

Measure before optimizing.

Remove:

- unnecessary queries;
- unnecessary requests;
- unnecessary assets;
- unnecessary processing.

## Step 6 — Test

Test:

- happy path;
- invalid input;
- permissions;
- failure;
- empty states;
- large data;
- upgrade;
- uninstall.

## Step 7 — UX review

Ask:

> If I installed this plugin for the first time, would I immediately know what
> to do?

Then ask:

> Is there anything here that would make me want to deactivate it?

Fix the underlying problem rather than adding more onboarding.

---

# 48. Agent Behavior

When modifying an existing codebase:

1. Read the existing architecture before changing it.
2. Search for existing abstractions before creating new ones.
3. Reuse established naming conventions.
4. Do not duplicate existing functionality.
5. Preserve public APIs unless intentionally changing them.
6. Inspect related hooks before changing behavior.
7. Check migrations before modifying persisted data.
8. Check tests before changing implementation.
9. Add or update tests with behavioral changes.
10. Keep changes narrowly scoped.

Never rewrite large portions of the plugin merely because a different
architecture would be aesthetically preferable.

---

# 49. Agent Must Stop and Investigate

Do not guess when encountering:

- unknown database schema;
- undocumented external API;
- unclear capability requirements;
- uncertain privacy behavior;
- ambiguous WordPress compatibility;
- unknown migration state;
- security-sensitive behavior;
- licensing uncertainty;
- Plugin Directory requirements;
- destructive data operations.

Investigate the repository, WordPress documentation, tests, or authoritative
source first.

Never invent WordPress APIs.

Never invent hooks.

Never invent capability names.

Never invent Plugin Directory rules.

---

# 50. Anti-Patterns — Automatically Reject

The following require explicit justification or replacement:

- direct SQL concatenation;
- missing REST `permission_callback`;
- nonce used as authorization;
- unsanitized request input;
- unescaped output;
- global unprefixed functions;
- global CSS selectors;
- globally loaded assets without justification;
- remote HTTP request on every frontend request;
- hidden telemetry;
- automatic external registration;
- forced review requests;
- admin spam;
- arbitrary code execution;
- remote code execution;
- obfuscated code;
- silently changing existing content;
- deleting user data on deactivation;
- destructive migrations without backup/migration strategy;
- loading entire datasets unnecessarily;
- `SELECT *` in performance-sensitive queries;
- N+1 queries;
- unbounded loops;
- unbounded cron processing;
- hardcoded `wp_` database prefix;
- assuming Administrator access;
- assuming a particular theme;
- assuming jQuery;
- assuming pretty permalinks;
- assuming persistent object cache;
- assuming external APIs are available;
- swallowing errors without justification;
- storing secrets in source control.

---

# 51. Release Checklist

A release is NOT ready until all applicable items are satisfied.

## Functionality

- [ ] Feature works on a clean installation.
- [ ] Existing functionality remains intact.
- [ ] Empty states work.
- [ ] Error states work.
- [ ] Upgrade path works.

## Security

- [ ] Input validated.
- [ ] Input sanitized where appropriate.
- [ ] Output escaped.
- [ ] Capability checks implemented.
- [ ] Nonces implemented for applicable state-changing requests.
- [ ] SQL prepared.
- [ ] REST permission callbacks implemented.
- [ ] AJAX permissions implemented.
- [ ] Uploads secured.
- [ ] Secrets excluded from repository.
- [ ] Sensitive information not leaked through errors/logs.

## Performance

- [ ] Assets conditionally loaded.
- [ ] No unnecessary frontend requests.
- [ ] No unnecessary database queries.
- [ ] Large datasets paginated/batched.
- [ ] External requests minimized.
- [ ] Expensive work moved to background processing where appropriate.
- [ ] Cache strategy reviewed.
- [ ] Autoloaded options reviewed.
- [ ] Performance tested with realistic data.

## UX

- [ ] First-use experience is clear.
- [ ] Defaults are sensible.
- [ ] Settings are understandable.
- [ ] Notices are useful and scoped.
- [ ] Error messages are actionable.
- [ ] No dark patterns.
- [ ] No unnecessary registration.
- [ ] No unnecessary marketing.
- [ ] Deactivation remains straightforward.

## Accessibility

- [ ] Keyboard navigation works.
- [ ] Forms have labels.
- [ ] Focus states work.
- [ ] Errors are accessible.
- [ ] Status messages are accessible.
- [ ] Color is not the only signal.
- [ ] Headings are logical.
- [ ] Dialogs are accessible.

## Internationalization

- [ ] User-facing strings use gettext.
- [ ] Text domain matches plugin slug.
- [ ] Dynamic values use placeholders.
- [ ] Translator comments added where needed.
- [ ] Translated output is escaped.

## Privacy

- [ ] Personal data inventory completed.
- [ ] Data minimization reviewed.
- [ ] External data transmission documented.
- [ ] Privacy policy content provided where appropriate.
- [ ] Exporter implemented where applicable.
- [ ] Eraser implemented where applicable.
- [ ] Uninstall cleanup reviewed.

## Compatibility

- [ ] Minimum supported WordPress tested.
- [ ] Current WordPress tested.
- [ ] Minimum supported PHP tested.
- [ ] Current supported PHP tested.
- [ ] Multisite tested if supported.
- [ ] Plugin/theme conflict considerations reviewed.

## Directory Compliance

- [ ] GPL-compatible licensing verified.
- [ ] Third-party licenses verified.
- [ ] No obfuscated code.
- [ ] No arbitrary code execution.
- [ ] No remote code execution.
- [ ] No hidden functionality.
- [ ] No deceptive behavior.
- [ ] No unauthorized tracking.
- [ ] No trademark/copyright concerns.
- [ ] readme.txt validated.
- [ ] Plugin Check passed.
- [ ] Assets validated.
- [ ] Plugin headers correct.

## Quality

- [ ] PHPCS passes.
- [ ] PHPUnit passes.
- [ ] Static analysis passes where configured.
- [ ] JS lint passes.
- [ ] Build passes.
- [ ] Production bundle generated.
- [ ] No debug output.
- [ ] No accidental development dependencies in release.
- [ ] No TODOs representing unfinished production behavior.

---

# 52. Definition of Done

A feature is DONE only when:

    FUNCTIONAL
    + SECURE
    + FAST
    + ACCESSIBLE
    + INTERNATIONALIZED
    + PRIVACY-AWARE
    + COMPATIBLE
    + TESTED
    + DOCUMENTED
    + USER-FRIENDLY

A plugin is RELEASE-READY only when:

    Plugin Check
    + Coding Standards
    + Automated Tests
    + Manual QA
    + Security Review
    + Performance Review
    + UX Review
    + Privacy Review
    + Directory Compliance Review

all pass.

The objective is not merely to get the plugin approved.

The objective is to publish software that WordPress users install, understand,
trust, keep active, update safely, and recommend.

---

# 53. Source of Truth

When WordPress behavior or Plugin Directory policy is uncertain, consult
current official WordPress documentation.

Primary references:

- Plugin Handbook:
  https://developer.wordpress.org/plugins/

- Plugin Directory:
  https://developer.wordpress.org/plugins/wordpress-org/

- Detailed Plugin Guidelines:
  https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/

- Security:
  https://developer.wordpress.org/apis/security/

- Nonces:
  https://developer.wordpress.org/apis/security/nonces/

- Escaping:
  https://developer.wordpress.org/apis/security/escaping/

- Best Practices:
  https://developer.wordpress.org/plugins/plugin-basics/best-practices/

- Internationalization:
  https://developer.wordpress.org/plugins/internationalization/

- Privacy:
  https://developer.wordpress.org/plugins/privacy/

- Plugin Check:
  https://developer.wordpress.org/plugins/developer-tools/helper-plugins/

- Readme:
  https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/

- Plugin Assets:
  https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/

When current official documentation conflicts with this file, follow the
current official WordPress requirement and update this file accordingly.