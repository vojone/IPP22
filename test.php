<?php
    define('INVALID_ARG_COMBINATION', 10);
    define('BAD_PATH_GIVEN', 41);
    define('SUCCESS', 0);

    /**
     * Contains ipmlicit values of test parameters
     */
    class Options {
        public static $directory = '.';
        public static $recursive = false;
        public static $parseScript = 'parse.php';
        public static $intScript = 'interpret.py';
        public static $parseOnly = false;
        public static $intOnly = false;
        public static $jexamPath = '/pub/courses/ipp/jexamxml/';
        public static $noclean = '.';

        public static $generatedFilesName = 'auto';
    }



    $lOpts = array('help', 'directory::', 'recursive', 
                   'parse-script::', 'int-script::', 
                   'parse-only', 'int-only', 'jexampath::', 
                   'noclean');

    $sOpts = '';
    $args = getopt($sOpts, $lOpts);
    parseOpts($args);

    $stderr = fopen('php://stderr', 'a');

    $testFolders = array();
    findTestFolders(Options::$directory, $testFolders, Options::$recursive);

    var_dump($testFolders);




    function findTestFolders($dir, &$testFolder, $recursive) {
        $testFolders = array();
        if(!is_dir($dir)) {
            fwrite($stderr, "Cannot acces given directory '{$dir}'");
            exit(BAD_PATH_GIVEN);
        }


        $filesAndFolders = scandir($dir);

        $toRemove = array('.', '..');
        $dir = substr($dir, -1) === '/' ? $dir : $dir.'/';
        foreach($filesAndFolders as $potentialTestFolder) {
            $fullPath = $dir.$potentialTestFolder;

            if(is_dir($fullPath) && 
               !in_array($potentialTestFolder, $toRemove)) {

                $files = scandir($fullPath);
                foreach($files as $file) {
                    if(pathinfo($fullPath.$file, PATHINFO_EXTENSION ) == 'src') {
                        generateMissing($fullPath);
                        array_push($testFolders, $fullPath);
                    }
                }

                if($recursive) {
                    findTestFolders($fullPath, $testFolder, true);
                }
            }
        }
    }

        public function preventEmptyFiles($out, $tmpXML, &$difference) {
            //Preven premature EOF error in jexamtool
            $outContent = trim(file_get_contents($out));
            $gotContent = trim(file_get_contents($tmpXML));
            if($outContent === "" || $gotContent === "") {

                if($outContent === "" && $gotContent === "") {
                    return true;
                }
                else {
                    if($outContent === "") {
                        $difference = "Expected empty file, but printed file is not empty!";
                    }
                    else {
                        $difference = "Printed file is empty, but expected result is not!";
                    }

                    return false;
                }
            }

            return null;
        }

        /**
         * Compares return codes and output of DUT and expected output
         * @return Bool True if test passed otherwise false
         */
        public function compare($test, $resultCode, &$difference) {
            if($resultCode === false) {
                return true;
            }

            if(!$this->compareRC($test, $resultCode, $difference)) { //Comparing return code
                return false;
            }

            if($resultCode !== 0) {
                return true;
            }  

            $out = $test['out'];
            $returnCode = 0;
            $diffFile = $test['folder'].$test['name'].'.'.Options::$diffFileSuffix;

            if(Options::$parseOnly) { //Parse-only option is used

                $tmpXML = $test['folder'].$test['name'].'.'.Options::$tempXMLFileSuffix;

                $eFileResult = $this->preventEmptyFiles($out, $tmpXML, $difference);
                if($eFileResult !== null) {
                    return $eFileResult;
                }

                $jexamJarPath = addSlashToDir(Options::$jexamPath).Options::$jexamJar;
                $jexamOptsPath = addSlashToDir(Options::$jexamPath).Options::$jexamOpts;

                exec("java -jar \"{$jexamJarPath}\" \"{$out}\" \"{$tmpXML}\" \"{$diffFile}\" /D \"{$jexamOptsPath}\"", result_code : $returnCode);
            }
            else {
                $tmp = $test['folder'].$test['name'].'.'.Options::$tempFileSuffix;

        $rcMissing = true;
        $inMissing = true;
        $outMissing = true;

        foreach($files as $file) {
            switch (pathinfo($file, PATHINFO_EXTENSION)) {
                case 'rc':
                    $rcMissing = false;
                    break;
                case 'in':
                    $inMissing = false;
                    break;
                case 'out':
                    $outMissing = false;
                    break;
            }
        }

        if($rcMissing) {
            $rc = fopen($path.'/'.Options::$generatedFilesName.'.rc', 'w');
            fwrite($rc, '0');
            fwrite($rc, "\n");
            fclose($rc); 
        }

        if($inMissing) {
            $in = fopen($path.'/'.Options::$generatedFilesName.'.in', 'w');
            fclose($in);
        }

        if($outMissing) {
            $out = fopen($path.'/'.Options::$generatedFilesName.'.out', 'w');
            fclose($out);
        }
    }

    function parseOpts($args) {
        if(isset($args['help'])) {
            printHelp();

            if(count($args) > 1) {
                fwrite($stderr, 'Error: Cannot use --help option with other options!');
                exit(INVALID_ARG_COMBINATION);
            }
            else {
                exit(SUCCESS);
            }
        }
        
        if(isset($args['directory'])) {
            Options::$directory = $args['directory'];
        }

        if(isset($args['recursive'])) {
            Options::$recursive = true;
        }

        if(isset($args['parse-script'])) {
            Options::$parseScript = $args['parse-script'];
        }

        if(isset($args['int-script'])) {
            Options::$intScript = $args['int-script'];
        }

        if(isset($args['parse-only'])) {
            if(isset($args['int-only']) || isset($args['int-script'])) {
                fwrite($stderr, 'Error: --parse-only option cannot be combined with --int-only nor with --int-script!');
                exit(INVALID_ARG_COMBINATION);
            }
            else {
                Options::$parseOnly = true;
            }
        }

        if(isset($args['int-only'])) {
            if(isset($args['parse-only']) || isset($args['parse-script'])) {
                fwrite($stderr, 'Error: --int-only option cannot be combined with --parse-only nor with --parse-script!');
                exit(INVALID_ARG_COMBINATION);
            }
            else {
                Options::$intOnly = true;
            }
        }

        if(isset($args['jexampath'])) {
            Options::$jexamPath = $args['int-jexampath'];
        }

        if(isset($args['noclean'])) {
            Options::$noclean = true;
        }
    }


    function printHelp() {
        echo <<<END
        test.php - Testovací rámec parseru a interpretu IPPcode22 (PHP8 skript)
        
        

        Použití:
        ./php8.1 test.php [--help] [--directory='path'] [--recursive]

        Možnosti:
        --help      Vypíše stručnou nápovědu

        Návratové kódy:
        0   Vstupní program neobsahuje žádnou lexikální nebo syntaktickou chybu
        21  Chybějící/neplatná hlavička na začátku programu
        22  Neznámý/chybný operační kód
        23  Jiná lexikální syntaktická chyba
        99  Interní chyba parseru 

        END;
    }
?>