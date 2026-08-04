Feature: Developer can compile a .phunkie file into .php

  Scenario: The help names the binary, there being no command to choose between
    When I ask for help
    Then the output should mention "phunkiec [options] [--] <input>"
    And the output should not mention "compile [options]"

  Scenario: The synopsis printed with an error names the binary too
    When I run it with no arguments
    Then the output should mention "phunkiec [-o|--out OUT]"

  Scenario: Empty file
    Given there is a file "src/Empty.phunkie" that is empty
    When I compile "src/Empty.phunkie" into "build/Empty.php"
    Then the file "build/Empty.php" should be created
    And the file "build/Empty.php" should be empty
