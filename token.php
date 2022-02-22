<?php
    /**************************************************************************
     *                                  IPP project                           *  
     *                                   token.php                            *
     *                                                                        *
     *                                 Vojtech Dvorak                         *
     *                                 February 2022                          *
     *************************************************************************/

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

        /**
         * Returns value of token without prefixes
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
?>