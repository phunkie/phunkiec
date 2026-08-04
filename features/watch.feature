Feature: Developer can have sources recompiled as they are saved

  Scenario: The sources present at startup are compiled before watching begins
    Given there is a source "src/App/Todo.phunkie" containing "$x = Some(1);"
    And the compiler is watching "src" into "build"
    Then "build/App/Todo.php" should eventually contain "Some(1)"

  Scenario: Saving a source recompiles it
    Given there is a source "src/App/Todo.phunkie" containing "$x = Some(1);"
    And the compiler is watching "src" into "build"
    When I save "src/App/Todo.phunkie" containing "$x = Some(2);"
    Then "build/App/Todo.php" should eventually contain "Some(2)"

  Scenario: Adding a source compiles it, at the path it sits at
    Given there is a source "src/App/Todo.phunkie" containing "$x = Some(1);"
    And the compiler is watching "src" into "build"
    When I save "src/Deep/Down/Other.phunkie" containing "$y = Some(3);"
    Then "build/Deep/Down/Other.php" should eventually contain "Some(3)"

  Scenario: Watching carries on across repeated saves
    Given there is a source "src/App/Todo.phunkie" containing "$x = Some(1);"
    And the compiler is watching "src" into "build"
    When I save "src/App/Todo.phunkie" containing "$x = Some(2);"
    Then "build/App/Todo.php" should eventually contain "Some(2)"
    When I save "src/App/Todo.phunkie" containing "$x = Some(3);"
    Then "build/App/Todo.php" should eventually contain "Some(3)"

  Scenario: Each recompile is reported, so the watch shows it is working
    Given there is a source "src/App/Todo.phunkie" containing "$x = Some(1);"
    And the compiler is watching "src" into "build"
    When I save "src/App/Todo.phunkie" containing "$x = Some(2);"
    Then the watch log should eventually contain "App/Todo.phunkie"

  Scenario: The output directory may be written onto the short option
    Given there is a source "src/App/Todo.phunkie" containing "$x = Some(1);"
    When I compile "src" with "-o=build"
    Then "build/App/Todo.php" should eventually contain "Some(1)"

  Scenario: An output directory that cannot be made is reported rather than warned about
    Given there is a file "build" that is empty
    And there is a source "src/App/Todo.phunkie" containing "$x = Some(1);"
    When I compile "src" with "-o=build"
    Then the compiler should have failed saying "Could not create the output directory"
