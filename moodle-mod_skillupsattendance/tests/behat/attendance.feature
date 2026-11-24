@mod @mod_skillupsattendance
Feature: Use the Attendance Pro activity
  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Test course | C1 |
    And the following "users" exist:
      | username | firstname | lastname |
      | learner | Synthetic | Learner |
    And the following "course enrolments" exist:
      | user | course | role |
      | learner | C1 | student |
    And the following "activities" exist:
      | activity | course | name | idnumber |
      | skillupsattendance | C1 | Course attendance | attendance |

  Scenario: Schedule a session from the activity
    Given I log in as "admin"
    When I am on the "Course attendance" "skillupsattendance activity" page
    And I follow "Add session"
    And I set the field "Session name" to "Workshop"
    And I press "Save session"
    Then I should see "Workshop"
    And I should see "Scheduled"

  Scenario: Students can access their report without session-management controls
    Given I log in as "learner"
    When I am on the "Course attendance" "skillupsattendance activity" page
    Then I should not see "Add session"
    When I follow "Attendance report"
    Then I should see "Counts cover finalized sessions"
