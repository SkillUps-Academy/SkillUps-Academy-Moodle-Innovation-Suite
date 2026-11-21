
# Moodle Marketplace submission draft

- **Component** `local_skillupscopilot`
- **Type** Local plugin
- **Provider** SkillUps Academy
- **License** GPL-3.0-or-later

## Short English description

A course-aware AI assistant for Moodle with authorized course document retrieval, local and external model connectors, and institutional usage controls.

## Full English description

SkillUps AI Copilot gives students, instructors and administrators a context-specific assistant inside Moodle. Students can ask questions and draft study aids. Authorized instructors can generate draft activities and questions. Administrators can configure providers, apply daily request budgets and review measured usage. Course managers curate text documents and may restrict their availability by group and Moodle activity. The assistant labels AI responses and keeps private conversation history.

Community supports Ollama without a SkillUps Cloud account. Institutions may enable an approved OpenAI or compatible endpoint and require users to acknowledge external transfers. Optional signed entitlements enable aggregate CSV export. SkillUps Academy is an independent provider and is unaffiliated with Moodle.

## Submission material

- [Installation and provider configuration](administration.md)
- [User instructions](user-guide.md)
- [Testing and compatibility](validation.md)
- [Privacy and reporting](../SECURITY.md)
- [Release notes](../CHANGELOG.md)
- Bundled third-party libraries: none. The plugin uses Moodle Core services. Provider applications are installed and licensed separately by their operators.
- [Proposed issue tracker](https://github.com/SkillUps-Academy/SkillUps-Academy-Moodle-Innovation-Suite/issues)

Moodle's current public FAQ describes the listing and package review sequence, including registration, archive and CI checks, screenshots and manual publication. The detailed submission and quick-start links required a Marketplace-linked Atlassian sign-in during review. Verify component availability, commercial terms and the current handling of bundled Spanish strings or AMOS in the seller portal before submission. Store English as the canonical language under `lang/en` and bundle Spanish under `lang/es` in this draft. [Moodle Marketplace FAQ](https://docs.moodle.org/503/en/Plugins_FAQ)
