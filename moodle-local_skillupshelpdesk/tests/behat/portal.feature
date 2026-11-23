@local @local_skillupshelpdesk
Feature: Open and find a support request
  As an authenticated requester
  I can open a Helpdesk ticket from Moodle

  Scenario: Open a support ticket
    Given I log in as "admin"
    And I follow "SkillUps Helpdesk"
    When I follow "Open ticket"
    And I set the field "Title" to "Training account issue"
    And I set the field "Describe the issue" to "Synthetic course access question"
    And I press "Open ticket"
    Then I should see "Ticket opened."
    And I should see "Training account issue"
    And I should see "Synthetic course access question"
    When I follow "Tickets"
    Then I should see "Training account issue"
