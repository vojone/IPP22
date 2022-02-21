<?php
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

    class Token {
        private $type = null;
        private $value = null;

        public function setType($newType) {
            $this->type = $newType;
        }

        public function setVal($newValue) {
            $this->value = $newValue;
        }

        public function getType() {
            return $this->type;
        }

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

    }
?>