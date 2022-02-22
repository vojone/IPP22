<?php
    /**************************************************************************
     *                                  IPP project                           *  
     *                                  parser.php                           *
     *                                                                        *
     *                                 Vojtech Dvorak                         *
     *                                 February 2022                          *
     *************************************************************************/


    require_once 'scanner.php';
    require_once 'printer.php';
    require_once 'token.php';

    //Return codes of the parser
    define('PARSE_SUCCESS', 0);
    define('INVALID_PROLOG', 21);
    define('INVALID_OPCODE', 22);
    define('OTHER_ERROR', 23);
    define('INTERNAL_ERROR', 99);

    /**
     * Parses given program in source language and prints XML output
     */
    class Parser {
        /**
         * Scanner object, that provides tokens to parser
         */
        private $scanner;

        /**
         * Printer object, that is used for creating target XML
         */
        private $printer;

        /**
         * Temporary storage for one token
         */
        private $tokenBuffer;

        /**
         * Flag, that indicates end of input file
         */
        private $reachedEOF;

        /**
         * File pointer to a file, where will be printed error messages 
         */
        private $logStream;

        /**
         * Instruction counter
         */
        private $insOrd;

        /**
         * Parser constructor
         * @param FilePointer $input File pointer to a file with source code
         * @param FilePointer $output File pointer to a file, where should be printed target representation
         */
        function __construct($input, $output) {
            $this->scanner = Scanner::instantiate($input);

            $this->printer = new XMLPrinter($output, "\t", 1);

            $this->reachedEOF = false;
            $this->tokenBuffer = null;
            $this->insOrd = 1;

            $this->log = fopen('php://stderr', 'a');
        }

        /**
         * Prints error message to $log
         * @param String $type Highlighted prefix of error message
         * @param String $content Content of the error message
         */
        private function printErrorMessage($type, $content) {
            $curPos = $this->scanner->getCursorPosition();

            fwrite($this->log, "({$curPos['ROW']}, {$curPos['COL']})\t");
            fwrite($this->log, "\033[31m{$type}\033[0m : {$content}\n");
        }

        /**
         * Check whether token buffer is empty or not
         */
        private function isBufferEmpty() {
            return $this->tokenBuffer === null;
        }

        /**
         * Puts given tokn to token buffer
         * @param Token $token
         */
        private function toBuffer($token) {
            $this->tokenBuffer = $token;
        }

        /**
         * Takes one next token from buffer or from input and check its validity
         */
        private function getNextToken() {
            $token = null;

            if($this->isBufferEmpty()) {
                $token = $this->scanner->nextToken();
                if($token->getType() === type::ERROR) {
                    $str = $token->getVal();
                    $this->printErrorMessage('Lexical error', "Invalid token {$str}! ");

                    exit(OTHER_ERROR);
                }
            }
            else {
                $token = $this->tokenBuffer;
                $this->toBuffer(null);
            }

            return $token;
        }

        /**
         * Check type of the next token
         * @param Token $token Returns check token trhough it
         * @param Bool $canBeEOF Flag indicates whether check is EOF tolerant or not
         * @param Array $expTypes Array of all expected (possible) token types
         * @return Bool True if type corresponds at least with one expected type (or it is EOF and EOF fag is true)
         */
        private function checkNext(&$token, $canBeEOF, ...$expTypes) {
            $token = $this->getNextToken();
            $type = $token->getType();

            if($type === type::EOF) {
                $this->reachedEOF = true;

                if($canBeEOF) {
                    return true;
                }
            }

            foreach($expTypes as $expType) {
                if($token->getType() === $expType) {
                    return true;
                }
            }

            return false;
        }

        /**
         * Checks whether next token is valid instruction (line with instruction and ts arguments)
         * @param Token $op Returns read instruction token through it
         * @param Int $retCode Specifies error though it
         * @return Bool True if valid instruction with arguments was read
         */
        private function checkOperation(&$op, &$retCode) {
            if(!$this->checkNewline()) {
                $retCode = OTHER_ERROR;
                return false;
            }

            if($this->reachedEOF) {
                return true;
            }

            if(!$this->checkNext($op, true, type::OPCODE)) {
                $this->printErrorMessage('Syntax error', "Operation code expected, got: '{$op->getVal()}'");
                $retCode = INVALID_OPCODE;
                return false;
            }


            $this->printer->startInstruction($op->getVal(), $this->insOrd);

            if(!$this->checkArgs($op)) {
                return false;
            }

            $this->printer->endInstruction();

            $this->insOrd++;

            return true;
        }

        /**
         * Checks arguments and prints of instruction
         * @param Token $op Token with intruction its arguments should be checked
         * @return Bool True if arguments are OK
         */
        private function checkArgs($op) {
            $expArgs = Table::OPERATION_CODES[$op->getVal()];
            $argNum = strlen($expArgs);
            for($i = 1; $i <= $argNum; $i++) {
                $token = null;

                $possibleTokens = Table::charToTypes($expArgs[$i - 1]);
                $isOk = false;

                $token = $this->getNextToken();
                foreach($possibleTokens as $possibleToken) {
                    if($token->getType() === $possibleToken) {
                        $isOk = true;
                        break;
                    }
                }

                if(!$isOk) {
                    $expected = Table::UICharToStr($expArgs[$i - 1]);
                    $typeStr = Table::UITypeToStr($token->getType());
                    $this->printErrorMessage('Syntax error', "Expected {$expected}, got: '{$token->getVal()}' which is {$typeStr}!");
                    $retCode = OTHER_ERROR;
                    return false;
                }

                $argValue = $token->getPurifiedVal();
                $type = Table::typeToStr($token->getType());
                $this->printer->printArgument($i, $type, $argValue);
            }
        }

        /**
         * Skips all empty lines
         */
        private function skipNewlines() {
            $token = null;

            while($this->checkNext($token, false, type::NEWLINE));

            $this->toBuffer($token);
        }

        /**
         * Checks if next token is NEWLINE
         * @return Bool True if yes
         */
        private function checkNewline() {
            $token = null;
            if(!$this->checkNext($token, true, type::NEWLINE)) {
                $this->printErrorMessage('Syntax error', "Missing NEWLINE!");
                return false;
            }

            $this->skipNewlines();

            return true;
        }

        /**
         * Parses input file
         */
        public function parse() {
            $token = null;
            $foundEOF = false;

            $this->printer->initDoc();

            //Skip initial empty lines
            $this->skipNewlines();
            if($this->reachedEOF) {
                return PARSE_SUCCESS;
            }


            //Check if there is header (rpolog)
            if(!$this->checkNext($token, $foundEOF, type::PROLOG)) {
                $this->printErrorMessage('Syntax error', "Header expected, got: '{$token->getVal()}'");
                
                return INVALID_PROLOG;
            }
            if($this->reachedEOF) {
                return PARSE_SUCCESS;
            }


            //Check instruction 'list'
            $retCode = PARSE_SUCCESS;
            while($this->checkOperation($token, $retCode)) {
                if($this->reachedEOF) {
                    break;
                }
            }

            $this->printer->endDoc();

            if($retCode === PARSE_SUCCESS) {
                $this->printer->printXML();
            }

            return $retCode;
        }
    }
?>