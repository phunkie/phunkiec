Feature: Developer can compile a .phunkie file into .php

  Scenario: Empty file
    Given there is a file "src/Empty.phunkie" that is empty
    When I compile "src/Empty.phunkie" into "build/Empty.php"
    Then the file "build/Empty.php" should be created
    And the file "build/Empty.php" should be empty
