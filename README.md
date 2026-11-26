# Tutor LMS Content Drip

Scheduled release for Tutor LMS Free (target 4.2.1). No changes to Tutor core.

## Setup
WP Admin > Tools > Content Drip: select a course, enable, set planned topic and activity counts, then set the release rule per published topic. Modes: always open, manual, scheduled, enrollment-relative. Dates use the WordPress timezone; enrollment-relative days are elapsed 24-hour days. Newly added topics default to locked. For BIA's four weeks with three activities each, set 4 planned topics and 12 planned activities, even if only two topics are currently published.

## Behavior
Locked activities stay in the denominator and are excluded from the numerator; missing planned topics/activities reserve progress. Native auto-completion uses the protected stats. Normal learner direct URL, sidebar, lesson/quiz actions, known AJAX handlers and Tutor v4.2.1 REST content routes are guarded. Core REST bodies are redacted. The aggregate Tutor REST topic/curriculum endpoints are conservatively denied while any topic is locked. Enrollment-relative REST routes fail closed if the token viewer is not a WordPress user. Editors may preview, not bypass learner eligibility. Unmanaged courses retain native behavior.

The native quiz progress definition is a non-started attempt, not necessarily a pass. Score/assessment certificate conditions are separate. External video URLs need their own access control. Exclude managed routes from full-page/CDN caching and purge cache on policy updates.

## CertPSU
The public filter tlcd_course_completion_allowed accepts (allowed, course_id, user_id). The CertPSU Bridge should check it against the target learner before issuing. This plugin does not change CertPSU or implement manual certificate approval. Tutor's before-completion action omits the target learner ID, so third-party direct calls to the low-level model must explicitly validate the filter. Existing completion records and certificates are not deleted or revoked; review them before enabling or changing a policy.

## Abilities
tutorlms-content-drip/get-policy and tutorlms-content-drip/update-policy. Update replaces the complete policy, including rules keyed by topic ID. Course edit permission required; scheduled local_time format YYYY-MM-DDTHH:MM.

## Tests and scope
PHP policy unit tests and WP/Tutor integration fixture are included. Initial scope is Tutor LMS Free lessons and quizzes. Browser-level, production theme, third-party integrations, REST/AJAX E2E and CertPSU need staging verification prior to production use.

GPL-2.0-or-later.
