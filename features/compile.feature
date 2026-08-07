Feature: Developer can compile a .phunkie file into .php

  Scenario: The help names the binary, there being no command to choose between
    When I ask for help
    Then the output should mention "phunkiec [options] [--] <input>"
    And the output should not mention "compile [options]"

  Scenario: The synopsis printed with an error names the binary too
    When I run it with no arguments
    Then the output should mention "phunkiec [-o|--out OUT]"

  # The compiler has no grammar of its own, so anything it does not recognise is
  # passed through untouched. That is right for ordinary PHP and wrong for a
  # phunkie notation it has yet to learn, and the two are indistinguishable here.
  # Reading the output back with PHP's own parser is what tells them apart.
  Scenario: PHP that does not parse is an error, however well the macros ran
    Given a phunkie file containing:
      """
      $todo = ;
      """
    When I compile it
    Then the compiler should have failed saying "The compiled PHP does not parse"
    And the failure should name the line it broke on

  # The compiler opens the tag for a source that did not open its own, so the
  # line PHP counts to is two past the line the reader would count to. Naming a
  # line that is not in the file you were handed is worse than naming none.
  Scenario: The line named is a line in the file that was written
    Given a phunkie file with no opening tag containing:
      """
      $ok = 1;
      $todo = ;
      """
    When I compile it
    Then the compiler should have failed saying "on line 2"

  Scenario: PHP that does not parse is not written
    Given a phunkie file containing:
      """
      $todo = ;
      """
    When I compile it
    Then the file "build/Example.php" should not have been created

  # A write that fails and is not looked at is the worst of the failures here:
  # the compile says it worked, the build is empty, and the pipeline made of it
  # goes green.
  Scenario: A write that could not happen is a failure, not a success
    Given there is a file "src/One.phunkie" that is empty
    And there is a directory "build/One.php" where the output should go
    When I compile "src/One.phunkie" into "build/One.php"
    Then the compiler should have failed saying "Could not write"

  Scenario: Empty file
    Given there is a file "src/Empty.phunkie" that is empty
    When I compile "src/Empty.phunkie" into "build/Empty.php"
    Then the file "build/Empty.php" should be created
    And the file "build/Empty.php" should be empty
