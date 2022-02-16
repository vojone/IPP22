<?php
    ini_set('display_errors', 'stderr');

    require_once 'scanner.php';

    $stdin = fopen('php://stdin', 'r');

    $scanner = new Scanner($stdin);

    $token = null;
    do {
        $token = $scanner->nextToken();
        var_dump($token);
    }
    while($token->getType() !== type::EOF)

?>