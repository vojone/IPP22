<?php
    /**************************************************************************
     *                                  IPP project                           *  
     *                                  scanner.php                           *
     *                                                                        *
     *                                 Vojtech Dvorak                         *
     *                                 February 2022                          *
     *************************************************************************/

    require_once 'token.php';
    require_once 'tables.php';

    /**
     * 
     */
    enum state {
        case INIT;
        case COMMENT;
        case NEWLINE;
        case WNEWLINE;
        case PROLOG;
        case DIRTY_TOKEN;
        case EOF;
    }

    /**
     * 
     */
    class Scanner {
        private $inputStream;

        private $cursorPosition;

        private $charBuffer;

        private $strBuffer;

        private $expected;

        function __construct($input) {
            $this->inputStream = $input;
            $this->cursorPosition = array(
                'ROW' => 1,
                'COL' => 1
            );

            $this->expected = array();
            $this->strBuffer = null;
            $this->charBuffer = null;
        }
        

        public function nextToken() {
            $state = state::INIT;

            $nextState = null;
            $possibleTypes = array();

            $this->clearStrBuffer();

            $isEOF = false;
            while(empty($possibleTypes)) {
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
                            array_push($possibleTypes, type::ERROR);
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

                            Table::isProlog($possibleTypes, $this->strBuffer);
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
                        array_push($possibleTypes, type::NEWLINE);

                        break;
                    case state::DIRTY_TOKEN:
                        if(!$isEOF && !preg_match('/[\s\\#]/', $currentCharacter)) {
                            $nextState = state::DIRTY_TOKEN;
                        }
                        else {
                            $this->toBuffer($currentCharacter);

                            Table::classifyToken($possibleTypes, $this->strBuffer);
                        }

                        break;

                    case state::EOF:
                        array_push($possibleTypes, type::EOF);

                        break;
                }

                if(empty($possibleTypes)) {
                    $this->strBuffer .= $currentCharacter;
                }

                if($state !== state::DIRTY_TOKEN && 
                   $nextState !== state::DIRTY_TOKEN &&
                   !in_array(state::DIRTY_TOKEN, $possibleTypes, true)) {

                    $this->clearStrBuffer();
                }

                if($nextState === null && empty($possibleTypes)) {
                    array_push($possibleTypes, type::ERROR);
                    break;
                }
                else {
                    $state = $nextState;
                }
            }

            $token = $this->createToken($possibleTypes, $this->strBuffer);

            $this->lastToken = $token;

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

        private function createToken($possibleTypes, $value) {
            $token = new Token();
            $token->setVal($value);

            $type = null;
            if(in_array(type::ERROR, $possibleTypes)) {
                $type = type::ERROR;
            }
            else if(empty($possibleTypes)) {
                $type = type::ERROR;
            }
            else if(count($possibleTypes) === 1) {
                $type = $possibleTypes[0];
            }
            else if(!empty($this->expected)) {
                foreach($this->expected[0] as $curExpType) {
                    if(in_array($curExpType, $possibleTypes, true)) {
                        $type = $curExpType;
                        break;
                    }
                }

                if($type === null) {
                    $type = $possibleTypes[0];
                }
    
                $this->expected = array_shift($this->expected);
            }
            else {
                $type = $possibleTypes[0];
            }

            if($type === type::OPCODE) {
                $succesors = Table::OPERATION_CODES[$value];
                
                $expected = array();
                for($i = 0;$i < strlen($succesors); $i++) {
                    $succesorType = Table::charToTypes($succesors[$i]);
                    array_push($this->expected, $succesorType);
                }
            }
            else if($type === type::NEWLINE) {
                $this->expected = array(array(type::OPCODE));
            }
            else {
                $this->expected = array();
            }

            $token->setType($type);

            return $token;
        }
    }
?>