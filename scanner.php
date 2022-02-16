<?php
    ini_set('display_errors', 'stderr');

    require_once 'token.php';
    require_once 'tables.php';

    enum state {
        case INIT;
        case COMMENT;
        case NEWLINE;
        case PROLOG;
        case LABEL;
        case OPCODE;
        case VARIABLE;
        case STR_LITERAL;
        case INT_LITERAL;
        case NIL_LITERAL;
    }

    class Scanner {
        private $inputStream = null;

        private $cursorPosition = array(0, 0);

        private $charBuffer = null;

        private $table = null;

        function __construct($input) {
            $this->inputStream = $input;
            $this->table = new Table();
        }
        
        
        private function getChar() {
            if($this->charBuffer !== null) {
                $tmp = $this->charBuffer;
                $this->charBuffer = null;
                
                return $tmp;
            }
            else  {
                return fgetc($this->inputStream);
            }
        }

        private function toBuffer($chararacter) {
            $this->charBuffer = $character;
        }

        private function isBufferEmpty() {
            return $this->charBuffer === null;
        }

        public function nextToken() {
            $state = state::INIT;

            $nextState = null;
            $token = null;
            $tokenValue = null;
            while(!feof($this->inputStream)) {
                $currentCharacter = $this->getChar();
                $tokenValue = $tokenValue.$currentCharacter;
               
            }

            return $token;
        } 
    }
?>