<?php

use Phunkie\Compiler\Generics\Companions;

describe("Companions", function () {
    $add = fn (string $source, string $compiled) => (new Companions())->addTo("<?php\n" . $source, $compiled);

    it("appends the mirror in a global block and brackets the namespace", function () use ($add) {
        $result = $add(
            "namespace Phunkie\\Types;\n#[Companion]\nfinal class Some<T>(T \$value) extends Option<T>;",
            "<?php\n\nnamespace Phunkie\\Types;\n\nfinal class Some extends Option\n{\n}\n"
        );

        expect($result)->toContain('namespace Phunkie\Types {');
        expect($result)->toContain('namespace {');
        expect($result)->toContain("if (!function_exists('Some')) {");
        expect($result)->toContain('function Some(mixed $value): \Phunkie\Types\Some');
        expect($result)->toContain('return new \Phunkie\Types\Some($value);');
    });

    it("writes the one instance and the bare name for a singleton", function () use ($add) {
        $result = $add(
            "namespace Phunkie\\Types;\n#[Companion(singleton: true, withArguments: false)]\nfinal class None extends Option;",
            "<?php\n\nnamespace Phunkie\\Types;\n\nfinal class None extends Option\n{\n}\n"
        );

        expect($result)->toContain('function None(): \Phunkie\Types\None');
        expect($result)->toContain('static $instance;');
        expect($result)->toContain('return $instance ??= new \Phunkie\Types\None();');
        expect($result)->toContain("if (!defined('None')) {");
        expect($result)->toContain("define('None', None());");
    });

    it("keeps the bare name out of it when the singleton still takes parentheses", function () use ($add) {
        $result = $add(
            "namespace X;\n#[Companion(singleton: true)]\nfinal class Registry;",
            "<?php\n\nnamespace X;\n\nfinal class Registry\n{\n}\n"
        );

        expect($result)->toContain('static $instance;');
        expect($result)->not()->toContain('define(');
    });

    it("folds a variadic head through its cases by name alone", function () use ($add) {
        $result = $add(
            "namespace Phunkie\\Types;\n#[Companion(variadic: [NonEmptyList, Nil])]\nabstract class ImmList<T>\n{\n}",
            "<?php\n\nnamespace Phunkie\\Types;\n\nabstract class ImmList\n{\n}\n"
        );

        expect($result)->toContain('function ImmList(mixed ...$values): \Phunkie\Types\ImmList');
        expect($result)->toContain('$list = Nil;');
        expect($result)->toContain('foreach (array_reverse($values) as $value) {');
        expect($result)->toContain('$list = NonEmptyList($value, $list);');
    });

    it("sends null to the empty case of a nullable head", function () use ($add) {
        $result = $add(
            "namespace Phunkie\\Types;\n#[Companion(nullable: [Some, None])]\nabstract class Option<T>\n{\n}",
            "<?php\n\nnamespace Phunkie\\Types;\n\nabstract class Option\n{\n}\n"
        );

        expect($result)->toContain('function Option(mixed $value = null): \Phunkie\Types\Option');
        expect($result)->toContain('return null === $value ? None : Some($value);');
    });

    // On the head the fold accepts nothing and answers the empty case; on the
    // cons case itself an empty call would be a lie, so it refuses one. The
    // recipe reads what it decorates.
    it("demands at least one value when the variadic face is the cons case's own", function () use ($add) {
        $result = $add(
            "namespace Phunkie\\Types;\n#[Companion(withArguments: true)]\n#[Companion(named: Nel, variadic: [NonEmptyList, Nil])]\nfinal class NonEmptyList<T>(T \$head, ImmList<T> \$tail) extends ImmList<T>;",
            "<?php\n\nnamespace Phunkie\\Types;\n\nfinal class NonEmptyList extends ImmList\n{\n}\n"
        );

        expect($result)->toContain('function NonEmptyList(mixed $head, mixed $tail): \Phunkie\Types\NonEmptyList');
        expect($result)->toContain('function Nel(mixed ...$values): \Phunkie\Types\NonEmptyList');
        expect($result)->toContain("throw new \InvalidArgumentException('Nel() needs at least one value.');");
        expect($result)->toContain('foreach (array_reverse(array_slice($values, 1)) as $value) {');
        expect($result)->toContain('return NonEmptyList($values[0], $list);');
    });

    it("leaves a file with no companions exactly as it was", function () use ($add) {
        expect($add('final class Plain {}', "<?php\nfinal class Plain {}\n"))->toBe("<?php\nfinal class Plain {}\n");
    });

    // A script's own statements run where they stand, so the functions have to
    // exist before the first of them: in a file with no namespace the block
    // goes just under the tag, ahead of everything.
    it("puts the block ahead of a script's own code", function () use ($add) {
        $result = $add(
            "#[Companion]\nfinal class Coin(string \$currency);\n\necho Coin(\"gbp\")->currency;",
            "<?php\n\n#[Companion]\nfinal class Coin\n{\n}\n\necho Coin(\"gbp\")->currency;\n"
        );

        expect(strpos($result, "function Coin"))->toSatisfy(fn ($at) => $at < strpos($result, 'final class Coin'));
        expect($result)->not()->toContain('namespace {');
    });

    // The mirror is a global function, where a short class name would resolve
    // against the wrong namespace; the synthesized constructor still holds the
    // written type, so PHP enforces it one call deeper.
    it("keeps a built-in parameter type and widens a class-typed one", function () use ($add) {
        $result = $add(
            "namespace X;\n#[Companion]\nfinal class Wallet(Balance \$balance, string \$label);",
            "<?php\n\nnamespace X;\n\nfinal class Wallet\n{\n}\n"
        );

        expect($result)->toContain('function Wallet(mixed $balance, string $label): \X\Wallet');
    });
});
