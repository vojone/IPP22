<?php
    ini_set('display_errors', 'stderr');

    require_once 'parser.php';

    $stdin = fopen('php://stdin', 'r');
    $stdout = fopen('php://stdout', 'w');

    $parser = new Parser($stdin, $stdout);

    $ret = $parser->parse();

    exit($ret);
?>