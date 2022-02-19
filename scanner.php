<?php
    require_once 'token.php';
    require_once 'tables.php';

    enum state {
        case INIT;
        case COMMENT;
        case NEWLINE;
        case WNEWLINE;
        case PROLOG;
        case DIRTY_TOKEN;
        case EOF;
    }

    class Scanner {
        private $inputStream;

        private $cursorPosition;

        private $charBuffer;

        private $strBuffer;

        function __construct($input) {
            $this->inputStream = $input;
            $this->cursorPosition = array(
                'ROW' => 1,
                'COL' => 1
            );

            $this->strBuffer = null;
            $this->charBuffer = null;
        }
        

        public function nextToken() {
            $state = state::INIT;

            $nextState = null;
            $token = null;

            $this->clearStrBuffer();

            $isEOF = false;
            while($token === null) {
                $nextState = null;
                $currentCharacter = $this->getChar($isEOF);
            
                switch($state) {
                    case state::INIT:
                        if($isEOF) {
                            $nextState = state::EOF;
                        }
                        else if(preg_match('/[ \t]/', $currentCharacter)) {
                            $nextState = state::INIT;
                        }
                        else if(preg_match('/[#]/', $currentCharacter)) {
                            $nextState = state::COMMENT;
                        }
                        else if(preg_match('/[.]/', $currentCharacter)) {
                            $nextState = state::PROLOG;
                        }
                        else if(preg_match('/[a-z_\-$&%*!?]/i', $currentCharacter)) {
                            $nextState = state::DIRTY_TOKEN;
                        }
                        else if(preg_match('/[\r]/', $currentCharacter)) {
                            $nextState = state::WNEWLINE;
                        }
                        else if(preg_match('/[\n]/', $currentCharacter)) {
                            $nextState = state::NEWLINE;
                        }
                        else {
                            $token = $this->createToken(type::ERROR, $this->strBuffer);
                        }
                      
                        break;
                    case state::COMMENT:
                        if($isEOF) {
                            $nextState = state::EOF;
                        }
                        else if(preg_match('/[\r]/', $currentCharacter)) {
                            $nextState = state::WNEWLINE;
                        }
                        else if(preg_match('/[\n]/', $currentCharacter)) {
                            $nextState = state::NEWLINE;
                        }
                        else {
                            $nextState = state::COMMENT;
                        }

                        break;
                    case state::PROLOG:
                        if(!$isEOF && preg_match('/[a-z0-9_\-$&%*!?]/i', $currentCharacter)) {
                            $nextState = state::PROLOG;
                        }
                        else {
                            $this->toBuffer($currentCharacter);
                    
                            if(preg_match('/^'.Table::aToRegex(Table::PROLOG).'$/i', $this->strBuffer)) {
                                $token = $this->createToken(type::PROLOG, null);
                            }
                            else {
                                $token = $this->createToken(type::ERROR, $this->strBuffer);
                            }
                        }

                        break;
                    case state::WNEWLINE:
                        if(preg_match('/[\n]/', $currentCharacter)) {
                            $nextState = state::NEWLINE;
                        }

                        break;
                    case state::NEWLINE:
                        $this->cursorPosition['ROW']++;
                        $this->cursorPosition['COL'] = 1;

                        $this->toBuffer($currentCharacter);
                        $token = $this->createToken(type::NEWLINE, null);

                        break;
                    case state::DIRTY_TOKEN:
                        if(!$isEOF && !preg_match('/[\s\\#]/', $currentCharacter)) {
                            $nextState = state::DIRTY_TOKEN;
                        }
                        else {
                            $this->toBuffer($currentCharacter);

                            $type = $this->classifyToken($this->strBuffer);

                            $token = $this->createToken($type, $this->strBuffer);
                        }

                        break;

                    case state::EOF:
                        $token = $this->createToken(type::EOF, null);

                        break;
                }

                if($token === null) {
                    $this->strBuffer .= $currentCharacter;
                }

                if($state !== state::DIRTY_TOKEN && $nextState !== state::DIRTY_TOKEN) {
                    $this->clearStrBuffer();
                }

                if($nextState === null && $token === null) {
                    $token = $this->createToken(type::ERROR, $this->strBuffer);
                    break;
                }
                else {
                    $state = $nextState;
                }
            }

            return $token;
        }

        public function getCursorPosition() {
            return $this->cursorPosition;
        }

        private function getChar(&$isEOF) {
            if($this->charBuffer !== null) {
                $tmp = $this->charBuffer;
                $this->charBuffer = null;
                $isEOF = false;
                return $tmp;
            }
            else  {
                $fromInput = fgetc($this->inputStream);

                if($fromInput === false) {
                    $isEOF = true;
                    return null;
                }
                else {
                    $isEOF = false;
                    return $fromInput;
                }
            }
        }

        private function clearStrBuffer() {
            $this->cursorPosition['COL'] += strlen($this->strBuffer);

            $this->strBuffer = null;
        }

        private function toBuffer($character) {
            $this->charBuffer = $character;
        }

        private function isBufferEmpty() {
            return $this->charBuffer === null;
        }

        private function createToken($type, $value) {
            $token = new Token();
            $token->setType($type);
            $token->setVal($value);

            return $token;
        }

        private function classifyToken($inpString) {
            $type = null;

            $varName = '[a-zA-Z_\-$&%*!?][a-zA-Z_\-$&%*!?0-9]*';
            $var = '/^'.Table::aToRegex(Table::FRAME_CODES).'@'.$varName.'$/';

            $type = '/^'.Table::aToRegex(Table::TYPE_CODES).'$/';
            $literal = '/^'.Table::aToRegex(Table::TYPE_CODES).'@.*$/';
            $label = '/^[a-zA-Z_\-$&%*!?][a-zA-Z_\-$&%*!?0-9]*$/';


            if(Table::searchInTab(Table::OPERATION_CODES, $inpString)) {
                $type = type::OPCODE;
            }
            else if(preg_match($var, $inpString)) {
                $type = type::VARIABLE;
            }
            else if(preg_match($type, $inpString)) {
                $type = type::TYPE;
            }
            else if(preg_match($literal, $inpString)) {
                $type = $this->classifyLiteral($inpString);
            }
            else if(preg_match($label, $inpString)) {
                $type = type::LABEL;
            }
            else {
                $type = type::ERROR;
            }

            return $type;
        }

        private function classifyLiteral($inpString) {
            $type = null;

            $stringContent = '([^\x{0000}-\x{0020}\s\\\]|(\\\[0-9]{3}))*)';
            $string = '/^(string@'.$stringContent.'$/u';

            $int = '/^int@-?[0-9]+$/';
            $bool = '/^bool@(true|false)$/';
            $nil = '/^nil@nil$/';

            if(preg_match($int, $inpString)) {
                $type = type::INT;
            }
            else if(preg_match($bool, $inpString)) {
                $type = type::BOOL;
            }
            else if(preg_match($nil, $inpString)) {
                $type = type::NIL;
            }
            else if(preg_match($string, $inpString)) {
                $type = type::STR;
            }
            else {
                $type = type::ERROR;
            }

            return $type;
        }
    }
?>