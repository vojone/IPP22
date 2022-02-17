<?php

    class Table {
        public const PROLOG = array(
            '.IPPcode22'
        );

        public const OPERATION_CODES = array(
            'MOVE', 'CREATEFRAME', 'PUSHFRAME', 'POPFRAME', 'DEFVAR', 
            'CALL', 'RETURN', 'PUSHS', 'POPS', 'ADD', 'SUB', 'MUL', 
            'IDIV', 'LT', 'GT', 'EQ', 'AND', 'OR', 'NOT', 'INT2CHAR', 
            'STRI2INT', 'READ', 'WRITE', 'CONCAT', 'STRLEN', 'GETCHAR', 
            'SETCHAR', 'TYPE', 'LABEL', 'JUMP', 'JUMPIFEQ', 'JUMPIFNEQ', 
            'EXIT', 'DPRINT', 'BREAK'
        );

        public const FRAME_CODES = array(
            'LF', 'GF', 'TF'
        );

        public const TYPE_CODES = array(
            'string', 'int', 'nil', 'bool'
        );

        public static function searchInTab($tab, $str) {
            return in_array($str, $tab);
        }

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