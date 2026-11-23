@tool @tool_skillupsenrollment
Feature: Confirm an enrollment after a simulation
  Scenario: An administrator simulates and runs a student enrollment
    Given the following "users" exist:
      | username | firstname | lastname |
      | learnerqa | Student  | Example  |
    And the following "courses" exist:
      | fullname | shortname |
      | Training | COURSE-QA |
    And I log in as "admin"
    When I visit "/admin/tool/skillupsenrollment/create.php"
    And I set the field "Username" to "learnerqa"
    And I set the field "Course short name" to "COURSE-QA"
    And I set the field "Role ID or short name" to "student"
    And I press "Run simulation"
    Then I should see "No enrollment in this instance"
    And I should see "Requested change is pending"
    When I press "Confirm valid rows"
    Then I should see "Queued"
    When I run the scheduled task "\tool_skillupsenrollment\task\dispatch"
    And I run all adhoc tasks
    And I reload the page
    Then I should see "Applied"
