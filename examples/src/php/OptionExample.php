<?php

$result = Some(42)->flatMap(function($a) {
        return Some($a + 1)->map(function($b) use ($a) {
            return $b;
        });
    });
