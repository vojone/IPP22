<?php
    require_once 'token.php';
    require_once 'tables.php';

    enum state {
        case INIT;
        case COMMENT;
        case NEWLINE;
        case PROLOG;
        case DIRTY_TOKEN;
        case EOF;
    }

    class Scanner {
        private $inputStream = null;

        private $cursorPosition = array(0, 0);

        private $charBuffer = null;

        function __construct($input) {
            $this->inputStream = $input;
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

        private function toBuffer($character) {
            $this->charBuffer = $character;
        }

        private function isBufferEmpty() {
            return $this->charBuffer === null;
        }

        private function createToken($type, $value) {
            $token = new Token();
            $token->setType($type);
            $token->setValue($value);

            return $token;
        }

        public function nextToken() {
            $state = state::INIT;

            $nextState = null;
            $token = null;
            $inpString = null;
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
                        else if(preg_match('/[\n]/', $currentCharacter)) {
                            $nextState = state::NEWLINE;
                        }
                        else {
                            $token = $this->createToken(type::ERROR, null);
                        }
                      
                        break;
                    case state::COMMENT:
                        if(preg_match('/[\n]/', $currentCharacter)) {
                            $nextState = state::NEWLINE;
                        }
                        else if(!$isEOF) {
                            $nextState = state::COMMENT;
                        }

                        break;
                    case state::PROLOG:
                        if(!$isEOF && preg_match('/[a-z0-9_\-$&%*!?]/i', $currentCharacter)) {
                            $nextState = state::PROLOG;
                        }
                        else {
                            $this->toBuffer($currentCharacter);
                    
                            if(preg_match('/^'.Table::aToRegex(Table::PROLOG).'$/i', $inpString)) {
                                $token = $this->createToken(type::PROLOG, null);
                            }
                            else {
                                $token = $this->createToken(type::ERROR, null);
                            }
                        }

                        break;
                    case state::NEWLINE:
                        $this->toBuffer($currentCharacter);
                        $token = $this->createToken(type::NEWLINE, null);

                        break;
                    case state::DIRTY_TOKEN:
                        if(!$isEOF && preg_match('/[a-z_\-$&%*!?@0-9]/i', $currentCharacter)) {
                            $nextState = state::DIRTY_TOKEN;
                        }
                        else {
                            $this->toBuffer($currentCharacter);

                            $type = null;

                            var_dump('/'.Table::aToRegex(Table::TYPE_CODES).'/');
                            var_dump(preg_match('/'.Table::aToRegex(Table::TYPE_CODES).'/', $inpString));
                            if(Table::searchInTab(Table::OPERATION_CODES, $inpString)) {
                                $type = type::OPCODE;
                            }
                            else if(preg_match('/^'.Table::aToRegex(Table::FRAME_CODES).'@[a-zA-Z_\-$&%*!?][a-zA-Z_\-$&%*!?0-9]*$/', $inpString)) {
                                $type = type::VARIABLE;
                            }
                            else if(preg_match('/^'.Table::aToRegex(Table::TYPE_CODES).'$/', $inpString)) {
                                $type = type::TYPE;
                            }
                            else if(preg_match('/^'.Table::aToRegex(Table::TYPE_CODES).'@.*$/', $inpString)) {
                                if(preg_match('/^int@-?[0-9]+$/', $inpString)) {
                                    $type = type::LITERAL;
                                }
                                else if(preg_match('/^bool@(true|false)$/', $inpString)) {
                                    $type = type::LITERAL;
                                }
                                else if(preg_match('/^nil@nil$/', $inpString)) {
                                    $type = type::LITERAL;
                                }
                                else if('/^string@([^\u0000-\u0020\s\\]|(\\[0-9]{3}))*$/u') {
                                    $type = type::STR;
                                }
                                else {
                                    $type = type::ERROR;
                                }
                            }
                            else if(preg_match('/^[a-zA-Z_\-$&%*!?][a-zA-Z_\-$&%*!?0-9]*$/', $inpString)) {
                                $type = type::LABEL;
                            }
                            else {
                                $type = type::ERROR;
                            }

                            $token = $this->createToken($type, $inpString);
                        }

                        break;

                    case state::EOF:
                        $token = $this->createToken(type::EOF, null);

                        break;
                }

                if($nextState === null && $token === null) {
                    $token = $this->createToken(type::ERROR, null);
                    break;
                }
                else {
                    $state = $nextState;
                }

                if($state === state::DIRTY_TOKEN) {
                    $inpString .= $currentCharacter;
                }
            }

            return $token;
        } 
    }
?>