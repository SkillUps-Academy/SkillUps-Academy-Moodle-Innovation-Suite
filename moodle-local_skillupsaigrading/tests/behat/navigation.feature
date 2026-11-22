@local @local_skillupsaigrading
Feature: Manage assignment reviews in a Moodle course
  Background:
    Given the following "courses" exist:
      | fullname       | shortname |
      | Grading Studio | GRADING   |
    And the following "users" exist:
      | username | firstname | lastname |
      | learner  | Sample    | Learner  |
    And the following "course enrolments" exist:
      | user    | course  | role    |
      | learner | GRADING | student |
    And the following "activities" exist:
      | activity | name              | course  | idnumber |
      | assign   | Fieldwork report  | GRADING | FIELD    |

  Scenario: An administrator can find the native assignment review dashboard
    Given I log in as "admin"
    And I am on "Grading Studio" course homepage
    When I navigate to "SkillUps AI Grading Assistant" in current page administration
    And I follow "Fieldwork report"
    Then I should see "Review statistics"
    And I should see "Select Rubric as the advanced grading method"

  Scenario: A student does not have the teacher review navigation entry
    Given I log in as "learner"
    When I am on "Grading Studio" course homepage
    Then I should not see "SkillUps AI Grading Assistant"
