@report @report_skillupsmonitor
Feature: Navigate the site operations report as an administrator
  Scenario: The reports distinguish missing observations and provide access to operational views
    Given I log in as "admin"
    When I visit "/report/skillupsmonitor/index.php"
    Then I should see "Waiting for the first scheduled observation."
    And I should see "Cron heartbeat"
    When I click on "Availability history" "link"
    Then I should see "Internal availability and coverage"
    And I should see "Unknown or unobserved intervals"
    When I click on "Incidents" "link"
    Then I should see "No incidents match these filters."
    When I click on "Diagnostics" "link"
    Then I should see "Canonical HTTPS URL"
