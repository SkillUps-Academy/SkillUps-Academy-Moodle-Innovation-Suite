# Contributing

Use Moodle 5.3 LTS as the reference and verify any added Gradebook API against all supported branches. Keep the English strings canonical and the Spanish strings in sync. Run Moodle CodeSniffer, the component PHPUnit suite, and its Behat feature on an isolated Moodle installation.

To add a rule, register a unique identifier, severity and treatment in `classes/rules.php`, describe its evidence in the operations guide and add a fixture covering the condition. Diagnostics must avoid native functions that create or normalize persistent records. Include a regression test comparing stored grades and totals whenever changing a repair path.

Report bugs in the [suite issue tracker](https://github.com/SkillUps-Academy/SkillUps-Academy-Moodle-Innovation-Suite/issues), using synthetic examples. Follow [SECURITY.md](SECURITY.md) for security issues.
