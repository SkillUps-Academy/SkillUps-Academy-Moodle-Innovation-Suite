@tool @tool_skillupscoursefactory
Feature: Preview and authorize a new Moodle course
  Scenario: A draft is confirmed from the operation page
    Given the following "categories" exist:
      | name             | idnumber |
      | Training         | TRAINING |
    And I log in as "admin"
    When I visit "/admin/tool/skillupscoursefactory/create.php"
    And I set the field "Destination category" to "Training"
    And I set the field "Course name" to "New training course"
    And I set the field "Short name" to "NEW-COURSE"
    And I press "Prepare preview"
    Then I should see "Review the destination and roles"
    And I should see "NEW-COURSE"
    When I press "Confirm operation"
    Then I should see "Moodle cron dispatches the operation"
    And I should see "Queued"
    When I run the scheduled task "\tool_skillupscoursefactory\task\dispatch"
    And I run all adhoc tasks
    And I reload the page
    Then I should see "Completed"
