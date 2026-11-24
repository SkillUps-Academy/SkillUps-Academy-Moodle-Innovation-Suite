# Moodle Marketplace submission materials

Component `local_skillupsintegration` · Local plugin · SkillUps Academy · GNU GPL v3 or later.

## Short English description

Import user and course records from supported identity and institutional sources with Moodle previews, approval, retry and audit.

## Full English description

SkillUps Integration Hub provides connectors for Google Workspace, Microsoft 365 and REST gateways for academic or HR platforms. It also accepts authenticated events using Moodle REST and signed webhooks. Administrators choose field mappings, define safe transformations, monitor connectors and inspect pending simulations before an authorized operator approves native Moodle writes.

Imports create disabled-login users and hidden courses. The system records decisions, conflicts, connector health and retry outcomes. It uses Moodle APIs, capabilities and scheduled tasks. The package includes English and Spanish. Community works without external licensing, while providers require their own institutional credentials. Optional signed entitlements enable audit evidence CSV.

## Materials

- [Installation package](../../releases/skillupsintegration/skillupsintegration-0.9.0-beta.1.zip) and [checksum](../../releases/skillupsintegration/SHA256SUMS).
- [Administrator guide](administration.md), [operator guide](operations.md), [connector documentation](connectors.md), [security](../SECURITY.md) and [validation](validation.md).
- [Dashboard](screenshots/dashboard.png) and [simulation](screenshots/preview.png).
- [Source](https://github.com/SkillUps-Academy/SkillUps-Academy-Moodle-Innovation-Suite/tree/main/moodle-local_skillupsintegration) and [issue tracker](https://github.com/SkillUps-Academy/SkillUps-Academy-Moodle-Innovation-Suite/issues). The subtree is the plugin root in the SkillUps monorepo.
- Bundles no third-party libraries. Moodle provides HTTP, external services, privacy, tasks and core data APIs. Optional edition verification uses PHP sodium.

English is the canonical language source. The GitHub ZIP includes Spanish. The provider portal governs current submission requirements, including localization. This release is prepared for submission materials review. It has not been submitted to or approved by Moodle Marketplace.

[Moodle Marketplace](https://marketplace.moodle.com/) · [How to submit a plugin](https://moodle.atlassian.net/wiki/external/MzFlM2RkYjM3ZDVhNDgyMGJmYjA2ZjIyMzQ1NDRlYmY) · [Plugin submission guidelines](https://moodle.atlassian.net/wiki/external/ODRhYjAyNTY4NDVmNGJlNjljN2ViMzkwYzdmYmIwMGI)
