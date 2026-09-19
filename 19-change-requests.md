# Production-readiness change requests

These items require a test environment, policy decision, or approved product scope that cannot be truthfully completed from mocked unit tests alone.

## CR-001 — Establish a real WordPress release test matrix

- **Affected backlog items:** PR-001, PR-003, PR-004, PR-005, PR-008, PR-010, PR-011, PR-013, PR-021
- **Reason:** The repository only provides mocked unit tests. It has no disposable WordPress database harness, multisite test environment, or browser smoke suite.
- **Requested decision:** Approve CI infrastructure that installs the packaged ZIP in WordPress 6.x/PHP 8.2, exercises lifecycle and privacy callbacks, and runs Playwright browser smoke tests. Include a separate multisite job.
- **Approval status:** Required before release candidate.

## CR-002 — Define and enforce the WPCS/Plugin Check baseline

- **Affected backlog items:** PR-015
- **Reason:** Existing source produces a large volume of WordPress coding-standard findings; this is inherited debt, not evidence that the release is compliant.
- **Requested decision:** Approve a baseline file with only justified, time-bounded exclusions, then enforce zero new violations and remediate high-risk existing violations. Add WordPress Plugin Check to CI.
- **Approval status:** Required before WordPress.org submission.

## CR-003 — Complete REST and outbound-request inventory

- **Affected backlog items:** PR-007, PR-012, PR-016, PR-023
- **Reason:** The plugin exposes approximately 135 REST routes and multiple third-party request paths. Only the high-risk paths reviewed in this execution have object-level checks and safe-fetch controls.
- **Requested decision:** Allocate a route/service inventory task that records capability, object authorization, input schema, response sensitivity, service host, data sent, timeout/retry behavior, and test coverage for every route and external request.
- **Approval status:** Required before release candidate.

## CR-004 — Preserve the v1.0 page-builder scope decision

- **Affected backlog items:** PR-009
- **Reason:** Elementor, Divi, WPBakery, and other builder integrations have been removed rather than shipped as unmaintained placeholders.
- **Requested decision:** Keep all builder integrations out of v1.0 unless a supported-builder matrix, owner, UX design, and automated integration tests are approved.
- **Approval status:** In force for v1.0.

## CR-005 — Confirm legal/product wording for privacy and external services

- **Affected backlog items:** PR-005, PR-010, PR-016, PR-017
- **Reason:** Code can accurately describe its behavior but cannot make legal compliance determinations. The plugin uses optional AI, indexing, analytics, social, and backend services.
- **Requested decision:** Have counsel/product approve the privacy-policy suggested text, consent notice wording, external-service disclosure, and all public screenshots/readme claims.
- **Approval status:** Required before public distribution.
