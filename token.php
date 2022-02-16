<?php
    enum type {
        case OPCODE;
        case STR;
        case IDENTIFIER;
        case NEWLINE;
        case PROLOG;
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