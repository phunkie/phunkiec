Feature: Developer can pattern match on a value

  Pattern matching checks a value against a pattern. It is desugared onto
  phunkie's pmatch, so that the subject is matched once and each case is asked
  in turn whether it matches.

  Scenario: A simpler switch statement
    Given a phunkie file containing:
      """
      $y = $x match {
          1 => "one",
          2 => "two"
      };
      """
    When I compile it
    Then the compiled PHP should be equivalent to:
      """
      $y = (fn($on) => match (true) {
          $on(1) => "one",
          $on(2) => "two",
      })(pmatch($x));
      """

  Scenario: Catching everything else with a wildcard
    Given a phunkie file containing:
      """
      $y = $x match {
          1 => "one",
          2 => "two",
          _ => "many"
      };
      """
    When I compile it
    Then the compiled PHP should be equivalent to:
      """
      $y = (fn($on) => match (true) {
          $on(1) => "one",
          $on(2) => "two",
          $on(_) => "many",
      })(pmatch($x));
      """

  Scenario: Deconstructing a list
    Given a phunkie file containing:
      """
      $total = $list match {
          Nil => 0,
          ImmList($x, Nil) => $x,
          ImmList($x, $xs) => $x + total($xs)
      };
      """
    When I compile it
    Then the compiled PHP should be equivalent to:
      """
      $total = (fn($on) => match (true) {
          $on(Nil) => 0,
          $on(\Phunkie\PatternMatching\Referenced\ListNoTail($x, Nil)) => $x,
          $on(\Phunkie\PatternMatching\Referenced\ListWithTail($x, $xs)) => $x + total($xs),
      })(pmatch($list));
      """

  Scenario: Deconstructing an Option
    Given a phunkie file containing:
      """
      $y = $option match {
          Some($value) => $value + 1,
          None => 0
      };
      """
    When I compile it
    Then the compiled PHP should be equivalent to:
      """
      $y = (fn($on) => match (true) {
          $on(\Phunkie\PatternMatching\Referenced\Some($value)) => $value + 1,
          $on(None) => 0,
      })(pmatch($option));
      """

  Scenario: Deconstructing a Validation
    Given a phunkie file containing:
      """
      $y = $validation match {
          Success($a) => $a,
          Failure($e) => $e->getMessage()
      };
      """
    When I compile it
    Then the compiled PHP should be equivalent to:
      """
      $y = (fn($on) => match (true) {
          $on(\Phunkie\PatternMatching\Referenced\Success($a)) => $a,
          $on(\Phunkie\PatternMatching\Referenced\Failure($e)) => $e->getMessage(),
      })(pmatch($validation));
      """

  Scenario: Matching a constructor by value or by wildcard deconstructs nothing
    Given a phunkie file containing:
      """
      $y = $option match {
          Some(42) => "the answer",
          Some(_) => "some other",
          None => "none"
      };
      """
    When I compile it
    Then the compiled PHP should be equivalent to:
      """
      $y = (fn($on) => match (true) {
          $on(Some(42)) => "the answer",
          $on(Some(_)) => "some other",
          $on(None) => "none",
      })(pmatch($option));
      """

  Scenario: Deconstructing an Either
    Given a phunkie file containing:
      """
      $y = $either match {
          Right($a) => $a,
          Left($e) => $e->getMessage()
      };
      """
    When I compile it
    Then the compiled PHP should be equivalent to:
      """
      $y = (fn($on) => match (true) {
          $on(\Phunkie\PatternMatching\Referenced\Right($a)) => $a,
          $on(\Phunkie\PatternMatching\Referenced\Left($e)) => $e->getMessage(),
      })(pmatch($either));
      """

  Scenario: Deconstructing values built from two parts
    Given a phunkie file containing:
      """
      $y = $x match {
          Pair($a, $b) => $a + $b,
          Nel($head, $tail) => $head,
          Cons($head, $tail) => $head,
          ImmSet($a, $b) => $a + $b
      };
      """
    When I compile it
    Then the compiled PHP should be equivalent to:
      """
      $y = (fn($on) => match (true) {
          $on(\Phunkie\PatternMatching\Referenced\Pair($a, $b)) => $a + $b,
          $on(\Phunkie\PatternMatching\Referenced\Nel($head, $tail)) => $head,
          $on(\Phunkie\PatternMatching\Referenced\Cons($head, $tail)) => $head,
          $on(\Phunkie\PatternMatching\Referenced\ImmSet($a, $b)) => $a + $b,
      })(pmatch($x));
      """

  Scenario: Deconstructing a tuple by writing its parentheses
    Given a phunkie file containing:
      """
      $y = $x match {
          ($a, $b) => $a + $b,
          ($a, $b, $c) => $a + $b + $c
      };
      """
    When I compile it
    Then the compiled PHP should be equivalent to:
      """
      $y = (fn($on) => match (true) {
          $on(\Phunkie\PatternMatching\Referenced\Pair($a, $b)) => $a + $b,
          $on(\Phunkie\PatternMatching\Referenced\Tuple($a, $b, $c)) => $a + $b + $c,
      })(pmatch($x));
      """

  Scenario: Deconstructing a value that wraps one thing
    Given a phunkie file containing:
      """
      $y = $x match {
          Id($v) => $v,
          ImmString($s) => $s,
          ImmInteger($i) => $i,
          Function1($f) => $f,
          IO($thunk) => $thunk(),
          State($run) => $run,
          Reader($run) => $run,
          Kleisli($run) => $run,
          OptionT($monad) => $monad,
          EitherT($monad) => $monad,
          StateT($run) => $run
      };
      """
    When I compile it
    Then the compiled PHP should be equivalent to:
      """
      $y = (fn($on) => match (true) {
          $on(\Phunkie\PatternMatching\Referenced\Id($v)) => $v,
          $on(\Phunkie\PatternMatching\Referenced\ImmString($s)) => $s,
          $on(\Phunkie\PatternMatching\Referenced\ImmInteger($i)) => $i,
          $on(\Phunkie\PatternMatching\Referenced\Function1($f)) => $f,
          $on(\Phunkie\PatternMatching\Referenced\IO($thunk)) => $thunk(),
          $on(\Phunkie\PatternMatching\Referenced\State($run)) => $run,
          $on(\Phunkie\PatternMatching\Referenced\Reader($run)) => $run,
          $on(\Phunkie\PatternMatching\Referenced\Kleisli($run)) => $run,
          $on(\Phunkie\PatternMatching\Referenced\OptionT($monad)) => $monad,
          $on(\Phunkie\PatternMatching\Referenced\EitherT($monad)) => $monad,
          $on(\Phunkie\PatternMatching\Referenced\StateT($run)) => $run,
      })(pmatch($x));
      """

  Scenario: Guarding a case with a condition
    Given a phunkie file containing:
      """
      $y = $option match {
          Some($v) if $v > 10 => "big",
          Some($v) => "small",
          _ => "none"
      };
      """
    When I compile it
    Then the compiled PHP should be equivalent to:
      """
      $y = (fn($on) => match (true) {
          $on(\Phunkie\PatternMatching\Referenced\Some($v)) && $v > 10 => "big",
          $on(\Phunkie\PatternMatching\Referenced\Some($v)) => "small",
          $on(_) => "none",
      })(pmatch($option));
      """

  Scenario: Matching on the result of an expression
    Given a phunkie file containing:
      """
      $y = $response->status() match {
          200 => "ok",
          _ => "not ok"
      };
      """
    When I compile it
    Then the compiled PHP should be equivalent to:
      """
      $y = (fn($on) => match (true) {
          $on(200) => "ok",
          $on(_) => "not ok",
      })(pmatch($response->status()));
      """
