# Security

For vulnerabilities, contact the SkillUps Academy maintainer privately or use private vulnerability reporting for the suite when available. Omit real ticket content or attachments from public reports.

Moodle checks the operator's role and the ticket relationship on every detail, comment, report and attachment request. Public and internal attachments are separate message areas with independent access checks. Input is escaped as plain text. Moodle Forms and sesskey protect mutations. File validation requires local files, matching content type and extension, and size and quantity limits. The File API forces an authenticated download. Site antivirus scanning depends on enabled Moodle antivirus plugins.

Notifications disclose only a generic service message and the portal address. Moodle controls channels, delivery policies and the retention of notices and events after they enter core subsystems. Maintain a functioning cron and a shared lock backend if the site is clustered. Retries revalidate the recipient's access before sending.
