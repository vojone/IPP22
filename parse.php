<?php
    ini_set('display_errors', 'stderr');

    require_once 'parser.php';

    $stdin = fopen('php://stdin', 'r');

    $parser = new Parser($stdin);

    $ret = $parser->parse();

    exit($ret);
?>