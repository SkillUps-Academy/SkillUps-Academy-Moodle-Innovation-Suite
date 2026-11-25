@local @local_skillupsrepository
Feature: Browse the institutional library
  Authenticated users can browse the library while contribution remains category-scoped

  Background:
    Given the following "users" exist:
      | username   | firstname | lastname |
      | reporeader | Course    | Reader   |

  Scenario: A site administrator can contribute and review
    Given I log in as "admin"
    When I visit "/local/skillupsrepository/index.php"
    Then I should see "SkillUps Content Repository"
    And I should see "Advanced search"
    And I should see "Add resource"
    And I should see "Management and review"

  Scenario: A user without library capabilities cannot contribute
    Given I log in as "reporeader"
    When I visit "/local/skillupsrepository/index.php"
    Then I should see "SkillUps Content Repository"
    And I should not see "Add resource"
    And I should not see "Management and review"
