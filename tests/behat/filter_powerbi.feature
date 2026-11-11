@filter @filter_powerbi
Feature: Show Power BI reports placed in content
  In order to read live reports where I learn
  As a user
  I need links to Power BI reports to show as the reports themselves

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Sam       | Student  | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the "powerbi" filter is "on"

  Scenario: A marked link shows the report and a plain link stays a link
    Given the following "activities" exist:
      | activity | course | name         | content                                                                                                                                                                                                                                          |
      | page     | C1     | Results page | <p><a class="powerbi-embed" href="https://app.powerbi.com/groups/me/reports/aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee?mdlheight=400">Enrolments</a></p><p><a href="https://app.powerbi.com/groups/me/reports/aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee">Plain link</a></p> |
    When I am on the "Results page" "page activity" page logged in as student1
    Then "figure.filter-powerbi iframe[title='Enrolments']" "css_element" should exist
    And "Full screen" "button" should exist in the "figure.filter-powerbi" "css_element"
    And I should see "Plain link"
    And "Plain link" "link" should not exist in the "figure.filter-powerbi" "css_element"

  Scenario: A report in the embedded mode explains that the site is not set up yet
    Given the following "activities" exist:
      | activity | course | name         | content                                                                                                                                                                  |
      | page     | C1     | Results page | <p><a class="powerbi-embed" href="https://app.powerbi.com/groups/me/reports/aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee?mdlmode=embedded">Enrolments</a></p> |
    When I am on the "Results page" "page activity" page logged in as student1
    Then I should see "This report needs the Power BI app registration"
    And "figure.filter-powerbi iframe" "css_element" should not exist
