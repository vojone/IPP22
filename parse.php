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

    $lOpts = array('help');
    $sOpts = '';
    $args = getopt($sOpts, $lOpts);

    $ret = PARSE_SUCCESS;
    if(!empty($args) && isset($args['help'])) {
        printHelp();
    }
    else {
        $stdin = fopen('php://stdin', 'r');
        $stdout = fopen('php://stdout', 'w');

        if(!$stdin || !$stdout) {
            $ret = INTERNAL_ERROR;
        }
        else {
            $parser = new Parser($stdin, $stdout);

            $ret = $parser->parse();
        }
    }

    exit($ret);



    function printHelp() {
        echo <<<END
        IPPcode22 parser (PHP8 skript)
        
        Program kontroluje lexikální a syntaktickou správnost programu 
        v jazyce IPPcode22, který čte ze standardního vstupu (STDIN). 
        Pokud je v pořádku je na stadardní výstup (STDOUT) vytisknuta 
        XML reprezentace vstupního programu.

        Použití:
        ./php8.1 parse.php [--help] [< 'vstupni_soubor'] [> 'vystupni soubor']

        Možnosti:
        --help      Vypíše stručnou nápovědu

        Návratové kódy:
        0   Vstupní program neobsahuje žádnou lexikální nebo syntaktickou chybu
        21  Chybějící/neplatná hlavička na začátku programu
        22  Neznámý/chybný operační kód
        23  Jiná lexikální syntaktická chyba
        99  Interní chyba parseru 

        END;
    }
?>