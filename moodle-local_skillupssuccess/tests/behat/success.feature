@local @local_skillupssuccess
Feature: Enable Student Success only in authorized courses
  Background:
    Given the following "courses" exist:
      | fullname      | shortname |
      | Support Lab   | SUPPORT   |
    And the following "users" exist:
      | username | firstname | lastname |
      | learner  | Sample    | Learner  |
    And the following "course enrolments" exist:
      | user    | course  | role    |
      | learner | SUPPORT | student |

  Scenario: A manager opts a course into early warning collection
    Given I log in as "admin"
    And I am on "Support Lab" course homepage
    When I navigate to "SkillUps Student Success Predictor" in current page administration
    And I follow "Course rules and activation"
    And I set the field "Enable scheduled signal collection" to "1"
    And I press "Save course rules"
    Then I should see "Changes saved."

  Scenario: A learner does not see the staff case dashboard
    Given I log in as "learner"
    When I am on "Support Lab" course homepage
    Then I should not see "SkillUps Student Success Predictor"
