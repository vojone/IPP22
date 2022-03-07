<?php
    /**************************************************************************
     *                                  IPP project                           *  
     *                                  printer.php                           *
     *                                                                        *
     *                           Vojtech Dvorak (xdvora3o)                    *
     *                                    March 2022                          *
     *************************************************************************/

    /**
     * Prepares XML document in memory and then it can prints it to given output 
     * Uses XMLWriter to create XML element
     * 
     * @author Vojtech Dvorak
     */
    class XMLPrinter {
        /**
         * XML context which is used for preparing document by XML writer
         */
        private $xmlCtx;

        /**
         * File pointer to target file (got e.g. by fopen())
         */
        private $outputStream;

        /**
         * @param FilePointer $output Pointer to output file
         * @param String $indentString Used for making indentation
         * @param Int $indentLen How many string will be used for indentation of XML elements
         */
        function __construct($output, $indentType, $indentLen) {
            $this->xmlCtx = xmlwriter_open_memory();

            //Set indentation
            xmlwriter_set_indent($this->xmlCtx, $indentLen);
            xmlwriter_set_indent_string($this->xmlCtx, $indentType);

            $this->outputStream = $output;
        }


        /**
         * Initializes XML document, creates root element, need to be called before creating instructions
         */
        public function initDoc() {
            xmlwriter_start_document($this->xmlCtx, '1.0', 'UTF-8');

            //Root element with language attribute
            xmlwriter_start_element($this->xmlCtx, 'program');
            xmlwriter_start_attribute($this->xmlCtx, 'language');
            xmlwriter_text($this->xmlCtx, 'IPPcode22');
            xmlwriter_end_attribute($this->xmlCtx);
        }

        /**
         * Starts instruction element, it can be ended by 
         * @param String $opcode Code of instruction (in uppercase)
         * @param Int $order Number of instruction
         */
        public function startInstruction($opcode, $order) {
            xmlwriter_start_element($this->xmlCtx, 'instruction');

            xmlwriter_start_attribute($this->xmlCtx, 'order');
            xmlwriter_text($this->xmlCtx, $order);
            xmlwriter_end_attribute($this->xmlCtx);

            xmlwriter_start_attribute($this->xmlCtx, 'opcode');
            xmlwriter_text($this->xmlCtx, strtoupper($opcode));
            xmlwriter_end_attribute($this->xmlCtx);
        }

        /**
         * Ends istruction sequence
         */
        public function endInstruction() {
            xmlwriter_end_element($this->xmlCtx);
        }

        /**
         * Prints argument tag
         * @param Int $num Order number of argument
         * @param String $type Type of argument (label, type, int, str...)
         * @param String $value Content of argument tag
         */
        public function printArgument($num, $type, $value) {
            xmlwriter_start_element($this->xmlCtx, "arg{$num}");

            xmlwriter_start_attribute($this->xmlCtx, 'type');
            xmlwriter_text($this->xmlCtx, $type);
            xmlwriter_end_attribute($this->xmlCtx);

            //Substituion of problematic characters for equivalent sequences
            
            //$xmlFriendlyVal = htmlspecialchars($value, ENT_XML1, 'UTF-8'); <- SUPERFLUOS XML writer is XML safe
            xmlwriter_text($this->xmlCtx, $value);

            xmlwriter_end_element($this->xmlCtx);
        }

        /**
         * Ends prepared document, needs to be called after initDoc()
         */
        public function endDoc() {
            //End root element
            xmlwriter_end_element($this->xmlCtx);

            xmlwriter_end_document($this->xmlCtx);
        }

        /**
         * Prints ended XML document, needs to be called after initDoc()
         */
        public function printXML() {
            fwrite($this->outputStream, xmlwriter_output_memory($this->xmlCtx));
        }
    }
?>