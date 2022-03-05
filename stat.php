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
        public static $inst = null;

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

        private function __construct() {
            $this->labels = array();
            $this->jumps = array();
            $this->stats = array();
        }

        public static function instantiate() {
            if(StatCollector::$inst === null) {
                StatCollector::$inst = new StatCollector();
            }

            return StatCollector::$inst;
        }

        public function setStats($statArr) {
            $this->stats = $statArr;
        }

        public function addStats($newStatArr) {
            array_push($this->stats, $newStatArr);
        }

        public function addLabel($labelName, $rowNumber) {

            //There should be only one target row (there should be only unique label names), but for sure
            if(array_key_exists($labelName, $this->labels)) {
                array_push($this->labels[$labelName], $rowNumber);
            }
            else {
                $this->labels[$labelName] = array($rowNumber);
            }
        }

        public function addJump($jumpTarget, $rowNumber) {
            if(array_key_exists($jumpTarget, $this->jumps)) {
                array_push($this->jumps[$jumpTarget], $rowNumber);
            }
            else {
                $this->jumps[$jumpTarget] = array($rowNumber);
            }
        }

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

        private function calculateJumps(&$fwdjumps, &$backjumps, &$badjumps) {
            $fwdjumps = 0;
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
                            $fwdjumps++;
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

        public function calculateStats() {
            $fwdjumps = null;
            $backjumps = null;
            $badjumps = null;

            foreach($this->stats as $file => $stats) {
                if(array_key_exists('labels', $stats)) {
                    $this->stats[$file]['labels'] = count($this->labels);
                }

                if(array_key_exists('badjumps', $stats) ||
                   array_key_exists('fwdjumps', $stats) ||
                   array_key_exists('backjumps', $stats)) {

                    if(!$fwdjumps || !$backjumps || !$badjumps) {
                        $this->calculateJumps($fwdjumps, $backjumps, $badjumps);
                    }

                    if(array_key_exists('badjumps', $stats)) {
                        $this->stats[$file]['badjumps'] = $badjumps;
                    }

                    if(array_key_exists('backjumps', $stats)) {
                        $this->stats[$file]['backjumps'] = $backjumps;
                    }

                    if(array_key_exists('fwdjumps', $stats)) {
                        $this->stats[$file]['fwdjumps'] = $fwdjumps;
                    }   
                }
            }

            
        }

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