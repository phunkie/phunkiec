<?php

use Phunkie\Compiler\Watch\SourceSnapshot;

describe("SourceSnapshot", function () {
    it("reports a source that was not there before", function () {
        $before = new SourceSnapshot(["App/Todo.phunkie" => "a"]);
        $after = new SourceSnapshot(["App/Todo.phunkie" => "a", "App/List.phunkie" => "b"]);

        expect($after->changedSince($before))->toBe(["App/List.phunkie"]);
    });

    it("reports a source whose contents differ", function () {
        $before = new SourceSnapshot(["App/Todo.phunkie" => "a"]);
        $after = new SourceSnapshot(["App/Todo.phunkie" => "b"]);

        expect($after->changedSince($before))->toBe(["App/Todo.phunkie"]);
    });

    it("reports nothing when every source is untouched", function () {
        $snapshot = new SourceSnapshot(["App/Todo.phunkie" => "a", "App/List.phunkie" => "b"]);

        expect($snapshot->changedSince($snapshot))->toBe([]);
    });

    // A source that is gone cannot be recompiled, and the PHP it produced is
    // left alone: deleting generated files on the strength of a poll is a worse
    // failure than leaving one behind.
    it("does not report a source that was removed", function () {
        $before = new SourceSnapshot(["App/Todo.phunkie" => "a", "App/List.phunkie" => "b"]);
        $after = new SourceSnapshot(["App/Todo.phunkie" => "a"]);

        expect($after->changedSince($before))->toBe([]);
    });
});
