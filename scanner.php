<?php
    /**************************************************************************
     *                                  IPP project                           *  
     *                                  scanner.php                           *
     *                                                                        *
     *                                 Vojtech Dvorak                         *
     *                                 February 2022                          *
     *************************************************************************/

    require_once 'tables.php';

    enum type {
        case OPCODE;
        case STR;
        case BOOL;
        case INT;
        case NIL;
        case TYPE;
        case LABEL;
        case VARIABLE;
        case PROLOG;
        case ERROR;
        case EOF;
        case NEWLINE;
    }

    /**
     * Token class
     */
    class Token {
        /**
         * @var type $type Specifies type of token
         */
        private $type = null;

        /**
         * @var String $value Contains value of token (with prefixes) 
         */
        private $value = null;

        /**
         * Type setter
         * @param type $newType New type of token
         */
        public function setType($newType) {
            $this->type = $newType;
        }

        /**
         * Value setter
         * @param String $newValue New value of token
         */
        public function setVal($newValue) {
            $this->value = $newValue;
        }

        /**
         * Type getter
         */
        public function getType() {
            return $this->type;
        }

        /**
         * Value getter
         */
        public function getVal() {
            if($this->type == type::EOF) {
                return 'EOF';
            }
            else if($this->type == type::NEWLINE) {
                return 'NEWLINE';
            }
            else {
                return $this->value;
            }
        }

        /**
         * Returns value of token without prefixes
         * @return String Value of token without prefixes such as frame or data type
         */
        public function getPurifiedVal() {
            $tokenValue = $this->getVal();

            $argValue = null;
            if(strpos($tokenValue, '@') !== false) {
                $argValue = substr($tokenValue, strpos($tokenValue, '@') + 1);
            }
            else {
                $argValue = $tokenValue;
            }

            return $argValue;
        }

    }

    /**
     * Reads one token from input (it can be also EOF, newline or error token)
     * and determines its type
     * 
     * Because it interacts with input, there can be only one scanner (implemented as singleton) to
     * make input consitent
     */
    class Scanner {

        private static $inst = null;

        /**
         * @var Resource $inputStream Input with source code
         */
        private $inputStream;

        /**
         * @var Array $cursorPosition Position of cursor (associative array with 2 values - ROW, COL)
         */
        private $cursorPosition;

        /**
         * @var Char $charBuffer Buffer for one character waiting for accepting
         */
        private $charBuffer;

        /**
         * @var String $strBuffer Contains value of currently processed token
         */
        private $strBuffer;

        /**
         * @var Char $curChar Character that is currently processed
         */
        private $curChar;

        /**
         * @var Boolean $foundEOF Value signalizing that EOF was reached
         */
        private $foundEOF;

        /**
         * @var Array2D Twodimensional array with types of tokens, that are expected (used 
         * for context parsing)
         */
        private $expected;

        /**
         * Scanner constructor
         * @param Resource $input Input file where can be source code read
         */
        private function __construct($input) {
            $this->inputStream = $input;
            $this->cursorPosition = array(
                'ROW' => 1,
                'COL' => 1
            );

            $this->expected = array();
            $this->strBuffer = null;
            $this->charBuffer = null;
        }

        /**
         * Constructor of singleton instance of Scanner
         * @var Resource $input 
         */
        public static function instantiate($input) {
            if(Scanner::$inst === null) {
                return new Scanner($input);
            }
            else {
                return Scanner::$inst;
            }
        }

        /**
         * Returns position of cursor in input
         * @return Array asociative array with two values (first is 'ROW', second is 'COL')
         */
        public function getCursorPosition() {
            return $this->cursorPosition;
        }

        /**
         * Sets cursor position due to arguments
         * @param CharOrInteger $row if it is +/- it increments/decrements current row, 
         *                      if it is integer it sets it to this its value
         * @param CharOrInteger $col if it is +/- it increments/decrements current col, 
         *                      if it is integer it sets it to this integer
         */
        public function setCursorPosition($row, $col) {
            if($row === '+') {
                $this->cursorPosition['ROW']++;
            }
            else if($row === '-') {
                $this->cursorPosition['ROW']--;
            }
            else if(gettype($row) === 'integer') {
                $this->cursorPosition['ROW'] = $row;
            }

            if($col === '+') {
                $this->cursorPosition['COL']++;
            }
            else if($row === '-') {
                $this->cursorPosition['COL']--;
            }
            else if(gettype($col) === 'integer') {
                $this->cursorPosition['COL'] = $col;
            }
        }

        /**
         * Returns value of foundEOF flag
         */
        public function wasEOFFound() {
            return $this->foundEOF;
        }

        /**
         * Returns current processed character
         */
        public function getCurChar() {
            return $this->curChar;
        }

        /**
         * Returns current processed string by FSM
         */
        public function getStr() {
            return $this->strBuffer;
        }

        
        /**
         * Reads one token from input and returns it
         * @return Token Token from input
         */
        public function nextToken() {
            $possibleTypes = array();
            $this->clearStrBuffer();

            $this->foundEOF = false;

            FSM::reset();
            while(empty($possibleTypes)) {
                $this->readNextChar();

                //Found corresponding transition function and process char with it
                FSM::doTransition($this, $possibleTypes);

                //If type was not recognized, save current character to string buffer
                if(empty($possibleTypes)) {
                    $this->strBuffer .= $this->curChar;
                }

                if(FSM::getState() !== state::DIRTY_TOKEN && 
                   FSM::getNextState() !== state::DIRTY_TOKEN &&
                   !in_array(state::DIRTY_TOKEN, $possibleTypes, true)) {

                    $this->clearStrBuffer();
                }

                //Age next state (if it is given)
                if(FSM::getNextState() === null && empty($possibleTypes)) {
                    array_push($possibleTypes, type::ERROR);
                    break;
                }
                else {
                    FSM::ageState();
                }
            }

            $token = $this->createToken($possibleTypes, $this->strBuffer);
            $this->lastToken = $token;

            return $token;
        }

        //Clears buffer with currently processed string (token)
        public function clearStrBuffer() {
            $this->cursorPosition['COL'] += strlen($this->strBuffer);

            $this->strBuffer = null;
        }

        /**
         * Saves given character to buffer
         * @param Char $character character to be saved 
         */
        public function toBuffer($character) {
            $this->charBuffer = $character;
        }

        /**
         * Checks whether character buffer is empty or not
         * @return Bool True if character buffer is empty
         */
        public function isBufferEmpty() {
            return $this->charBuffer === null;
        }

        /**
         * Reads next char from input or from buffer and updates 
         * processed character with it
         */
        private function readNextChar() {
            //EOF was found before
            if($this->foundEOF) { 
                $this->foundEOF = true;
                $this->curChar = null;
                return;
            }

            //There is character in buffer
            if($this->charBuffer !== null) { 
                $this->curChar = $this->charBuffer;
                $this->charBuffer = null;
                $this->foundEOF = false;
            }
            else  { 
                $fromInput = fgetc($this->inputStream);

                if($fromInput === false) {
                    $this->foundEOF = true;
                    $this->curChar = null;
                }
                else {
                    $this->foundEOF = false;
                    $this->curChar = $fromInput;
                }
            }
        }

        /**
         * Creates token and initializes due to lexical analysis
         * @param Array $possibleTypes Array with expecting types
         * @param String $value Value of token
         * @return Token Newly created token object
         */
        private function createToken($possibleTypes, $value) {
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

            $token = new Token();
            $token->setType($type);
            $token->setVal($value);

            return $token;
        }

        /**
         * Updates array with expected types
         * @param Token $token Currently returned token
         */
        private function updateExp($type) {
            if($type === type::OPCODE) {
                $succesors = Table::OPERATION_CODES[$value];
                
                $expected = array();
                for($i = 0; $i < strlen($succesors); $i++) {
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
        }
    }

?>