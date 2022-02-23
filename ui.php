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

    class UI {
        private $helpFlag;

        private $statFlag;
        
        private $stats;

        private $scanner;

        private $output;

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
                    $this->usageError(INVALID_ARG_COMBINATION, "--help option cannot be combined with any other parameter");
                }
                else {
                    $this->helpFlag = true;
                }
            }

            var_dump($args);
        }

        public function wasHelpCalled() {
            return $this->helpFlag;
        }

        public function updateStats() {

        }

        public function printStats() {
            
        }

        public function  usageError($retCode, $message) {
            fwrite($this->errOut, "\033[31mUsage error\033[0m : ");
            fwrite($this->errOut, "{$message}\n");
            exit($retCode);
        }

        /**
         * Prints error message to $log
         * @param String $type Highlighted prefix of error message
         * @param String $content Content of the error message
         */
        private function printErrorMessage($type, $content) {
            $curPos = $this->scanner->getCursorPosition();

            fwrite($this->errOut, "({$curPos['ROW']}, {$curPos['COL']})\t");
            fwrite($this->errOut, "\033[31m{$type}\033[0m : {$content}\n");
        }


        public function printHelp() {
            $help = <<<END
            IPPcode22 parser (PHP8 skript)
            
            Program kontroluje lexikální a syntaktickou správnost programu 
            v jazyce IPPcode22, který čte ze standardního vstupu (STDIN). 
            Pokud je v pořádku je na stadardní výstup (STDOUT) vytisknuta 
            XML reprezentace vstupního programu.

            Základní použití:
            ./php8.1 parse.php [--help] [< 'vstupni_soubor'] [> 'vystupni soubor']
            ./php8.1 parse.php [--stats="cesta"] [--loc, --jumps, --labels]

            Možnosti:
            --help      Vypíše nápovědu (nelze kombinovat s jiným parametrem)

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