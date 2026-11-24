@local @local_skillupsintegration
Feature: Manage independent source connectors in Moodle
  In order to plan integrations safely
  As an integration administrator
  I can configure a connector and inspect its operational history

  Scenario: Register an inactive inbound connector
    Given I log in as "admin"
    When I visit "/local/skillupsintegration/index.php"
    And I follow "Add connector"
    And I set the field "Connector name" to "Admissions sandbox"
    And I press "Save changes"
    Then I should see "Admissions sandbox"
    And I should see "Disabled"

  Scenario: View the operational audit from the integration dashboard
    Given I log in as "admin"
    When I visit "/local/skillupsintegration/index.php"
    And I follow "Operational audit"
    Then I should see "There are no matching records."
