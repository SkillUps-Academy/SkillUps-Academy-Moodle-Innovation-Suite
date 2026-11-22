@local @local_skillupsquestionstudio
Feature: Manage Question Studio projects in a Moodle course
  Background:
    Given the following "courses" exist:
      | fullname            | shortname |
      | Teaching laboratory | STUDIO    |
    And the following "users" exist:
      | username | firstname | lastname |
      | learner  | Sample    | Learner  |
    And the following "course enrolments" exist:
      | user    | course | role    |
      | learner | STUDIO | student |

  Scenario: An administrator can create a course project
    Given I log in as "admin"
    And I am on "Teaching laboratory" course homepage
    When I navigate to "SkillUps Question Studio" in current page administration
    And I set the field "Project name" to "Learning assessment"
    And I press "Create project"
    Then I should see "Learning assessment"
    And I should see "New question"

  Scenario: A learner does not have a Question Studio navigation entry
    Given I log in as "learner"
    When I am on "Teaching laboratory" course homepage
    Then I should not see "SkillUps Question Studio"
