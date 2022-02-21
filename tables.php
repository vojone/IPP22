<?php
    /**************************************************************************
     *                                  IPP project                           *  
     *                                   tables.php                           *
     *                                                                        *
     *                                 Vojtech Dvorak                         *
     *                                 February 2022                          *
     *************************************************************************/

    /**
     * Contains majority of important elements of source language (tables with 
     * keywords, conversion methods...)
     */
    class Table {
        /**
         * Contains all possible headers
         */
        public const PROLOG = array(
            '.IPPcode22'
        );

        /**
         * Contains valid operaton codes (as keys) and associated strings, that 
         * specifies their arguments
         */
        public const OPERATION_CODES = array(
            'MOVE' => 'v&', 'CREATEFRAME' => '', 'PU&HFRAME' => '',
            'POPFRAME' => '', 'DEFVAR' => 'v', 'CALL' => 'l', 
            'RETURN' => '', 'PUSHS' => '&', 'POPS' => 'v', 
            'ADD' => 'v&&', 'SUB' => 'v&&', 'MUL' => 'v&&', 
            'IDIV' => 'v&&', 'LT' => 'v&&', 'GT' => 'v&&', 
            'EQ' => 'v&&', 'AND' => 'v&&', 'OR' => 'v&&', 
            'NOT' => 'v&&', 'INT2CHAR' => 'v&', 'STRI2INT' => 'v&&', 
            'READ' => 'vt', 'WRITE' => '&', 'CONCAT' => 'v&&', 
            'STRLEN' => 'v&', 'GETCHAR' => 'v&&', 'SETCHAR' => 'v&&', 
            'TYPE' => 'v&', 'LABEL' => 'l', 'JUMP' => 'l', 
            'JUMPIFEQ' => 'l&&', 'JUMPIFNEQ' => 'l&&', 'EXIT' => '&', 
            'DPRINT' => '&', 'BREAK' => ''
        );

        public const FRAME_CODES = array(
            'LF', 'GF', 'TF'
        );

        /**
         * Data types
         */
        public const TYPE_CODES = array(
            'string', 'int', 'nil', 'bool'
        );

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
                    return 'type';
                case type::LABEL:
                    return 'label';
                case type::VARIABLE:
                    return 'var';
            }
        }

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

        public static function UICharToStr($char) {
            switch($char) {
                case '&':
                    return "variable or constant";
                case 'v':
                    return "variable";
                case 'l':
                    return "label";
                case 't':
                    return "type specifier";
            }
        }

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
         */
        public static function classifyToken(&$possibleTypes, $inpString) {
            $varName = '[a-zA-Z_\-$&%*!?][a-zA-Z_\-$&%*!?0-9]*';
            $var = '/^'.Table::aToRegex(Table::FRAME_CODES).'@'.$varName.'$/';

            $type = '/^'.Table::aToRegex(Table::TYPE_CODES).'$/';
            $literal = '/^'.Table::aToRegex(Table::TYPE_CODES).'@.*$/';
            $label = '/^[a-zA-Z_\-$&%*!?][a-zA-Z_\-$&%*!?0-9]*$/';


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
         */
        public static function classifyLiteral(&$possibleTypes, $inpString) {
            $stringContent = '([^\x{0000}-\x{0020}\s\\\]|(\\\[0-9]{3}))*';
            $string = '/^(string@('.$stringContent.')|nil)$/u';

            $int = '/^int@((-?[0-9]+)|(nil))$/';
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
         * @param Array $tab Haystack
         * @param String $str Needle
         */
        public static function searchInstr($tab, $str) {
            foreach($tab as $key => $element) {
                if(strtoupper($str) === strtoupper($key)) {
                    return true;
                }
            }

            return false;
        }

        /**
         * Converts array with strings to regex representation
         * Example: array('string', 'bool', 'int') -> '(string|bool|int)'
         */
        public static function aToRegex($arr) {
            $regex = '(';

            foreach ($arr as $str) {
                if($regex !== '') {
                    $regex .= '|';
                }

                $regex .= $str;
            }

            $regex .= ')';

            return $regex;
        }
    }

?>