<?php
function sweep_linkify($href_string) {
    // TODO: should detect links in string
    return "<a href='$href_string'>$href_string</a>";
}

function sweep_array_random($array) {
    return $array[array_rand($array)];
}

function sweep_length($var) {
    return is_array($var) ? count($var) : mb_strlen($var);
}

