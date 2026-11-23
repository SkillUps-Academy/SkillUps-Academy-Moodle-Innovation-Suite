@local @local_skillupsgamification
Feature: Open course gamification management
  As an authorized Moodle administrator
  I can enable gamification in a course

  Background:
    Given the following "courses" exist:
      | fullname                | shortname |
      | Gamification Demo Course | GAMEQA    |

  Scenario: Enable progress dashboard from the course menu
    Given I log in as "admin"
    And I am on the "Gamification Demo Course" course page
    And I follow "SkillUps Gamification Pro"
    And I follow "Manage gamification"
    When I set the field "Enabled" to "1"
    And I press "Save changes"
    Then I should see "Changes saved."
    When I follow "Progress"
    Then I should see "Available points"
    And I should see "Progress to next level"
