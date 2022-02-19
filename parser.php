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

        private $logStream;

        private $xml;

        function __construct($input) {
            $this->scanner = new Scanner($input);
            $this->reachedEOF = false;
            $this->tokenBuffer = null;

            $this->log = fopen('php://stderr', 'a');
            $this->out = fopen('php://stdout', 'w');
        }

        private function printErrorMessage($type, $content) {
            $curPos = $this->scanner->getCursorPosition();

            fwrite($this->log, "({$curPos['ROW']}, {$curPos['COL']})\t");
            fwrite($this->log, "\033[31m{$type}\033[0m : {$content}\n");
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

            xmlwriter_start_element($this->xml, 'instruction');
            xmlwriter_start_attribute($this->xml, 'opcode');
            xmlwriter_text($this->xml, strtoupper($op->getVal()));
            xmlwriter_end_attribute($this->xml);

            $argNum = strlen(Table::OPERATION_CODES[$op->getVal()]);
            for($i = 1; $i <= $argNum; $i++) {
                $token = null;
                if(!$this->checkNext($token, false, type::LABEL, type::VARIABLE, type::TYPE, type::STR, type::INT, type::BOOL, type::NIL)) {

                    $this->printErrorMessage('Syntax error', "Argument expected, got: '{$token->getVal()}'");
                    return false;
                }

                xmlwriter_start_element($this->xml, "arg{$i}");
                xmlwriter_start_attribute($this->xml, 'type');
                xmlwriter_text($this->xml, 'var');
                xmlwriter_end_attribute($this->xml);

                $tokenValue = $token->getVal();
                $argValue = null;
                if(strpos($tokenValue, '@') !== false) {
                    $argValue = substr($tokenValue, strpos($tokenValue, '@'));
                }
                else {
                    $argValue = $tokenValue;
                }
                
                xmlwriter_text($this->xml, htmlspecialchars($argValue, ENT_XML1, 'UTF-8'));

                xmlwriter_end_element($this->xml);
            }

            xmlwriter_end_element($this->xml);

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

            $this->xml = xmlwriter_open_memory();
            xmlwriter_set_indent($this->xml, 1);
            xmlwriter_set_indent_string($this->xml, "\t");

            xmlwriter_start_document($this->xml, '1.0', 'UTF-8');


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

            xmlwriter_start_element($this->xml, 'program');
            xmlwriter_start_attribute($this->xml, 'language');
            xmlwriter_text($this->xml, 'IPPcode22');
            xmlwriter_end_attribute($this->xml);

            $retCode = PARSE_SUCCESS;
            while($this->checkOperation($token, $retCode)) {
                if($this->reachedEOF) {
                    break;
                }
            }

            xmlwriter_end_element($this->xml);

            xmlwriter_end_document($this->xml);

            echo xmlwriter_output_memory($this->xml);

            return $retCode;
        }
    }
?>