<?php
    /**************************************************************************
     *                                  IPP project                           *  
     *                                    ui.php                              *
     *                                                                        *
     *                                 Vojtech Dvorak                         *
     *                                   March 2022                           *
     *************************************************************************/

    define('SUCCESS', 0);
    define('INVALID_ARG_COMBINATION', 10);
    define('FILE_ERROR', 12);
    
    require_once 'stat.php';

    /**
     * Provides commnication interface with user 
     * (argument parsing, error message printing)
     */
    class UI {
        /**
         * @var Scanner Scanner object
         */
        private $scanner;

        /**
         * @var Boolean $helpFlag signalizes that --help flag was used
         */
        private $helpFlag;

        /**
         * @var Boolean $statFlag signalizes that --stats flag was used
         */
        private $statFlag;

        /**
         * @var Resource $output where will be printed help
         */
        private $output;

        /**
         * @var Resource $errOut File where will be error messages printed
         */
        private $errOut;

        private const LONG_OPTIONS = array(
            'help', 'stats:', 'loc', 'comments', 'labels', 'jumps', 'fwjumps',
            'backjumps', 'badjumps'
        );

        private const SHORT_OPTIONS = '';


        public function __construct($input, $output, $errOut) {
            $this->output = $output;
            $this->errOut = $errOut;

            $this->scanner = Scanner::instantiate($input);
        }

        /**
         * Parse arguments and check presence of --help option
         * @param Array $argv Argument vector from main body of script
         */
        public function parseArgs($argv) {
            $args = getopt(UI::SHORT_OPTIONS, UI::LONG_OPTIONS);

            foreach ($argv as $key => $arg) {
                if($key === 0) {
                    continue;
                }

                //Checking validity of used options (NOW ONLY FOR LONG OPTIONS WITHOUT OPTIONAL PARAM!)
                $prefixLess = preg_replace('/^(--)/', '', $arg, limit : 1);
                $paramLess = preg_replace('/(=[^"\']+)$/', ':', $prefixLess, limit : 1);
                if(!in_array($paramLess, UI::LONG_OPTIONS)) {
                    $this->usageError(INVALID_ARG_COMBINATION, "Chybný přepínač {$arg}!");
                }
            }

            if(isset($args['help'])) {
                if(count($args) > 1) {
                    $this->usageError(INVALID_ARG_COMBINATION, "--help přepínač nemůže být kombinován s jiným přepínačem!");
                }
                else {
                    $this->helpFlag = true;
                }
            }

            if(!$this->helpFlag && count($args) > 0) {
                $this->checkArgs($argv, $args);
            }
        }

        /**
         * Checks validity arguments and parses it
         */
        private function checkArgs($argv, $args) {
            $statCollector = StatCollector::instantiate();

            $statsIndex = 0;
            $stats = array();
            $activeFilename = null;
            foreach ($argv as $order => $arg) {
                if($order === 0) {
                    continue;
                }

                $fileSpecifier = '/^--stats=.*$/';
                if(preg_match($fileSpecifier, $arg) && isset($args['stats'])) {

                    if(is_array($args['stats'])) {
                        $activeFilename = $args['stats'][$statsIndex];
                    }
                    else {
                        $activeFilename = $args['stats'];
                    }
                    
                    //There cannot be two groups of statistics in one file
                    if(array_key_exists($activeFilename, $stats)) { 
                        $this->usageError(FILE_ERROR, "Nelze zapsat do jednoho souboru (soubor '{$activeFilename}') dvě skupiny statistik!");
                    }
                    else {
                        $stats[$activeFilename] = array();
                        $statsIndex++;
                    }
                }
                else {

                    //File must be set before adding statistics flags
                    if($activeFilename === null) {
                        $this->usageError(INVALID_ARG_COMBINATION, "chybí přepínač --stats=\"FILE\" před přepínačem {$arg}!");
                    }
                    else {
                        $arg = preg_replace('/^(--)/', '', $arg, limit : 1);
                        if(isset($args[$arg])) {
                            $stats[$activeFilename][$arg] = 0;
                        }
                    }
                }
            }

            $statCollector->setStats($stats);    
        }

        /**
         * Help flag getter
         */
        public function wasHelpCalled() {
            return $this->helpFlag;
        }

        /**
         * Prints usage error to given err. output and ends program with gitven error code
         * @param Integer 
         * @param String Message that will be written
         */
        public function  usageError($retCode, $message) {
            fwrite($this->errOut, "\033[31mŠpatné použití\033[0m : ");
            fwrite($this->errOut, "{$message}\n");
            exit($retCode);
        }

        /**
         * Prints error message to $log
         * @param String $type Highlighted prefix of error message
         * @param String $content Content of the error message
         */
        public function printErrorMessage($type, $content) {
            $curPos = $this->scanner->getCursorPosition();

            fwrite($this->errOut, "({$curPos['ROW']}, {$curPos['COL']})\t");
            fwrite($this->errOut, "\033[31m{$type}\033[0m : {$content}\n");
        }

        /**
         * Converts type specifier from table of opcodes to user friendly string
         * @param Char $char character to be converted
         */
        public static function CharToStr($char) {
            switch($char) {
                case '&':
                    return "proměnná nebo konstanta";
                case 'v':
                    return "proměnná";
                case 'l':
                    return "název návěští";
                case 't':
                    return "typový specifikátor";
            }
        }

        /**
         * Converts type to string to be understood by user
         * @param type $type type to be converted
         */
        public static function TypeToStr($type) {
            switch($type) {
                case type::STR:
                    return 'string';
                case type::INT:
                    return 'integer';
                case type::BOOL:
                    return 'bool';
                case type::NIL:
                    return 'nil';
                case type::TYPE:
                    return 'typový specifikátor';
                case type::LABEL:
                    return 'návěští';
                case type::VARIABLE:
                    return 'proměnná';
                case type::OPCODE:
                    return 'instrukce';
                case type::NEWLINE:
                    return 'začátek nového řádku';
                case type::EOF:
                    return 'konec souboru';
            }
        }

        /**
         * Prints brief help to given output
         */
        public function printHelp() {
            $help = <<<HELP
            IPPcode22 - analyzátor kódu (PHP8 skript)
            
            Program kontroluje lexikální a syntaktickou správnost programu 
            v jazyce IPPcode22, který čte ze standardního vstupu (STDIN). 
            Pokud je v pořádku je na stadardní výstup (STDOUT) vytisknuta 
            XML reprezentace vstupního programu.

            Použití:
            ./php8.1 parse.php [--help] [< 'vstup'] [> 'vystup']
            ./php8.1 parse.php [< 'vstup'] [> 'vystup'] --stats='s' [--loc, --comments,...]

            Možnosti:
            --help      Vypíše nápovědu (NELZE KOMBINOVAT s jiným parametrem)
            --stats='s' Nastaví soubor cílový soubor pro ukládání statistik (musí předcházet
                        následujícím přepínačům)
            --loc       Vypíše do souboru se statistikami počet řádků kódu
            --comments  Vypíše do statistik počet řádků, na kterých se vyskytly
                        komentáře
            --jumps     Vypíše počet skokových instrukcí do souboru se statistikami
            --labels    Vypíše počet unikátních cílů skoku
            --fwjumps  Vypíše počet dopředných skoků
            --backjumps Vypíše počet zpětných skoků 
            --badjumps  Vypíše do souboru se statistikami počet skoků na neexistující návěští


            Návratové kódy:
            0   Vstupní program neobsahuje žádnou lexikální nebo syntaktickou chybu
            21  Chybějící/neplatná hlavička na začátku programu
            22  Neznámý/chybný operační kód
            23  Jiná lexikální/syntaktická chyba
            99  Interní chyba analyzátoru 

            HELP;

            fwrite($this->output, $help);

            exit(SUCCESS);
        }
    } 
?>