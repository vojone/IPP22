<?php
    require_once 'scanner.php';

    $stdin = fopen('php://stdin', 'r');

    $scanner = new Scanner($stdin);


?>