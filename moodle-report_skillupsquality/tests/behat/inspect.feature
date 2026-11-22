@report @report_skillupsquality
Feature: Audit Moodle courses and explore institutional rules
  Scenario: A course audit can be requested through Moodle
    Given the following "courses" exist:
      | fullname         | shortname |
      | Evidence Course  | FIELD-QA  |
    And I log in as "admin"
    When I visit "/report/skillupsquality/index.php"
    And I follow "Evidence Course"
    Then I should see "No audits are available."
    When I click on "Request audit" "button"
    Then I should see "Audit queued for 1 course."
    And I should see "Queued"
    When I click on "Rule catalog" "link"
    Then I should see "Inverted dates"
    And I should see "Design recommendations"
    When I click on "Course audit history" "link"
    And I run all adhoc tasks
    And I reload the page
    And I click on "table.generaltable tbody a" "css_element"
    Then I should see "85 / 100"
    When I click on "Empty course" "link"
    And I click on "Edit corrective plan" "link"
    And I set the field "Action notes" to "Add orientation material."
    And I click on "Save plan" "button"
    Then I should see "Corrective plan saved."
    And I should see "Verification pending"
