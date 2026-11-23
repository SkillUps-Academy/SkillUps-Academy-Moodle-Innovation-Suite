@local @local_skillupscertify
Feature: Role boundaries and verification in SkillUps Certify
  In order to keep student certificates private
  As a Moodle user
  I need the credential registry and public verification to stay separate

  Scenario: An administrator can reach template management from the registry
    Given the following "courses" exist:
      | fullname       | shortname |
      | Design Studies | CERT101   |
    When I am on the "CERT101" "local_skillupscertify > registry" page logged in as "admin"
    Then I should see "Certificate registry"
    And I follow "Certificate templates"
    And I should see "New template"

  Scenario: A learner can open only their own wallet through the private endpoint
    Given the following "users" exist:
      | username |
      | learner  |
    And I log in as "learner"
    When I visit "/local/skillupscertify/mine.php"
    Then I should see "My certificates"
    And I should not see "Certificate templates"

  Scenario: Public verification rejects an unknown code
    When I visit "/local/skillupscertify/verify.php?code=ffffffffffffffffffffffffffffffffffffffffffffffff"
    Then I should see "No credential could be verified using this code."
    And I should not see "Download PDF"
