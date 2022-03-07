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

    //Opening of standart streams to read input and prints result and errors
    $stdin = fopen('php://stdin', 'r');
    $stdout = fopen('php://stdout', 'w');
    $stderr = fopen('php://stderr', 'w');

    //Check if opening was succesful (stderr is missing because it is not critical)
    if(!$stdin || !$stdout) { 
        exit(INTERNAL_ERROR);
    }

    $ui = new UI($stdin, $stdout, $stderr);
    $ui->parseArgs();

    //If help was called, help is printed and program is terminated (@see printHelp in ui.php) 
    if($ui->wasHelpCalled()) {
        $ui->printHelp();
    }


    $parser = new Parser($stdin, $stdout, $ui);
    $ret = $parser->parse();

    if($ret = PARSE_SUCCESS) {
        $ui->printStats();
    }

    exit($ret);
?>