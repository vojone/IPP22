<?php
    /**************************************************************************
     *                           IPP project - test framework                 *  
     *                                   test.php                             *
     *                                                                        *
     *                                 Vojtech Dvorak                         *
     *                                   March 2022                           *
     *************************************************************************/


    ini_set('display_errors', 'stderr');

    define('INVALID_ARG_COMBINATION', 10);
    define('BAD_PATH_GIVEN', 41);
    define('SUCCESS', 0);

    /**
     * Contains implicit values of test parameters
     * These parametres can be changed by using options of script
     */
    class Options {
        public static $directory = '.';
        public static $recursive = false;
        public static $parseScript = 'parse.php';
        public static $intScript = 'interpret.py';
        public static $parseOnly = false;
        public static $intOnly = false;

        public static $jexamPath = 'jexamxml/'; //TODO
        public static $jexamJar = 'jexamxml.jar';
        public static $jexamOpts = 'options';
        public static $noclean = false;

        public static $generatedRCContent = '0';

        public static $tempFileSuffix = 'tmp';
        public static $tempXMLFileSuffix = 'xml.tmp';

        public static $diffFileSuffix = 'diff';
    }

    $lOpts = array('help', 'directory::', 'recursive', 
                   'parse-script::', 'int-script::', 
                   'parse-only', 'int-only', 'jexampath::', 
                   'noclean');

    $tests = new Tests();

    $sOpts = '';
    $args = getopt($sOpts, $lOpts);
    $tests->parseOpts($args);

    HTML::printProlog();

    $tests->run(Options::$directory, Options::$recursive);

    fwrite($tests->stderr, "=========================================\n");
    fwrite($tests->stderr, "Summary: {$tests->success}/{$tests->total}  (passed/total)\n");

    HTML::printSummary($argv, $tests);

    HTML::printEpilog();

    
    /**
     * Adds slash to str with path after last folder
     * If there is already, it does nothing
     */
    function addSlashToDir($dir) {
        $dir = substr($dir, -1) === '/' ? $dir : $dir.'/';

        return $dir;
    }

    /**
     * Main class of test framework
     * Contains necessary methods to run tests and compare results with expected values
     */
    class Tests {
        /**
         * @var Resource $stderr File where are printed log messages and warnings 
         */
        public $stderr = null;

        /**
         * @var Int $total Counter of tests
         */
        public $total = 0;

        /**
         * @var Int $success Counter of succesfull tests
         */
        public $success = 0;

        function __construct() {
            $this->stderr = fopen('php://stderr', 'a');
        }

        /**
         * Prints brief help to stdout
         */
        public function printHelp() {
            echo <<<DOC
            test.php - Test framework for IPPcode22 parser and interpret (PHP8 skript)
            
            Usage:
            ./php8.1 test.php [OPTIONS]
    
            Options:
            --help              Writes brief help to STDIN 
            --directory=''      Specififes directory with tests (implicitly it is current folder)
            --recursive         Checks nested folders in given directory
            --parse-script=''   Specifies path to parser (implicitly 'parse.php')
            --int-script=''     Specifies path to interperter (implicitly 'interpret.py')
            --parse-only        Test only translation of source code to XML by parser
            --int-only          Test only intepretation of XML
            --jexampath=''      Specifies path to directory with jexam tool (implicitly '/pub/courses/ipp/jexamxml/')
            --noclean           Test framework does not delete temporary files (with differences)
    
            DOC;
        }

        /**
         * Parses arguments from cmdline
         */
        public function parseOpts($args) {
            if(isset($args['help'])) {
                if(count($args) > 1) {
                    fwrite($this->stderr, 'Error: Cannot use --help option with other options!'.PHP_EOL);
                    exit(INVALID_ARG_COMBINATION);
                }
                else {
                    $this->printHelp();
                    exit(SUCCESS);
                }
            }
            
            if(isset($args['directory'])) {
                Options::$directory = addSlashToDir($args['directory']);
            }
    
            if(isset($args['recursive'])) {
                Options::$recursive = true;
            }
    
            if(isset($args['parse-script'])) {
                Options::$parseScript = addSlashToDir($args['parse-script']);
            }
    
            if(isset($args['int-script'])) {
                Options::$intScript = addSlashToDir($args['int-script']);
            }
    
            if(isset($args['parse-only'])) {
                if(isset($args['int-only']) || isset($args['int-script'])) {
                    fwrite($this->stderr, 'Error: --parse-only option cannot be combined with --int-only nor with --int-script!'.PHP_EOL);
                    exit(INVALID_ARG_COMBINATION);
                }
                else {
                    Options::$parseOnly = true;
                }
            }
    
            if(isset($args['int-only'])) {
                if(isset($args['parse-only']) || isset($args['parse-script'])) {
                    fwrite($this->stderr, 'Error: --int-only option cannot be combined with --parse-only nor with --parse-script!'.PHP_EOL);
                    exit(INVALID_ARG_COMBINATION);
                }
                else {
                    Options::$intOnly = true;
                }
            }
    
            if(isset($args['jexampath'])) {
                Options::$jexamPath = addSlashToDir($args['jexampath']);
            }
    
            if(isset($args['noclean'])) {
                Options::$noclean = true;
            }
        }

        /**
         * Check presence (and access rights) of all necessary files for perfoming tests
         */
        public function checkNecessaryFiles() {
            if(Options::$parseOnly || !Options::$intOnly) {
                if(!is_file(Options::$parseScript) || 
                !is_readable(Options::$parseScript)) {
                    $this->invalidFile(Options::$parseScript);
                }

                if(!is_file($jexamJarPath) || !is_readable($jexamJarPath)) {
                    $this->invalidFile($jexamJarPath);
                }

                if(!is_file($jexamOptsPath) || !is_readable($jexamOptsPath)) {
                    $this->invalidFile($jexamOptsPath);
                }
            }
            
            if(Options::$intOnly || !Options::$parseOnly) {
                if(!is_file(Options::$parseScript) || 
                !is_readable(Options::$parseScript)) {
                    $this->invalidFile(Options::$parseScript);
                }
            }

            if(!is_dir($dir) || !is_readable($dir) || !is_writeable($dir)) {
                fwrite($this->stderr, "Error: Cannot acces given directory '{$dir}'".PHP_EOL);
                exit(BAD_PATH_GIVEN);
            }
        }

        /**
         * Function that runs DUT above given test case - EXERCISE part
         * @param Array $test Asociative array containing paths to files that belongs to test case
         * @return Integer Return code of executed script
         */
        public function test($test) {
            $resultCode = 0;
            $tmp = $test['folder'].$test['name'].'.'.Options::$tempFileSuffix;
            $tmpXML = $test['folder'].$test['name'].'.'.Options::$tempXMLFileSuffix;

            if(Options::$parseOnly) {
                exec("php8.1 ".Options::$parseScript." <\"{$test['src']}\" >\"{$tmpXML}\"", result_code : $resultCode);
            }
            else if(Options::$intOnly) {
                exec("python3.8 ".Options::$intScript." <\"{$test['src']}\" --input=\"{$test['in']}\" >\"{$tmp}\"", result_code : $resultCode);
            }
            else {
                exec("php8.1 ".Options::$parseScript." <\"{$test['src']}\" >\"{$tmpXML}\"", result_code : $resultCode);

                if($resultCode === 0) {
                    exec("python3.8 ".Options::$intScript." <\"{$tmpXML}\" --input=\"{$test['in']}\" >\"{$tmp}\"", result_code : $resultCode);
                }
            }

            return $resultCode;
        }

        /**
         * Workaround method to preven premarture EOF error in jexamtool
         */
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
         * Compares return codes and output of DUT and expected output - VERIFY part
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

                $tmpXML = $test['folder'].$test['name'].'.'.Options::$tempXMLFileSuffix;
                
                //Comparing XML
                exec("java -jar \"{$jexamJarPath}\" \"{$out}\" \"{$tmpXML}\" \"{$diffFile}\" /D \"{$jexamOptsPath}\"", result_code : $returnCode);
            }
            else {
                $tmp = $test['folder'].$test['name'].'.'.Options::$tempFileSuffix;
                
                //Comparing regular file
                exec("diff \"{$out}\" \"{$tmp}\" >\"{$diffFile}\"" , result_code : $returnCode);
            }

            if($returnCode === 0) {
                $difference = null;
                return true;
            }
            else {
                if(is_readable($diffFile)) { //Getting differences and passing it as output parameter
                    $difference = file_get_contents($diffFile); 
                }
                
                return false;
            }
        }

        /**
         * Compares return code with return code from file
         */
        public function compareRC($test, $resultCode, &$difference) {
            $rc = $test['rc'];
            $rcContent = file_get_contents($rc);

            $expectedRC = trim($rcContent);
            if($expectedRC != $resultCode) {
                $difference = "Return codes differ! (expected: {$expectedRC}, got: {$resultCode})".PHP_EOL;

                return false;
            }

            return true;
        }

        /**
         * Clears all temporary files in test directory (file with differencies,...)
         */
        public function clearDir($test) {
            if(!Options::$noclean) {
                exec("rm -f \"".$test['folder'].$test['name'].'.'.Options::$tempFileSuffix."\"");
                exec("rm -f \"".$test['folder'].$test['name'].'.'.Options::$tempXMLFileSuffix."\"");
                exec("rm -f \"".$test['folder'].$test['name'].'.'.Options::$diffFileSuffix."\"");
            }
        }

        /**
         * Executes tests in given directory
         * @param String $dir target directory with tests
         * @param Bool $recurise specifies, whether method should search nested files recursively
         */
        public function run($dir, $recursive) {
            $dir = addSlashToDir($dir);   
            
            if(!is_readable($dir) || !is_writeable($dir)) {
                $this->invalidFile($dir);
            }

            $srcs = glob($dir.'*.src');
        
            if(count($srcs) > 0) { //There are tests in folder
                HTML::startNewFolder($dir, $dir);
                fwrite($this->stderr, "Running tests in folder: {$dir}\n");
                fwrite($this->stderr, "=========================================\n");

                foreach($srcs as $src) {
                    $test = array();

                    if(!is_readable($src)) {
                        continue;
                    }

                    $test['folder'] = $dir; //Creating asociative array with test paths
                    $test['name'] = basename($src, '.src');
                    $test['src'] = $src;

                    $this->total++;

                    fwrite($this->stderr, "{$this->total} TEST: {$test['name']} \n");

                    $this->checkForOtherFiles($test); 

                    $diff = null;
                    $ret = $this->test($test); //Testing DUT
                    $passed = $this->compare($test, $ret, $diff); //Comparing results

                    if($passed) { //TEARDOWN
                        $this->success++;

                        HTML::printItemSuccess('', $this->total, $test, "<span class='success'><b>Pass!</b></span>");
                        fwrite($this->stderr, "\nRESULT: PASSED\n");
                    }
                    else {
                        $out = file_get_contents($test['out']);

                        $gotPath = $test['folder'].$test['name'].'.'.Options::$tempFileSuffix;

                        if(Options::$parseOnly) {
                            $gotPath = $test['folder'].$test['name'].'.'.Options::$tempXMLFileSuffix;
                        }

                        $got = file_get_contents($gotPath);

                        HTML::printItemFailed('', $this->total, $test, $out, $got, $diff);
                        fwrite($this->stderr, "\nRESULT: FAILED\n");
                    }

                    fwrite($this->stderr, "-----------------------------------------\n");

                    $this->clearDir($test);
                }
            }

            if($recursive) {
                $this->testInnerFolders($dir); //Indirect recursion of run function
            }

            if(count($srcs) > 0) {
                HTML::endFolder();
            }
        }

        /**
         * Finds folders where can occurs test cases (used if --recursive option is selected)
         */
        private function testInnerFolders($dir) {
            $folderContent = scandir($dir);

            $toRemove = array('.', '..');
            foreach($folderContent as $folderObject) {
                if(is_dir($dir.$folderObject) && !in_array($folderObject, $toRemove)) {
                    if(is_readable($dir.$folderObject) && is_writeable($dir.$folderObject)) {
                        $this->run($dir.$folderObject, true);
                    }
                    else {
                        fwrite($this->stderr, 'Warning: --recursive: Found '.$dir.$folderObject.' but it is not writeable or readable! Skipping this folder...'.PHP_EOL);
                    }
                }
            }
        }

        /**
         * Search necessary files for performing tests (except of .src files)
         */
        public function checkForOtherFiles(&$test) {
            $test['rc'] = $this->findFile($test, 'rc', Options::$generatedRCContent);
            $test['out'] = $this->findFile($test, 'out');
            $test['in'] = $this->findFile($test, 'in');
        }

        /**
         * Tries to find file, if there is no specified file, it is generated with given content
         * @return String Path to created/searched file
         */
        public function findFile($test, $extension, $autoContent = null) {
            $fileArray = glob($test['folder'].$test['name'].'.'.$extension);

            $file = null;
            if(count($fileArray) >= 1) {
                $file = $fileArray[0];
            }
            else {
                $file = $test['folder'].$test['name'].'.'.$extension;
                $fd = fopen($file, 'w');

                if($autoContent !== null) {
                    fwrite($fd, $autoContent);
                }

                fclose($fd); 
            }

            return $file;
        }

        /**
         * Prints error messages about invalid file and ends program with specific error code
         */
        public function invalidFile($path) {
            fwrite($this->stderr, "Error: Cannot acces file or folder '{$path}'!".PHP_EOL);
            exit(BAD_PATH_GIVEN);
        }
    }

    /**
     * Provides priting of HTML content to stdout
     * Methods are specialised for printing test results
     */
    class HTML {

        public static function printProlog($header = '') {
            echo <<<DOC
            <!DOCTYPE html>
            <html>
                <head>
                    <title>IPPcode22 test framework</title>
                </head>
    
                <body>
            DOC;

            HTML::printJS();
            HTML::printCSS();

            echo <<<DOC
                <div class='header'>{$header}</div>
                <div class='content'>
            DOC;
        }
        
        /**
         * Prints CSS content to make result more beautiful
         */
        public static function printCSS() {
            echo <<<DOC
    
            <style>
                body {
                    background-color: whitesmoke;
                    font-family: Calibri;
                }
    
                div.leader {
                    order: 1;
                    background-color: white;
                    border-radius: 10px;
                    padding: 10px 10px 20px 20px;
                }
    
                div.header {
                    top: 0;
                    left: 0;
                    right: 0;
    
                    margin-bottom: 10px;
                }
    
                div.content {
                    display: flex;
                    flex-direction: column;
                    width: 100%;
                }
    
                div.item {
                    order: 2;
                    background-color: white;
                    border-radius: 10px;
                    padding: 10px 10px 20px 20px;
    
                    margin-bottom: 10px;
                }
    
                div.item h2 {
                    margin-top: 2px;
                }
    
                div.item .wrapper {
                    display: flex;
                    flex-direction: row;
                    justify-content: space-between;
                }
    
                div.item .details {
                    border: 1px solid black;
                    border-radius: 5px;
                    padding: 10px;
                    width: 30%;
                    overflow: auto;
                }
    
                div.success {
                    background-color: #c9ffd0;
                    border: 1px solid green;
                }
    
                span.success {
                    color: green;
                }
    
                span.info {
                    color: #ff8f00;
                }
    
                div.fail {
                    background-color: #ffadad;
                    border: 1px solid red;
                }
    
                span.fail {
                    color: red;
                }
    
                .code {
                    font-family: Courier;
                }
    
                div.folder {
                    order: 2;
    
                    display: flex;
                    flex-direction: column;
                    padding: 10px;
                    border: 1px black solid;
                    border-radius: 10px;
    
                    background-color: transparent;
    
                    margin: 10px 0 10px 0;
                }
    
                div.folder.success {
                    background-color: #f5fff8;
                }
    
                div.folder.fail {
                    background-color: #ffeded;
                }
    
                span.additional {
                    font-weight: normal;
                    color: rgba(0, 0, 0, 0.5);
                    margin-right: 10px;
                }
    
                span.additional.files {
                    margin-left: 30px;
                    font-size: 0.6em;
                    word-spacing: 20px;
                }
    
                span.additional.folder * {
                    font-size: 0.7em;
                    text-decoration: none;
                    color: rgba(0, 0, 0, 0.3);
                }
    
                #hideButton {
                    text-decoration: underline;
                    cursor: pointer;
                    display: inline;
                    user-select: none;
                    color: blue;
                }
    
                #hideButton:hover {
                    color: purple;
                }

                .hide {
                    display: none !important;
                }

                .styleless {
                    text-decoration: none;
                    color: black;
                }
    
            </style>
    
            DOC;
        }
    
        /**
         * Prints simple JS content to support interactivity
         */
        public static function printJS() {
            echo <<<DOC
    
                <script>
                    document.addEventListener("DOMContentLoaded", () => {
                        styleFolders();
                    });

                    function styleFolders() {
                        var folders = document.getElementsByClassName('folder');

                        for(var i = 0; folders[i]; i++) {
                            var tests = folders[i].getElementsByClassName('fail');

                            if(tests.length > 0) {
                                folders[i].className += ' fail';
                            }
                            else {
                                folders[i].className += ' success';
                            }
                        }
                    }

                    function hideSuccessful() {
                        var button = document.getElementById('hideButton');
    
                        var succesfulTests = document.getElementsByClassName('success');
    
                        for(var i = 0; succesfulTests[i]; i++) {
                            if(succesfulTests[i].tagName.toUpperCase() == 'DIV') {
                                succesfulTests[i].className += ' hide';
                            }
                        }
    
                        button.innerHTML = '<b>Show all tests</b>';
                        button.onclick = showAll;
                    }
    
                    function showAll() {
                        var button = document.getElementById('hideButton');
    
                        var succesfulTests = document.getElementsByClassName('success');
    
                        for(var i = 0; succesfulTests[i]; i++) {
                            if(succesfulTests[i].tagName.toUpperCase() == 'DIV') {
                                var hideIndex = succesfulTests[i].className.indexOf(' hide');
                                if(hideIndex > 0) {
                                    succesfulTests[i].className = succesfulTests[i].className.slice(0, hideIndex);
                                }
                            }
                        }
    
                        button.innerHTML = 'Show only failed tests';
                        button.onclick = hideSuccessful;
                    }
    
    
                </script>
            
            DOC;
        }

        /**
         * Prints the header of item in the list with the tests
         */
        public static function printItemHeader($num, $test) {
            $name = $test['name'];
            $folder = $test['folder'];

            echo <<<DOC

            <span class='additional folder' title='Folder with test'>
                <a href='{$folder}'>
                    {$folder}
                </a>
            </span>
            <h2>
                <span class='additional' title='Order number'>
                    #{$num}
                </span>
                <span title='Test name'>
                    {$name}
                </span>
                <span class='additional files' title='Test files'>
                    <a href='{$test['src']}'>source</a> 
                    <a href='{$test['rc']}'>retcode</a> 
                    <a href='{$test['in']}'>input</a> 
                    <a href='{$test['out']}'>output</a>
                </span>
            </h2>

            DOC;
        }
    
        public static function printItemSuccess($subClasses, $num, $test, $content) {
            echo <<<DOC
    
                <div class='item success {$subClasses}' id='{$num}'>
                    
            DOC;

            HTML::printItemHeader($num, $test);

            echo <<<DOC

                    {$content}
                </div>
    
            DOC;
        }

        public static function printItemFailed($subClasses, $num, $test, $out, $got, $diff) {
            echo <<<DOC
    
                <div class='item fail {$subClasses}' id='{$num}'>
            
            DOC;

            HTML::printItemHeader($num, $test);

            $out = htmlspecialchars($out === null ? '' : $out);
            $got = htmlspecialchars($out === null ? '' : $got);
            $diff = htmlspecialchars($out === null ? '' : $diff);

            echo <<<DOC

                    <h3><span class='fail'><b>Failed!</b></span></h3>
                    <div class='wrapper'>
                        <div class='details'>Expected:<span class='code'><pre>{$out}</pre></span></div>
                        <div class='details'>Got:<span class='code'><pre>{$got}</pre></span></div>
                        <div class='details'>Differences:<span class='code'><pre>{$diff}</pre></span></div>
                    </div>
                </div>
    
            DOC;
        }
    
    
        public static function printSummary($argv, $testsObj, $message = '') {
            $flagStr = '';
            foreach($argv as $arg) {
                $flagStr = $flagStr.$arg.' '; 
            }
            
            $dateStr = date("Y-m-d H:i:s", time());

            echo <<<DOC
    
                <div class='leader'>
                    <h1>Tests summary</h1>
                    Test results from: &nbsp &nbsp <span class='code'>{$dateStr}</span><br>
                    Command: &nbsp &nbsp <span class='code'>{$flagStr}</span>
                    <br>
                    <br>
            DOC;

            if($testsObj->total == 0) {
                echo <<<DOC
                    <b>No folder with test was found... Check given directory.</b>
                </div>
                DOC;
            }
            else {
                if($testsObj->success/$testsObj->total == 1) {
                    echo "Result: &nbsp &nbsp <span class='success'><b>{$testsObj->success}</b></span>/{$testsObj->total} (passed/total)<br>";
                    echo "<span class='success'><b>Well done! All tests passed!</b></span>";
                }
                else if($testsObj->success/$testsObj->total >= 0.9) {
                    echo "Result: &nbsp &nbsp <span class='info'><b>{$testsObj->success}</b></span>/{$testsObj->total} (passed/total)<br>";
                }
                else {
                    echo "Result: &nbsp &nbsp <span class='fail'><b>{$testsObj->success}</b></span>/{$testsObj->total} (passed/total)<br>";
                }

                echo <<<DOC
                        <br>
                        <br>
                        Use CTRL-F to find specific test by name.
                        <br>
                        <br>
                        <div onclick='hideSuccessful()' id='hideButton'>
                            Show only failed tests
                        </div>
                    </div>
                DOC;
            }

        }
    
        /**
         * Starts div element representing folder with tests
         */
        public static function startNewFolder($name, $path) {
            $path = htmlspecialchars($path);

            echo <<<DOC
                <div class='folder'>
                <h4>
                    <span class='additional'>Folder:</span>&nbsp<a class='styleless' href='{$path}'>{$name}</a>
                </h4>
            DOC;
        }
    
        /**
         * Ends div representing folder
         */
        public static function endFolder() {
            echo <<<DOC
                </div>
            DOC;
        }
    
    
        public static function printEpilog() {
            echo <<<DOC
                </div>
                </body>
            </html>
    
            DOC;
        }
    }
?>    
