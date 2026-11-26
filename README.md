# Tutor LMS Content Drip

Scheduled release for Tutor LMS Free (target 4.2.1). No changes to Tutor core.

## Setup
Create all intended topics in Tutor LMS Course Builder > Curriculum first, even empty topics. Tutor LMS owns topic identities, titles and ordering; Content Drip never creates or edits curriculum posts.

WP Admin > Tutor LMS > Content Drip (or Tools > Content Drip): select a course, enable, set activity targets for its real topics and choose the release mode independently. Published activities are counted automatically. Each topic reserves at least one activity, or its greater planned/actual count, including empty locked topics. New topics default to locked when enabled. Renaming a topic, reordering or adding lessons/quizzes does not change its saved release rule, which is keyed by topic ID. Scheduled rules still open when their configured time arrives.

A legacy plan with obsolete reservations or an active aggregate target that would be reduced is not silently discarded. Explicitly confirm reconciliation after reviewing the real curriculum and targets. Create all intended topics before allowing learners to complete the course; the plugin cannot infer topics that have not been created. The interface uses Thai or English according to the administrator locale.

## Behavior
Locked activities stay in the denominator and are excluded from the numerator; missing planned topics/activities reserve progress. Native auto-completion uses the protected stats. Normal learner direct URL, sidebar, lesson/quiz actions, known AJAX handlers and Tutor v4.2.1 REST content routes are guarded. Core REST bodies are redacted. The aggregate Tutor REST topic/curriculum endpoints are conservatively denied while any topic is locked. Enrollment-relative REST routes fail closed if the token viewer is not a WordPress user. Editors may preview, not bypass learner eligibility. Unmanaged courses retain native behavior.

The native quiz progress definition is a non-started attempt, not necessarily a pass. Score/assessment certificate conditions are separate. External video URLs need their own access control. Exclude managed routes from full-page/CDN caching and purge cache on policy updates.

## CertPSU
The public filter tlcd_course_completion_allowed accepts (allowed, course_id, user_id). The CertPSU Bridge should check it against the target learner before issuing. This plugin does not change CertPSU or implement manual certificate approval. Tutor's before-completion action omits the target learner ID, so third-party direct calls to the low-level model must explicitly validate the filter. Existing completion records and certificates are not deleted or revoked; review them before enabling or changing a policy.

## Abilities
tutorlms-content-drip/get-policy and tutorlms-content-drip/update-policy. Update replaces release rules keyed by topic ID and accepts optional plan rows {topic_id, title, expected_activities}, with a positive ID belonging to a real published topic. Omitted topics use their actual counts; new placeholder rows are rejected. Existing legacy reservations remain protected until an explicit plan replacement with reconcile_legacy=true (when required); omitting plan preserves a previously saved plan. Previously saved legacy aggregate totals remain supported but cannot be changed to create phantom reservations. Course edit permission required; scheduled local_time format YYYY-MM-DDTHH:MM.

## Tests and scope
PHP policy unit tests and WP/Tutor integration fixture are included. Initial scope is Tutor LMS Free lessons and quizzes. Browser-level, production theme, third-party integrations, REST/AJAX E2E and CertPSU need staging verification prior to production use.

GPL-2.0-or-later.
