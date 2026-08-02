Feature: Developer can use for-comprehensions

  A for-comprehension is syntax sugar over withEach, withFilter, map and flatMap.
  It is desugared at compile time, so there is no runtime cost.

  Scenario: Iterating over a single generator
    Given a phunkie file containing:
      """
      for ($a <- ImmList(1, 2, 3)) { echo $a; }
      """
    When I compile it
    Then the compiled PHP should be equivalent to:
      """
      ImmList(1, 2, 3)->withEach(function ($a) {
          echo $a;
      });
      """

  Scenario: Iterating over several generators
    Given a phunkie file containing:
      """
      for ($a <- ImmList(1, 2, 3); $b <- ImmList($a); $c <- ImmList($b)) { echo $a + $b + $c; }
      """
    When I compile it
    Then the compiled PHP should be equivalent to:
      """
      ImmList(1, 2, 3)->withEach(function ($a) {
          ImmList($a)->withEach(function ($b) use ($a) {
              ImmList($b)->withEach(function ($c) use ($a, $b) {
                  echo $a + $b + $c;
              });
          });
      });
      """

  Scenario: Yielding from a single generator maps over it
    Given a phunkie file containing:
      """
      for ($a <- ImmList(1, 2, 3)) yield $a + 1;
      """
    When I compile it
    Then the compiled PHP should be equivalent to:
      """
      ImmList(1, 2, 3)->map(function ($a) {
          return $a + 1;
      });
      """

  Scenario: Yielding from several generators flatMaps all but the last
    Given a phunkie file containing:
      """
      for {
          $a <- Some(42)
          $b <- Some($a + 1)
          $c <- Some($b + $a + 3)
      } yield $c;
      """
    When I compile it
    Then the compiled PHP should be equivalent to:
      """
      Some(42)->flatMap(function ($a) {
          return Some($a + 1)->flatMap(function ($b) use ($a) {
              return Some($b + $a + 3)->map(function ($c) use ($a, $b) {
                  return $c;
              });
          });
      });
      """

  Scenario: Filtering a generator with a guard
    Given a phunkie file containing:
      """
      for {
          $a <- ImmList(1, 2, 3) if $a % 2 == 0
      } yield $a;
      """
    When I compile it
    Then the compiled PHP should be equivalent to:
      """
      ImmList(1, 2, 3)->withFilter(function ($a) {
          return $a % 2 == 0;
      })->map(function ($a) {
          return $a;
      });
      """

  Scenario: Filtering several generators
    Given a phunkie file containing:
      """
      for {
          $a <- ImmList(1, 2, 3) if $a % 2 == 0
          $b <- ImmList(1, 2, 3) if $b % 2 != 0
      } yield ($a, $b);
      """
    When I compile it
    Then the compiled PHP should be equivalent to:
      """
      ImmList(1, 2, 3)->withFilter(function ($a) {
          return $a % 2 == 0;
      })->flatMap(function ($a) {
          return ImmList(1, 2, 3)->withFilter(function ($b) use ($a) {
              return $b % 2 != 0;
          })->map(function ($b) use ($a) {
              return Pair($a, $b);
          });
      });
      """

  Scenario: A guard on its own line filters the generator above it
    Given a phunkie file containing:
      """
      for {
          $a <- ImmList(1, 2, 3)
          $b <- ImmList(1, 2, 3)
          if $b % 2 != 0
      } yield ($a, $b);
      """
    When I compile it
    Then the compiled PHP should be equivalent to:
      """
      ImmList(1, 2, 3)->flatMap(function ($a) {
          return ImmList(1, 2, 3)->withFilter(function ($b) use ($a) {
              return $b % 2 != 0;
          })->map(function ($b) use ($a) {
              return Pair($a, $b);
          });
      });
      """

  Scenario: Destructuring tuples in a generator
    Given a phunkie file containing:
      """
      for {
          ($a) <- Some(Tuple(1))
          ($b, $c) <- Some(Pair(1, 2))
      } yield $b;
      """
    When I compile it
    Then the compiled PHP should be equivalent to:
      """
      Some(Tuple(1))->flatMap(function ($t1) {
          $a = $t1->_1;
          return Some(Pair(1, 2))->map(function ($t2) use ($a) {
              $b = $t2->_1;
              $c = $t2->_2;
              return $b;
          });
      });
      """

  Scenario: Yielding a tuple
    Given a phunkie file containing:
      """
      for {
          $a <- Some(42)
          $b <- Some(43)
      } yield ($a, $b);
      """
    When I compile it
    Then the compiled PHP should be equivalent to:
      """
      Some(42)->flatMap(function ($a) {
          return Some(43)->map(function ($b) use ($a) {
              return Pair($a, $b);
          });
      });
      """

  Scenario: Ignoring generated values with the wildcard
    Given a phunkie file containing:
      """
      for {
          $line <- IO\readline()
          _ <- IO\write($line, '/tmp/some_file.txt')
          _ <- IO\printLn("You have successfully written to file")
      } yield ();
      """
    When I compile it
    Then the compiled PHP should be equivalent to:
      """
      IO\readline()->flatMap(function ($line) {
          return IO\write($line, '/tmp/some_file.txt')->flatMap(function ($_) use ($line) {
              return IO\printLn("You have successfully written to file")->map(function ($_) use ($line) {
                  return Unit();
              });
          });
      });
      """
