@report @report_skillupsinsight
Feature: Institutional analytics permissions and academic periods
  Scenario: A site administrator creates a period and opens the dashboard
    Given I log in as "admin"
    And I visit "/report/skillupsinsight/periods.php"
    When I set the following fields to these values:
      | Name         | Integration semester |
      | Starts (UTC) | 2026-01-01           |
      | Ends (UTC)   | 2026-06-30           |
    And I press "Create period"
    Then I should see "Integration semester"
    When I visit "/report/skillupsinsight/index.php"
    Then I should see "SkillUps Insight Analytics"
    And I should see "waiting for its first published snapshot"

  Scenario: A learner lacks institution-wide analytics access
    Given the following "users" exist:
      | username |
      | learner  |
    And I log in as "learner"
    When I visit "/my/"
    Then I should not see "SkillUps Insight Analytics"
