@local @local_skillupsgradebookguardian
Feature: Run and review a course gradebook diagnosis
  In order to identify problems in the course gradebook
  As a Moodle administrator
  I can run a read-only scan and review its history

  Background:
    Given the following "courses" exist:
      | fullname         | shortname |
      | Gradebook Course | GGQA      |

  Scenario: A course diagnosis appears in history
    Given I log in as "admin"
    And I am on the "Gradebook Course" course page
    And I click on "SkillUps Gradebook Guardian" "link"
    When I press "Run diagnosis"
    Then I should see "Diagnosis saved."
    And I should see "Categories"
    And I should see "Findings"
    When I follow "Back to history"
    Then I should see "Audit history"
    And I should see "Diagnosis"
