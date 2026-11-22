@local @local_skillupsflow
Feature: Access SkillUps Flow Automation
  Scenario: An administrator can open the dashboard
    Given I log in as "admin"
    When I navigate to "Plugins > Local plugins > SkillUps Flow Automation > Workflow dashboard" in site administration
    Then I should see "Create a workflow or import a template to get started."
    And I should see "New workflow"

  Scenario: An administrator can open the approvals queue
    Given I log in as "admin"
    When I navigate to "Plugins > Local plugins > SkillUps Flow Automation > Approvals" in site administration
    Then I should see "No approvals are currently available to you."
