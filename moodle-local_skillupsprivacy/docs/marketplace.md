# Moodle Marketplace submission materials

Component `local_skillupsprivacy` · Local plugin · SkillUps Academy · GPL v3 or later.

## Short English description

An institutional privacy request register for Moodle with responsibility assignment, retention review, deadline alerts and auditable evidence.

## Full English description

SkillUps Privacy Manager gives Moodle privacy officers an internal register for access, export, deletion and anonymization cases. Officers assign responsibility, set operational deadlines, review retention, approve referrals and record completion. The register can link native export and deletion requests and display the latest Moodle status. The technical inventory comes from installed Privacy API providers. Reports summarize the case register and an authorized operator can download minimized CSV evidence.

Native data operations remain in the Moodle privacy workflow. The release implements Moodle Privacy API discovery, export and approved erasure for its own records. Community operates with no external service. Optional signed offline entitlements extend the evidence CSV. The included languages are English and Spanish.

## Distribution

- [Release ZIP](../../releases/skillupsprivacy/skillupsprivacy-0.9.0-beta.1.zip) and [checksum](../../releases/skillupsprivacy/SHA256SUMS).
- [Installation and administrator guide](administration.md), [operator guide](operations.md), [security](../SECURITY.md) and [compatibility report](validation.md).
- [Dashboard](screenshots/dashboard.png), [case](screenshots/case.png) and [inventory](screenshots/inventory.png).
- [Source](https://github.com/SkillUps-Academy/SkillUps-Academy-Moodle-Innovation-Suite/tree/main/moodle-local_skillupsprivacy) and [issue tracker](https://github.com/SkillUps-Academy/SkillUps-Academy-Moodle-Innovation-Suite/issues). Source uses the SkillUps monorepo. The plugin subtree is the package root.
- The release bundles no third-party libraries. The runtime depends on Moodle and native `tool_dataprivacy`. Optional entitlement verification uses PHP sodium.

The GitHub distribution contains both language files and uses English as the canonical source. Moodle's [legacy localization workflow](https://docs.moodle.org/en/Translating_plugins) describes AMOS for contributed translations. The Marketplace provider documentation was located, but its external Confluence pages did not expose the full criteria in the public response during validation. Confirm current localization and source-repository rules in the provider portal before submission. Materials are prepared for review. Submission and approval have not taken place.

[How to submit a plugin](https://moodle.atlassian.net/wiki/external/MzFlM2RkYjM3ZDVhNDgyMGJmYjA2ZjIyMzQ1NDRlYmY) · [Current submission guidelines](https://moodle.atlassian.net/wiki/external/ODRhYjAyNTY4NDVmNGJlNjljN2ViMzkwYzdmYmIwMGI)
