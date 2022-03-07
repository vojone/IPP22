<?php
    /**************************************************************************
     *                                  IPP project                           *  
     *                                   tables.php                           *
     *  Contains statical classes defining lexical aspects of source language *
     *                                                                        *
     *                            Vojtech Dvorak (xdvora3o)                   *
     *                                   March 2022                           *
     *************************************************************************/

    /**
     * Contains majority of important elements of source language (tables with 
     * keywords, conversion methods...)
     */
    class Table {
        /**
         * @var Array PROLOG Contains all possible headers
         */
        public const PROLOG = array(
            '.IPPcode22'
        );

        /**
         * @var Array OPERATION_DOES Contains valid operaton codes (as keys) ¨
         * and associated strings, that specifies their arguments
         */
        public const OPERATION_CODES = array(
            'MOVE' => 'v&', 'CREATEFRAME' => '', 'PUSHFRAME' => '',
            'POPFRAME' => '', 'DEFVAR' => 'v', 'CALL' => 'l', 
            'RETURN' => '', 'PUSHS' => '&', 'POPS' => 'v', 
            'ADD' => 'v&&', 'SUB' => 'v&&', 'MUL' => 'v&&', 
            'IDIV' => 'v&&', 'LT' => 'v&&', 'GT' => 'v&&', 
            'EQ' => 'v&&', 'AND' => 'v&&', 'OR' => 'v&&', 
            'NOT' => 'v&', 'INT2CHAR' => 'v&', 'STRI2INT' => 'v&&', 
            'READ' => 'vt', 'WRITE' => '&', 'CONCAT' => 'v&&', 
            'STRLEN' => 'v&', 'GETCHAR' => 'v&&', 'SETCHAR' => 'v&&', 
            'TYPE' => 'v&', 'LABEL' => 'l', 'JUMP' => 'l', 
            'JUMPIFEQ' => 'l&&', 'JUMPIFNEQ' => 'l&&', 'EXIT' => '&', 
            'DPRINT' => '&', 'BREAK' => ''
        );

        /**
         * @var Array Contains all opcodes from OPERATION_CODES of operations
         * performing jumps (it is important for statistics)
         */
        public const JUMP_OP = array(
            'JUMP', 'JUMPIFEQ', 'JUMPIFNEQ'
        );

        /**
         * @var Array Contains all opcodes from OPERATION_CODES of operations
         * that performs function calls or returns from functions
         */
        public const FUNC_OP = array(
            'CALL', 'RETURN' 
        );

        /**
         * @var Array Constant contaning all possible frame codes
         */
        public const FRAME_CODES = array(
            'LF', 'GF', 'TF'
        );

        /**
         * @var Array Data types
         */
        public const TYPE_CODES = array(
            'string', 'int', 'nil', 'bool'
        );

        /**
         * Provides type to string conversion for printing XML
         * @param type $type Type of token thaht should be converted
         */
        public static function typeToStr($type) {
            switch($type) {
                case type::STR:
                    return 'string';
                case type::INT:
                    return 'int';
                case type::BOOL:
                    return 'bool';
                case type::NIL:
                    return 'nil';
                case type::TYPE:
                    return 'type';
                case type::LABEL:
                    return 'label';
                case type::VARIABLE:
                    return 'var';
            }
        }

        /**
         * Converts type to string to be understood by user
         */
        public static function UITypeToStr($type) {
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
                    return 'type specifier';
                case type::LABEL:
                    return 'label';
                case type::VARIABLE:
                    return 'variable';
            }
        }

        /**
         * Converts type specifier from table of op. codes to array of token types 
         * @param Char $char character to be converted
         */
        public static function charToTypes($char) {
            switch($char) {
                case '&':
                    return array(
                        type::VARIABLE, type::STR, 
                        type::INT, type::BOOL, 
                        type::NIL
                    );
                case 'v':
                    return array(type::VARIABLE);
                case 'l':
                    return array(type::LABEL);
                case 't':
                    return array(type::TYPE);
            }
        }


        /**
         * Check if given string is prolog or not
         * @param Array $possibleTypes output parameter, array, that will be filled with all types that inpString represents
         * @param String $inpString string that should be classified
         * @param Array Array with token types that can input string represents
         */
        public static function isProlog(&$possibleTypes, $inpString) {
            $prolog = '/^'.Table::aToRegex(Table::PROLOG).'$/i';
            if(preg_match($prolog, $inpString)) {
                array_push($possibleTypes, type::PROLOG);
            }
            else {
                array_push($possibleTypes, type::ERROR);
            }

            return $possibleTypes;
        }


        /**
         * Classifies type of token to string (got by e. g. scanner) 
         * @param Array $possibleTypes output parameter, array, that will be filled with all types that inpString represents
         * @param String $inpString string that should be classified
         * @param Array Array with token types that can input string represents
         */
        public static function classifyToken(&$possibleTypes, $inpString) {
            $varName = '[a-zA-Z_\-$&%\*!?][a-zA-Z_\-$&%\*!?0-9]*';
            $var = '/^'.Table::aToRegex(Table::FRAME_CODES).'@'.$varName.'$/';

            $type = '/^'.Table::aToRegex(Table::TYPE_CODES).'$/';
            $literal = '/^'.Table::aToRegex(Table::TYPE_CODES).'@.*$/';
            $label = '/^[a-zA-Z_\-$&%\*!?][a-zA-Z_\-$&%\*!?0-9]*$/';

            if(Table::searchInstr(Table::OPERATION_CODES, $inpString)) {
                array_push($possibleTypes, type::OPCODE);
            }

            if(preg_match($var, $inpString)) {
                array_push($possibleTypes, type::VARIABLE);
            }

            if(preg_match($type, $inpString) ) {
                array_push($possibleTypes, type::TYPE);
            }

            if(preg_match($literal, $inpString)) {
                Table::classifyLiteral($possibleTypes, $inpString);
            }

            if(preg_match($label, $inpString)) {
                array_push($possibleTypes, type::LABEL);
            }

            if(empty($possibleTypes)) {
                array_push($possibleTypes, type::ERROR);
            }

            return $possibleTypes;
        }

        /**
         * Assign type of token to string as literal (got by e. g. scanner) 
         * @param Array $possibleTypes output parameter, array, that will be filled with all types that inpString represents
         * @param Strin $inpString string that should be classified
         * @param Array Array with token types that can input string represents
         */
        public static function classifyLiteral(&$possibleTypes, $inpString) {
            $stringContent = '([^\x{0000}-\x{0020}\s\\\]|(\\\[0-9]{3}))*';
            $string = '/^(string@('.$stringContent.')|nil)$/u';

            //Supports also _ in integer for better format of long numbers
            //             sign?    decimal format   |  octal numbers           |              hexadecimal            | 0 | nil
            $int = '/^int@[-\+]?(([1-9]((_)?[0-9]+)*)|(0[oO]?[0-7]((_)?[0-7]+)*)|(0[xX][0-9A-Fa-f]((_)?[0-9A-Fa-f]+)*)|(0)|(nil))$/';

            $bool = '/^bool@(true|false|nil)$/';
            $nil = '/^nil@nil$/';

            if(preg_match($int, $inpString)) {
                array_push($possibleTypes, type::INT);
            }
            else if(preg_match($bool, $inpString)) {
                array_push($possibleTypes, type::BOOL);
            }
            else if(preg_match($nil, $inpString)) {
                array_push($possibleTypes, type::NIL);
            }
            if(preg_match($string, $inpString)) {
                array_push($possibleTypes, type::STR);
            }

            return $possibleTypes;
        }

        /**
         * Function that can be used for searching in table with instructions 
         * (case insensitive in contrast to array_key_exist function)
         * @param Array $tab Haystack
         * @param String $str Needle
         * @param Bool $keys If it is true searching is performed in keys in the array
         * @return Bool True if there is occurence of $str in $tab
         */
        public static function searchInstr($tab, $str, $keys = true) {
            foreach($tab as $key => $element) {
                if(strtoupper($str) === strtoupper($key) && $keys) {
                    return true;
                }
                else if(strtoupper($str) === strtoupper($element) && !$keys) {
                    return true;
                }
            }

            return false;
        }

        /**
         * Converts array with strings to regex representation
         * Example: array('string', 'bool', 'int') -> '(string|bool|int)'
         * @param Array $arr array that whould be converted
         */
        public static function aToRegex($arr) {
            $regex = '(';

            foreach ($arr as $str) {
                if($regex !== '(') {
                    $regex .= '|';
                }

                $regex .= $str;
            }

            $regex .= ')';

            return $regex;
        }
    }

    
    /**
     * Additional enum type for states of FSM
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
     * FSM classifying tokens controlled by scanner object
     */
    class FSM {

        /**
         * @var state $state Current state of FSM
         */
        private static $state = state::INIT;

        /**
         * @var state $state Next state of FSM (can be se)
         */
        private static $nextState = null;

        /**
         * Puts FSM to default state
         */
        public static function reset() {
            FSM::$state = state::INIT;
            FSM::$nextState = null;
        }

        /**
         * Moves value from next state to (current) state and clears next state
         */
        public static function ageState() {
            FSM::$state = FSM::$nextState;
            FSM::$nextState = null;
        }

        public static function getNextState() {
            return FSM::$nextState;
        }

        public static function getState() {
            return FSM::$state;
        }

        /**
         * Initial function of every FSM run, selects transition function due to current state
         * @param Scanner $scanner Scanner object that calls (and controls) FSM 
         * @param Array $possibleTypes array that will be filled with all types, that current token can have
         */
        public static function doTransition($scanner, &$possibleTypes) {
            switch(FSM::$state) {
                case state::INIT:
                    FSM::INITtr($scanner, $possibleTypes);
                    break;
                case state::COMMENT:
                    FSM::COMMENTtr($scanner, $possibleTypes);
                    break;
                case state::PROLOG:
                    FSM::PROLOGtr($scanner, $possibleTypes);
                    break;
                case state::WNEWLINE:
                    FSM::WNEWLINEtr($scanner, $possibleTypes);
                    break;
                case state::NEWLINE:
                    FSM::NEWLINEtr($scanner, $possibleTypes);
                    break;
                case state::DIRTY_TOKEN:
                    FSM::DIRTY_TOKENtr($scanner, $possibleTypes);
                    break;
                case state::EOF:
                    FSM::EOFtr($scanner, $possibleTypes);
                    break;
            }
        }

        //---------------------FSM Transition functions------------------------


        public static function INITtr($scanner, &$possibleTypes) {
            $currentChar = $scanner->getCurChar();

            if($scanner->wasEOFFound()) {
                FSM::$nextState = state::EOF;
            }
            else if(preg_match('/[ \t]/', $currentChar)) {
                FSM::$nextState = state::INIT;
            }
            else if(preg_match('/[#]/', $currentChar)) {
                $scanner->statCollector->incStats('comments');

                FSM::$nextState = state::COMMENT;
            }
            else if(preg_match('/[.]/', $currentChar)) {
                $scanner->clearStrBuffer();
                FSM::$nextState = state::PROLOG;
            }
            else if(preg_match('/[a-z_\-+$&%*!?]/i', $currentChar)) {
                $scanner->clearStrBuffer();
                FSM::$nextState = state::DIRTY_TOKEN;
            }
            else if(preg_match('/[\r]/', $currentChar)) {
                FSM::$nextState = state::WNEWLINE;
            }
            else if(preg_match('/[\n]/', $currentChar)) {
                FSM::$nextState = state::NEWLINE;
            }
            else {
                array_push($possibleTypes, type::ERROR);
            }
        }

        public static function COMMENTtr($scanner, &$possibleTypes) {
            if($scanner->wasEOFFound()) {
                FSM::$nextState = state::EOF;
            }
            else if(preg_match('/[\r]/', $scanner->getCurChar())) {
                FSM::$nextState = state::WNEWLINE;
            }
            else if(preg_match('/[\n]/', $scanner->getCurChar())) {
                FSM::$nextState = state::NEWLINE;
            }
            else {
                FSM::$nextState = state::COMMENT;
            }
        }

        public static function NEWLINEtr($scanner, &$possibleTypes) {
            $scanner->setCursorPosition('+', 1);

            $currentChar = $scanner->getCurChar();
            $scanner->toBuffer($currentChar);
            array_push($possibleTypes, type::NEWLINE);
        }

        public static function WNEWLINEtr($scanner, &$possibleTypes) {
            if(preg_match('/[\n]/', $scanner->getCurChar())) {
                FSM::$nextState = state::NEWLINE;
            }
        }

        public static function PROLOGtr($scanner, &$possibleTypes) {
            if(!$scanner->wasEOFFound() && 
                preg_match('/[a-z0-9_\-$&%*!?]/i', $scanner->getCurChar())) {

                FSM::$nextState = state::PROLOG;
            }
            else {
                $scanner->toBuffer($scanner->getCurChar());

                Table::isProlog($possibleTypes, $scanner->getStr());
            }
        }

        public static function DIRTY_TOKENtr($scanner, &$possibleTypes) {
            if(!$scanner->wasEOFFound() && 
               !preg_match('/[\s\\#]/', $scanner->getCurChar())) {

                FSM::$nextState = state::DIRTY_TOKEN;
            }
            else {
                $currentChar = $scanner->getCurChar();
                $scanner->toBuffer($currentChar);

                Table::classifyToken($possibleTypes, $scanner->getStr());
            }
        }

        public static function EOFtr($scanner, &$possibleTypes) {
            array_push($possibleTypes, type::EOF);
        }

        //---------------------------------------------------------------------
    }

?>