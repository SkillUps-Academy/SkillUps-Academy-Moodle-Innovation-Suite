@local @local_skillupspathways
Feature: Course-scoped SkillUps learning pathways
  In order to organize learning across courses
  As a course participant
  I need a course portal with role-appropriate actions

  Background:
    Given the following "courses" exist:
      | fullname             | shortname |
      | Foundations pathway  | PATH101   |
    And the following "users" exist:
      | username |
      | learner  |
    And the following "course enrolments" exist:
      | user    | course  | role    |
      | learner | PATH101 | student |

  Scenario: A student sees the pathway listing without management actions
    When I am on the "PATH101" "local_skillupspathways > portal" page logged in as "learner"
    Then I should see "No pathways are available in this course."
    And I should not see "Pathway designer"

  Scenario: An administrator can open the pathway designer
    Given I am on the "PATH101" "local_skillupspathways > portal" page logged in as "admin"
    When I follow "Pathway designer"
    Then I should see "Pathway name"
    And I should see "Allow certification integrations to verify completion"
