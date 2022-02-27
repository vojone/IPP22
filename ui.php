<?php
    /**************************************************************************
     *                                  IPP project                           *  
     *                                    ui.php                              *
     *                                                                        *
     *                                 Vojtech Dvorak                         *
     *                                  February 2022                         *
     *************************************************************************/

    define('SUCCESS', 0);
    define('INVALID_ARG_COMBINATION', 0);

    /**
     * Provides commnication interface with user 
     * (error printing, collecting of stats...)
     */
    class UI {
        /**
         * @var Boolean $helpFlag signalizes that --help flag was used
         */
        private $helpFlag;

        /**
         * @var Boolean $statFlag signalizes that --stats flag was used
         */
        private $statFlag;
        
        /**
         * @var Array $stats contains specification of chosen statistic info
         */
        private $stats;

        /**
         * @var Scanner $scanner object with current position of cursor
         */
        private $scanner;

        /**
         * @var Resource $output where will be printed help
         */
        private $output;

        /**
         * @var Resource $errOut File where will be error messages printed
         */
        private $errOut;

        private const LONG_OPTIONS = array(
            'help', 'stats:', 'loc', 'comments', 'labels', 'jumps', 'fwdjumps',
            'backjumps', 'badjumps'
        );

        private const SHORT_OPTIONS = '';

        public function __construct($input, $output, $errOut) {
            $this->scanner = Scanner::instantiate($input);
            $this->output = $output;
            $this->errOut = $errOut;
        }


        public function parseArgs() {
            $args = getopt(UI::SHORT_OPTIONS, UI::LONG_OPTIONS);

            if(isset($args['help'])) {
                if(count($args) > 1) {
                    $this->usageError(INVALID_ARG_COMBINATION, "--help přepínač nemůže být kombinován s jiným přepínačem!");
                }
                else {
                    $this->helpFlag = true;
                }
            }
        }

        /**
         * Help flage getter
         */
        public function wasHelpCalled() {
            return $this->helpFlag;
        }

        public function updateStats() {

        }

        public function printStats() {
            
        }

        /**
         * Prints usage error to given err. output and ends program with gitven error code
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
         */
        public static function CharToStr($char) {
            switch($char) {
                case '&':
                    return "proměnná nebo konstanta";
                case 'v':
                    return "proměnná";
                case 'l':
                    return "návěští";
                case 't':
                    return "typový specifikátor";
            }
        }

        /**
         * Converts type to string to be understood by user
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
            $help = <<<END
            IPPcode22 parser (PHP8 skript)
            
            Program kontroluje lexikální a syntaktickou správnost programu 
            v jazyce IPPcode22, který čte ze standardního vstupu (STDIN). 
            Pokud je v pořádku je na stadardní výstup (STDOUT) vytisknuta 
            XML reprezentace vstupního programu.

            Základní použití:
            ./php8.1 parse.php [--help] [< 'vstupni_soubor'] [> 'vystupni soubor']
            ./php8.1 parse.php [--stats="cesta"] [--loc, --jumps, --labels, ...]

            Možnosti:
            --help      Vypíše nápovědu (NELZE KOMBINOVAT s jiným parametrem)

            Návratové kódy:
            0   Vstupní program neobsahuje žádnou lexikální nebo syntaktickou chybu
            21  Chybějící/neplatná hlavička na začátku programu
            22  Neznámý/chybný operační kód
            23  Jiná lexikální syntaktická chyba
            99  Interní chyba parseru 

            END;

            fwrite($this->output, $help);

            exit(SUCCESS);
        }
    } 
?>