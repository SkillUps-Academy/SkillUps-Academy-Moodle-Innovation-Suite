# Security

Report suspected vulnerabilities through GitHub private vulnerability reporting for the suite where available, or contact the SkillUps Academy maintainer privately through GitHub. Do not include real grades, student information or private site URLs in public issues.

Guardian authorizes hidden structural data per course. A diagnosis executes read-only Gradebook inspection, persists results in its own table and never requests learner grades. Changing visibility requires an eligible item, a POST with a Moodle sesskey, the plugin approval capability, and Moodle Gradebook management permission. The update uses Moodle `grade_update` with the visibility field only.

The preview expires in 24 hours. The plugin compares the item identity, module visibility, grade setup and modification time, serializes Guardian operations per course, and verifies the result in the same transaction as the audit decision. Native edits may use different locks. Coordinate approval with concurrent administration of the same gradebook. Any inconsistent state discovered in verification rolls back the assisted operation.

The plugin sends no data to external services. Optional license signatures are verified locally with a deployment-supplied Ed25519 public key. Use Moodle's log store retention for the additional operational events.
