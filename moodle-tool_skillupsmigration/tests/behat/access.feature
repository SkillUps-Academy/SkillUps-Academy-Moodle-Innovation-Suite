@tool @tool_skillupsmigration
Feature: Access to the migration dashboard
  Migration plans and registered origins are visible to authorized operators.

  Scenario: A site administrator can see migration history and registered origins
    Given I log in as "admin"
    When I visit "/admin/tool/skillupsmigration/index.php"
    Then I should see "SkillUps Course Migration"
    And I should see "Migration history"

  Scenario: Opening the dashboard without a session requires login
    When I visit "/admin/tool/skillupsmigration/index.php"
    Then I should see "Log in"
    And I should not see "Migration history"
