@local @local_coderunner_pylint
Feature: CodeRunner Pylint feedback
  As a student
  I want to see linting feedback on my Python CodeRunner submissions
  So that I can improve my code quality

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Teacher   | One      |
      | student1 | Student   | One      |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |

  @javascript
  Scenario: Student sees pylint feedback after submitting Python code
    Given I am logged in as "teacher1"
    And I create a Python CodeRunner question in course "C1" with name "Hello World"
    And I create a quiz "Lint Quiz" in course "C1" with the question "Hello World"
    When I am logged in as "student1"
    And I attempt quiz "Lint Quiz" in course "C1"
    And I set the code answer to "x = 1\nprint(x)"
    And I press "Check"
    Then I should see "Code Quality Report" in the ".coderunner-pylint-panel" "css_element"
    And I should see element ".pylint-score" in the ".coderunner-pylint-panel" "css_element"
