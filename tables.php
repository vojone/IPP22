<?php

    class Table {
        public const PROLOG = array(
            '.IPPcode22'
        );

        public const OPERATION_CODES = array(
            'MOVE' => 'vs', 'CREATEFRAME' => '', 'PUSHFRAME' => '',
            'POPFRAME' => '', 'DEFVAR' => 'v', 'CALL' => 'l', 
            'RETURN' => '', 'PUSHS' => 's', 'POPS' => 'v', 
            'ADD' => 'vss', 'SUB' => 'vss', 'MUL' => 'vss', 
            'IDIV' => 'vss', 'LT' => 'vss', 'GT' => 'vss', 
            'EQ' => 'vss', 'AND' => 'vss', 'OR' => 'vss', 
            'NOT' => 'vss', 'INT2CHAR' => 'vs', 'STRI2INT' => 'vss', 
            'READ' => 'vt', 'WRITE' => 's', 'CONCAT' => 'vss', 
            'STRLEN' => 'vs', 'GETCHAR' => 'vss', 'SETCHAR' => 'vss', 
            'TYPE' => 'vs', 'LABEL' => 'l', 'JUMP' => 'l', 
            'JUMPIFEQ' => 'lss', 'JUMPIFNEQ' => 'lss', 'EXIT' => 's', 
            'DPRINT' => 's', 'BREAK' => ''
        );

        public const FRAME_CODES = array(
            'LF', 'GF', 'TF'
        );

        public const TYPE_CODES = array(
            'string', 'int', 'nil', 'bool'
        );

        public static function searchInTab($tab, $str) {
            return array_key_exists($str, $tab);
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