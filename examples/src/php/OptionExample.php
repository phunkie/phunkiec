<?php

$result = Some(42)->flatMap(function($a) {
        return Some($a + 2)->map(function($b) use ($a) {
            return $a;
        });
    });
