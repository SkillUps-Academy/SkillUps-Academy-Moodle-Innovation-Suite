
@local @local_skillupscopilot
Feature: SkillUps AI Copilot course experience
  Background:
    Given the following "courses" exist:
      | fullname       | shortname |
      | Course Copilot | COPILOT   |
    And the following "users" exist:
      | username | firstname | lastname |
      | learner  | Sample    | Learner  |
    And the following "course enrolments" exist:
      | user    | course  | role   |
      | learner | COPILOT | student |

  Scenario: A learner can open their course assistant with providers disabled
    Given I log in as "learner"
    And I am on "Course Copilot" course homepage
    When I navigate to "SkillUps AI Copilot" in current page administration
    Then I should see "Your conversations for this context will appear here."
    And I should see "The selected provider is unavailable."
    And I should not see "Course sources"
    And I should not see "Copilot administration"

  Scenario: An administrator can open provider and quota controls
    Given I log in as "admin"
    When I navigate to "Plugins > Local plugins > SkillUps AI Copilot > Copilot administration" in site administration
    Then I should see "Provider statistics"
    And I should see "Course quota override"
    And I should see "Community"
