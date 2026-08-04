Feature: Developer can compile a .phunkie file into .php

  Scenario: The help names the binary, there being no command to choose between
    When I ask for help
    Then the output should mention "phunkiec [options] [--] <input>"
    And the output should not mention "compile [options]"

  Scenario: The synopsis printed with an error names the binary too
    When I run it with no arguments
    Then the output should mention "phunkiec [-o|--out OUT]"

  Scenario: PHP that does not parse is an error, however well the macros ran
    Given a phunkie file containing:
      """
      class Todo<F> {
      }
      """
    When I compile it
    Then the compiler should have failed saying "The compiled PHP does not parse"
    And the failure should name the line it broke on

  Scenario: PHP that does not parse is not written
    Given a phunkie file containing:
      """
      class Todo<F> {
      }
      """
    When I compile it
    Then the file "build/Example.php" should not have been created

  Scenario: Empty file
    Given there is a file "src/Empty.phunkie" that is empty
    When I compile "src/Empty.phunkie" into "build/Empty.php"
    Then the file "build/Empty.php" should be created
    And the file "build/Empty.php" should be empty
