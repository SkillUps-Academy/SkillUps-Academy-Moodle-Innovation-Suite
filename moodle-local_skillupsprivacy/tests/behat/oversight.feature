@local @local_skillupsprivacy
Feature: Navigate the SkillUps Privacy Manager
  In order to oversee institutional requests
  As a site privacy operator
  I can register a case and consult Moodle privacy information

  Scenario: Register and review a standalone access request
    Given I log in as "admin"
    When I visit "/local/skillupsprivacy/index.php"
    And I follow "Register case"
    And I set the field "Subject user ID" to "2"
    And I press "Register case"
    Then I should see "Case details"
    And I should see "Case registered"

  Scenario: Open the native requests and data inventory
    Given I log in as "admin"
    When I visit "/local/skillupsprivacy/index.php"
    And I follow "Moodle requests"
    Then I should see "Open Moodle data requests"
    When I follow "Data inventory"
    Then I should see "Privacy declaration available"
