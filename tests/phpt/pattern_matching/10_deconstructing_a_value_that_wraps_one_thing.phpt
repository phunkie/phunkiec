--TEST--
Deconstructing a value that wraps one thing
--FILE--
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
--EXPECT--
<?php $y = (fn($on) => match (true) { $on(\Phunkie\PatternMatching\Referenced\Id($v)) => $v, $on(\Phunkie\PatternMatching\Referenced\ImmString($s)) => $s, $on(\Phunkie\PatternMatching\Referenced\ImmInteger($i)) => $i, $on(\Phunkie\PatternMatching\Referenced\Function1($f)) => $f, $on(\Phunkie\PatternMatching\Referenced\IO($thunk)) => $thunk(), $on(\Phunkie\PatternMatching\Referenced\State($run)) => $run, $on(\Phunkie\PatternMatching\Referenced\Reader($run)) => $run, $on(\Phunkie\PatternMatching\Referenced\Kleisli($run)) => $run, $on(\Phunkie\PatternMatching\Referenced\OptionT($monad)) => $monad, $on(\Phunkie\PatternMatching\Referenced\EitherT($monad)) => $monad, $on(\Phunkie\PatternMatching\Referenced\StateT($run)) => $run, })(pmatch($x));
