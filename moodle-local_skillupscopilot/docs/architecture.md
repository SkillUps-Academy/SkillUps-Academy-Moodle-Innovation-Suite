# SkillUps AI Copilot architecture

## Investigation and implementation plan

Moodle 5.0.7 source was available at XAMPP. The PHP CLI is 8.2.12. That source tree has no config.php and XAMPP ships MariaDB 10.4.32, below Moodle 5.0's 10.11 minimum. Moodle 5.3 LTS requires PHP 8.3 and MariaDB 11.4 or PostgreSQL 17. The local component slot was free. Registry uniqueness still requires Marketplace confirmation.

Moodle's AI subsystem provides provider and policy abstractions for its own actions. This local plugin is used because the product spans system and course screens, retrieval, conversation storage and institutional quota enforcement. It uses Moodle context, enrolment, availability, groups, File, DML, Lock, Event, Privacy, task, navigation, form and output APIs. Existing products such as AI Manager and ONLYOFFICE AI integrations provide useful product comparisons. The plugin deliberately supports explicitly curated course text sources and accesses only its own file areas, as required by the File API ownership model.

1. Establish context and capabilities, with course access tied to native course viewing and active enrolment.
2. Define private course/site conversations, course sources with optional group/activity restrictions, a separate metering ledger and provider health records.
3. Validate privileges before reading source bytes and again in background execution. Persist a source revision manifest and withhold grounded responses whose sources are no longer accessible. Do not submit old grounded responses to a model.
4. Reserve daily request quota under an institution-wide lock, then queue the turn inside the same transaction. Authorize again before dispatch. Bound the prompt, source count, output and retry count.
5. Add role-specific Moodle UI, provider selection, course source management, response ratings, and aggregate usage.
6. Keep provider credentials and endpoint allowlists in deployment configuration. No model can invoke Moodle actions. Provide a locally verified signed entitlement for premium aggregate export.
7. Test lexical ranking, permissions, cross-course isolation, worker failures, privacy, packaging and compatibility. Report only executed checks.

References: [Moodle File API](https://moodledev.io/docs/5.0/apis/subsystems/files/internals), [Lock API](https://moodledev.io/docs/5.0/apis/core/lock), [Moodle 5.0](https://moodledev.io/general/releases/5.0), [Moodle 5.3](https://moodledev.io/general/releases/5.3), [Moodle Marketplace](https://marketplace.moodle.com/).
