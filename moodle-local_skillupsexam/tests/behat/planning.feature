@local @local_skillupsexam
Feature: The exam planner respects role boundaries
  In order to organize exams without changing Moodle grades
  Authorized staff can open the planner and students cannot browse its contents

  Background:
    Given the following "courses" exist:
      | fullname            | shortname |
      | Exam planning course | PLANNING  |
    And the following "users" exist:
      | username | firstname | lastname |
      | examlearner | Exam  | Learner  |
    And the following "course enrolments" exist:
      | user        | course   | role    |
      | examlearner | PLANNING | student |

  Scenario: Site administrators can open the institutional dashboard
    Given I log in as "admin"
    When I visit "/local/skillupsexam/index.php"
    Then I should see "Exam calendar"
    And I should see "Plan an exam"
    And I should see "Academic periods and spaces"

  Scenario: An enrolled student does not receive planning navigation
    Given I am on the "PLANNING" "Course" page logged in as "examlearner"
    Then I should not see "SkillUps Exam Manager"
    And I should not see "Plan an exam"
