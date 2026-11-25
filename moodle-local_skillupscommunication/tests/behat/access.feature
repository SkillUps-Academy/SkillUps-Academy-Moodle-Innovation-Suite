@local @local_skillupscommunication
Feature: Scoped Communication Center navigation
  Staff with the configured capabilities can compose and manage communications

  Scenario: An administrator can open campaign authoring
    Given I log in as "admin"
    When I visit "/local/skillupscommunication/index.php"
    Then I should see "SkillUps Communication Center"
    And I should see "New communication"
    And I should see "Templates and settings"
    When I follow "New communication"
    Then I should see "Communication type"
    And I should see "Scheduled date and time"
