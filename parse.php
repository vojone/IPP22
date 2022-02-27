<?php
    /**************************************************************************
     *                                  IPP project                           *  
     *                                   parse.php                            *
     *                                                                        *
     *                          Main body of IPPcode22 parser                 *
     *                                                                        *
     *                                 Vojtech Dvorak                         *
     *                                  February 2022                         *
     *************************************************************************/


    ini_set('display_errors', 'stderr');

    require_once 'parser.php';
    require_once 'ui.php';

    $stdin = fopen('php://stdin', 'r');
    $stdout = fopen('php://stdout', 'w');
    $stderr = fopen('php://stderr', 'w');

    if(!$stdin || !$stdout) {
        exit(INTERNAL_ERROR);
    }

    $ui = new UI($stdin, $stdout, $stderr);
    $ui->parseArgs();

    if($ui->wasHelpCalled()) {
        $ui->printHelp();
    }

    $parser = new Parser($stdin, $stdout, $ui);
    $ret = $parser->parse();


    if($ret === PARSE_SUCCESS) {
        $ui->printStats();
    }

    exit($ret);
?>