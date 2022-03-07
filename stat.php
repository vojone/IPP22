<?php
    /**************************************************************************
     *                                  IPP project                           *  
     *                                    stat.php                            *
     *                                                                        *
     *                              Vojtech Dvorak (xdvora3o)                 *
     *                                    March 2022                          *
     *************************************************************************/

    /**
     * Provides collecting of statiscs and writing it into given files
     * Implemented as SINGLETON (to make statistic information consistent)
     */
    class StatCollector {
        private static $inst = null;

        /**
         * @var Array array with defined labels (as keys) and rows (as values) where it was found
         */
        private $labels;

        /**
         * @var Array array with jump targets (as keys) and rows (as values) where it was found
         */
        private $jumps;

        /**
         * @var Array Asociative array containing files 
         */
        private $stats;

        /**
         * StatCollector constructor
         */
        private function __construct() {
            $this->labels = array();
            $this->jumps = array();
            $this->stats = array();
        }

        /**
         * Constructor of singleton instance of StatCollector
         */
        public static function instantiate() {
            if(StatCollector::$inst === null) {
                StatCollector::$inst = new StatCollector();
            }

            return StatCollector::$inst;
        }

        /**
         * stats setter
         * @param Array $statArr specifies filenames and statistic information, that will be written into this files
         */
        public function setStats($statArr) {
            $this->stats = $statArr;
        }

        /**
         * Adds label to label array, necessary for correct calculation of labels,fw, back and badjumps
         * @param String $labelName Name that will be added
         * @param Integer $rowNumber Row where label occured
         */
        public function addLabel($labelName, $rowNumber) {

            //There should be only one target row (there should be only unique label names), but for sure
            if(array_key_exists($labelName, $this->labels)) {
                array_push($this->labels[$labelName], $rowNumber);
            }
            else {
                $this->labels[$labelName] = array($rowNumber);
            }
        }

        /**
         * Adds label to label array, necessary for correct calculation of fw, back and badjumps
         * @param String $jumpTarget Target of jump (label)
         * @param Integer $rowNumber Row where jumps occured
         */
        public function addJump($jumpTarget, $rowNumber) {
            if(array_key_exists($jumpTarget, $this->jumps)) {
                array_push($this->jumps[$jumpTarget], $rowNumber);
            }
            else {
                $this->jumps[$jumpTarget] = array($rowNumber);
            }
        }

        /**
         * Increments group of stats
         * @param String $statName Specifies group of statiscis that shoul be incremented (e. g. loc, comments...)
         */
        public function incStats($statName) {
            if(!isset($this->stats)) {
                return;
            }

            foreach($this->stats as $file => $stats) {
                if(array_key_exists($statName, $stats)) {
                    $this->stats[$file][$statName]++;
                }
            }
        }

        /**
         * Calculates fwjumps, backjumps and badjumps
         * @param Integer $fwjumps output parameter
         * @param Integer $backjumps output parameter
         * @param Integer $badjumps output parameter
         */
        private function calculateJumps(&$fwjumps, &$backjumps, &$badjumps) {
            $fwjumps = 0;
            $backjumps = 0;
            $badjumps = 0;

            foreach($this->jumps as $jLabelName => $jrows) {
                if(array_key_exists($jLabelName, $this->labels)) {
                    //If there is more than one occurence of label with same label name (it is invalid code, but OK), the last occurence is taken
                    $lrow = end($this->labels[$jLabelName]);
                    foreach($jrows as $jrow) {
                        if($lrow < $jrow) {
                            $backjumps++;
                        }
                        else if($lrow > $jrow) {
                            $fwjumps++;
                        }
                        else {
                            //Never happens (there cannot be jump and label on the same row)
                        }
                    }
                }
                else {
                    $badjumps++;
                }
            }
        }

        /**
         * Calculates some group of stats
         * It is necessary to call it after code parsing but before stats printing!
         */
        public function calculateStats() {
            $fwjumps = null;
            $backjumps = null;
            $badjumps = null;

            foreach($this->stats as $file => $stats) {
                if(array_key_exists('labels', $stats)) {
                    $this->stats[$file]['labels'] = count($this->labels);
                }

                if(array_key_exists('badjumps', $stats) ||
                   array_key_exists('fwjumps', $stats) ||
                   array_key_exists('backjumps', $stats)) {

                    if(!$fwjumps || !$backjumps || !$badjumps) {
                        $this->calculateJumps($fwjumps, $backjumps, $badjumps);
                    }

                    if(array_key_exists('badjumps', $stats)) {
                        $this->stats[$file]['badjumps'] = $badjumps;
                    }

                    if(array_key_exists('backjumps', $stats)) {
                        $this->stats[$file]['backjumps'] = $backjumps;
                    }

                    if(array_key_exists('fwjumps', $stats)) {
                        $this->stats[$file]['fwjumps'] = $fwjumps;
                    }   
                }
            }
        }

        /**
         * Prints files with its group of stats (specified in stats attribute)
         */
        public function printStats() {
            if(!isset($this->stats)) {
                return;
            }

            foreach($this->stats as $file => $stats) {
                $fd = fopen($file, 'w');

                foreach($stats as $value) {
                    fwrite($fd, "{$value}\n");
                }

                fclose($fd);
            }
        }
    }
?>