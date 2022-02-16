<?php

    class Table {
        private const PROLOG = array(
            '.IPPcode22'
        );

        private const OPERATION_CODES = array(
            'MOVE', 'CREATEFRAME', 'PUSHFRAME', 'POPFRAME', 'DEFVAR', 
            'CALL', 'RETURN', 'PUSHS', 'POPS', 'ADD', 'SUB', 'MUL', 
            'IDIV', 'LT', 'GT', 'EQ', 'AND', 'OR', 'NOT', 'INT2CHAR', 
            'STRI2INT', 'READ', 'WRITE', 'CONCAT', 'STRLEN', 'GETCHAR', 
            'SETCHAR', 'TYPE', 'LABEL', 'JUMP', 'JUMPIFEQ', 'JUMPIFNEQ', 
            'EXIT', 'DPRINT', 'BREAK'
        );

        private const FRAME_CODES = array(
            'LF', 'GF', 'TF'
        );

        private const TYPE_CODES = array(
            'string', 'int', 'nil' 
        );

        public static function isOpcode($str) {
            foreach(self::OPERATION_CODES as $opCode) {
                if(strtolower($opCode) === strtolower($str)) return true;
            }

            return false;
        }

        public static function isVariablePrefix($str) {
            foreach(self::FRAME_CODES as $frameCode) {
                if(strtolower($frameCode) === strtolower($str)) return true;
            }

            return false;
        }

        public static function isLiteralPrefix($str, &$result) {
            foreach(self::TYPE_CODES as $type) {
                if(strtolower($type) === strtolower($str)) {
                    $result = $type;

                    return true;
                }
            }

            return false;
        }

    }

?>