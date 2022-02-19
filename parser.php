<?php
    require_once 'scanner.php';

    define('PARSE_SUCCESS', 0);
    define('INVALID_PROLOG', 21);
    define('INVALID_OPCODE', 22);
    define('OTHER_ERROR', 23);

    class Parser {
        private $scanner;

        private $tokenBuffer;

        private $reachedEOF;

        function __construct($input) {
            $this->scanner = new Scanner($input);
            $reachedEOF = false;
            $tokenBuffer = null;
        }

        private function printErrorMessage($type, $content) {
            $curPos = $this->scanner->getCursorPosition();

            echo "({$curPos['ROW']}, {$curPos['COL']})\t";
            echo "\033[31m{$type}\033[0m : {$content}\n";
        }

        private function isBufferEmpty() {
            return $this->tokenBuffer === null;
        }

        private function toBuffer($token) {
            $this->tokenBuffer = $token;
        }

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

            $argNum = strlen(Table::OPERATION_CODES[$op->getVal()]);
            while($argNum > 0) {
                $token = null;
                if(!$this->checkNext($token, false, type::LABEL, type::VARIABLE, type::TYPE, type::STR, type::INT, type::BOOL, type::NIL)) {
                    $this->printErrorMessage('Syntax error', "Argument expected, got: '{$token->getVal()}'");
                    return false;
                }

                $argNum--;
            }

            return true;
        }

        private function skipNewlines() {
            $token = null;

            while($this->checkNext($token, false, type::NEWLINE));

            $this->toBuffer($token);
        }

        private function checkNewline() {
            $token = null;
            if(!$this->checkNext($token, true, type::NEWLINE)) {
                $this->printErrorMessage('Syntax error', "Missing NEWLINE!");
                return false;
            }

            $this->skipNewlines();

            return true;
        }


        public function parse() {
            $token = null;
            $foundEOF = false;

            $this->skipNewlines();
            if($this->reachedEOF) {
                return PARSE_SUCCESS;
            }


            if(!$this->checkNext($token, $foundEOF, type::PROLOG)) {
                $this->printErrorMessage('Syntax error', "Header expected, got: '{$token->getVal()}'");
                
                return INVALID_PROLOG;
            }
            if($this->reachedEOF) {
                return PARSE_SUCCESS;
            }

            $retCode = PARSE_SUCCESS;
            while($this->checkOperation($token, $retCode)) {
                if($this->reachedEOF) {
                    break;
                }
            }

            return $retCode;
        }
    }
?>