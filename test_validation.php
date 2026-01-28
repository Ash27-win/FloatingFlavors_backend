<?php
$val1 = " ";
echo "Space: " . (!$val1 ? "Caught" : "Passed") . ", Int: " . (int)$val1 . "\n";

$val2 = "undefined";
echo "Undefined Str: " . (!$val2 ? "Caught" : "Passed") . ", Int: " . (int)$val2 . "\n";

$val3 = "0";
echo "Zero Str: " . (!$val3 ? "Caught" : "Passed") . ", Int: " . (int)$val3 . "\n";
