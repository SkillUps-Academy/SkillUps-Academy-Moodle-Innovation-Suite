@report @report_skillupssecurity
Feature: Site-scoped audit reports

  Scenario: A site administrator can see audit coverage and the role matrix
    Given I log in as "admin"
    When I visit "/report/skillupssecurity/index.php"
    Then I should see "Control coverage"
    And I should see "Queue security scan"
    When I follow "Role audit"
    Then I should see "Selected capabilities"

  Scenario: A delegated reviewer without inventory access sees the overview and aggregate findings
    Given the following "users" exist:
      | username |
      | reviewer |
    And the following "roles" exist:
      | shortname   | name          |
      | auditreader | Audit reader  |
    And the following "permission overrides" exist:
      | capability                    | permission | role        | contextlevel | reference |
      | report/skillupssecurity:view  | Allow      | auditreader | System       |           |
    And the following "role assigns" exist:
      | user     | role        | contextlevel | reference |
      | reviewer | auditreader | System       |           |
    And I log in as "reviewer"
    When I visit "/report/skillupssecurity/index.php"
    Then I should see "Control coverage"
    And I should not see "Privilege inventory"
    And I should not see "Queue security scan"
