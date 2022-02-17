<?php
    enum type {
        case OPCODE;
        case STR;
        case LITERAL;
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

    class Token {
        private $type = null;
        private $value = null;

        public function setType($newType) {
            $this->type = $newType;
        }

        public function setValue($newValue) {
            $this->value = $newValue;
        }

        public function getType() {
            return $this->type;
        }

        public function getValue() {
            return $this->value;
        }
    }
?>