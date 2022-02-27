<?php
    ini_set('display_errors', 'stderr');

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

        public static $jexamPath = 'jexamxml/'; //TODO
        public static $jexamJar = 'jexamxml.jar';
        public static $jexamOpts = 'options';
        public static $noclean = false;

        public static $generatedRCContent = '0';

        public static $tempFileSuffix = '.tmp';
        public static $tempXMLFileSuffix = '.xml.tmp';

        public static $diffFileSuffix = '.diff';
    }

    $lOpts = array('help', 'directory::', 'recursive', 
                   'parse-script::', 'int-script::', 
                   'parse-only', 'int-only', 'jexampath::', 
                   'noclean');

    $testsObj = new Tests();

    $sOpts = '';
    $args = getopt($sOpts, $lOpts);
    $testsObj->parseOpts($args);

    printHTMLProlog('');

    $testsObj->findTestFolders(Options::$directory, Options::$recursive);

    $flagStr = '';
    foreach($argv as $arg) {
        $flagStr = $flagStr.$arg.' '; 
    }

    if($testsObj->total == 0) {
        printHTMLHead("Test results from: <span class='code'>".date("Y-m-d H:i:s", time())."</span><br>
                       Command:<span class='code'>{$flagStr}</span><br><br>
                       <b>No test cases were found!<b>");
    }
    else {
        if($testsObj->success/$testsObj->total == 1) {
            printHTMLHead("Test results from:<span class='code'>".date("Y-m-d H:i:s", time())."</span><br>
                           Command: <span class='code'>{$flagStr}</span><br>
                           Result: <b><span class='success'>{$testsObj->success}</span></b>/{$testsObj->total} (passed/total)<br><br>
                           <span class='success'>Well done! All tests passed!</span>");
        }
        else if($testsObj->success/$testsObj->total >= 0.9) {
            printHTMLHead("Test results from: &nbsp &nbsp <span class='code'>".date("Y-m-d H:i:s", time())."</span><br>
                           Command: &nbsp &nbsp <span class='code'>{$flagStr}</span><br><br>
                           Result:  &nbsp &nbsp <span class='info'>{$testsObj->success}</span>/{$testsObj->total} (passed/total)");
        }
        else {
            printHTMLHead("Test results from: &nbsp &nbsp <span class='code'>".date("Y-m-d H:i:s", time())."</span><br>
                           Command: &nbsp &nbsp <span class='code'>{$flagStr}</span><br><br>
                           Result:  &nbsp &nbsp <span class='fail'>{$testsObj->success}</span>/{$testsObj->total} (passed/total)");
        }
    }

    fwrite($testsObj->stderr, "=========================================\n");
    fwrite($testsObj->stderr, "Summary: {$testsObj->success}/{$testsObj->total}  (passed/total)\n");

    printHTMLEpilog();

    function printHTMLProlog($header) {
        echo <<<DOC
        <!DOCTYPE html>
        <html>
            <head>
                <title>IPPcode22 test framework</title>
            </head>

            <body>
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
                    justify-content: space-around;
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

                .success b, span.success {
                    color: green;
                }

                .info b, span.info {
                    color: #edc40e;
                }

                div.fail {
                    background-color: #ffadad;
                    border: 1px solid red;
                }

                .fail b, span.fail {
                    color: red;
                }

                .code {
                    font-family: Courier;
                }

                .hide {
                    display: none;
                }

                div.folder {
                    order: 2;

                    display: flex;
                    flex-direction: column;
                    padding: 10px;
                    border: 1px black solid;
                    border-radius: 10px;

                    background-color: transparent;

                    margin-bottom: 10px;
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

            </style>
            <script>
                function hideSuccessful() {
                    var button = document.getElementById('hideButton');

                    var succesfulTests = document.getElementsByClassName('success');

                    for(var i = 0; succesfulTests[i]; i++) {
                        succesfulTests[i].className += ' hide';
                    }

                    button.innerHTML = 'Show all';
                    button.onclick = showAll;
                }

                function showAll() {
                    var button = document.getElementById('hideButton');

                    var succesfulTests = document.getElementsByClassName('success');

                    for(var i = 0; succesfulTests[i]; i++) {
                        var hideIndex = succesfulTests[i].className.indexOf(' hide');
                        if(hideIndex > 0) {
                            succesfulTests[i].className = succesfulTests[i].className.slice(0, hideIndex);
                        }
                    }

                    button.innerHTML = 'Show only failed';
                    button.onclick = hideSuccessful;
                }


            </script>
            <div class='header'>{$header}</div>
            <div class='content'>
        DOC;
    }


    function printHTMLItem($subClasses, $num, $test, $result) {
        echo <<<DOC

            <div class='item {$subClasses}' id='{$num}'>
                <span class='additional folder' title='Folder with test'><a href='{$test['folder']}'>{$test['folder']}</a></span>
                <h2><span class='additional' title='Order number'>#{$num}</span><span title='Test name'>{$test['name']}</span>
                <span class='additional files' title='Test files'><a href='{$test['src']}'>source</a> <a href='{$test['rc']}'>retcode</a> <a href='{$test['in']}'>input</a> <a href='{$test['out']}'>output</a></span></h2>
                {$result}
            </div>

        DOC;
    }


    function printHTMLHead($content) {
        echo <<<DOC

            <div class='leader'>
                <h1>Tests summary</h1>
                {$content}<br>
                <br>
                Use CTRL-F to find specific test by name.<br>
                <div onclick='hideSuccessful()' id='hideButton'>Show only failed</div>
            </div>

        DOC;
    }


    function startNewFolder($name) {
        echo <<<DOC
            <div class='folder'>
            <h4><span class='additional'>Folder:</span>&nbsp{$name}</h4>
        DOC;
    }

    function endFolder() {
        echo <<<DOC
            </div>
        DOC;
    }


    function printHTMLEpilog() {
        echo <<<DOC
            </div>
            </body>
        </html>

        DOC;
    }


    function printHelp() {
        echo <<<DOC
        test.php - Test framework for IPPcode22 parser and interpret (PHP8 skript)
        
        Usage:
        ./php8.1 test.php [OPTIONS]

        Options:
        --help      Writes brief help to STDIN 

        DOC;
    }

    function addSlashToDir($dir) {
        $dir = substr($dir, -1) === '/' ? $dir : $dir.'/';

        return $dir;
    }

    class Tests {
        public $stderr = null;

        public $total = 0;

        public $success = 0;

        function __construct() {
            $this->stderr = fopen('php://stderr', 'a');
        }

        function parseOpts($args) {
            if(isset($args['help'])) {
                if(count($args) > 1) {
                    fwrite($this->stderr, 'Error: Cannot use --help option with other options!'.PHP_EOL);
                    exit(INVALID_ARG_COMBINATION);
                }
                else {
                    printHelp();
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
                Options::$jexamPath = addSlashToDir($args['int-jexampath']);
            }
    
            if(isset($args['noclean'])) {
                Options::$noclean = true;
            }
        }

        function checkNecessaryFiles() {
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


        function run($test) {
            $resultCode = 0;
            $tmp = $test['folder'].$test['name'].Options::$tempFileSuffix;
            $tmpXML = $test['folder'].$test['name'].Options::$tempXMLFileSuffix;

            $output = null;
            if(Options::$parseOnly) {
                exec("php8.1 ".Options::$parseScript." <{$test['src']} >{$tmpXML}", result_code : $resultCode);
            }
            else if(Options::$intOnly) {
                exec("python3.8 ".Options::$intScript." <{$test['src']} >{$tmp}", result_code : $resultCode);
            }
            else {
                exec("php8.1 ".Options::$parseScript." <{$test['src']} > {$tmpXML}", result_code : $resultCode);

                if($resultCode === 0) {
                    exec("python3.8 ".Options::$intScript." <{$tmpXML} >{$tmp}", result_code : $resultCode);
                }
            }

            return $resultCode;
        }

        
        function compare($test, $resultCode, &$difference) {
            if($resultCode === false) {
                return true;
            }

            if(!$this->compareRC($test, $resultCode, $difference)) {
                return false;
            }

            if($resultCode !== 0) {
                return true;
            }  

            $out = $test['out'];
            $returnCode = 0;
            $diffFile = $test['folder'].$test['name'].Options::$diffFileSuffix;

            if(Options::$parseOnly) {
                $jexamJarPath = addSlashToDir(Options::$jexamPath).Options::$jexamJar;
                $jexamOptsPath = addSlashToDir(Options::$jexamPath).Options::$jexamOpts;

                $tmpXML = $test['folder'].$test['name'].Options::$tempXMLFileSuffix;

                exec("java -jar {$jexamJarPath} {$out} {$tmpXML} {$diffFile} /D {$jexamOptsPath}", result_code : $returnCode);
            }
            else {
                $tmp = $test['folder'].$test['name'].Options::$tempFileSuffix;

                exec("diff {$out} {$tmp} >{$diffFile}" , result_code : $returnCode);
            }

            if($returnCode === 0) {
                $difference = null;
                return true;
            }
            else {
                if(is_readable($diffFile)) {
                    $difference = file_get_contents($diffFile);
                }
                
                return false;
            }
        }


        function compareRC($test, $resultCode, &$difference) {
            $rc = $test['rc'];
            $rcContent = file_get_contents($rc);

            $expectedRC = trim($rcContent);
            if($expectedRC != $resultCode) {
                $difference = "Return codes differ! (expected: {$expectedRC}, got: {$resultCode})".PHP_EOL;

                return false;
            }

            return true;
        }


        function clearDir($test) {
            if(!Options::$noclean) {
                exec("rm -f ".$test['folder'].$test['name'].Options::$tempFileSuffix);
                exec("rm -f ".$test['folder'].$test['name'].Options::$tempXMLFileSuffix);
                exec("rm -f ".$test['folder'].$test['name'].Options::$diffFileSuffix);
            }
        }


        function findTestFolders($dir, $recursive) {
            $dir = addSlashToDir($dir);            
            $srcs = glob($dir.'*.src');

            if(count($srcs) > 0) {
                startNewFolder($dir);

                fwrite($this->stderr, "Running tests in folder: {$dir}\n");
                fwrite($this->stderr, "=========================================\n");
            }

            foreach($srcs as $src) {
                $test = array();

                if(is_readable($src)) {
                    $test['folder'] = $dir;
                    $test['name'] = basename($src, '.src');
                    $test['src'] = $src;

                    $this->total++;

                    fwrite($this->stderr, "{$this->total} TEST: {$test['name']} \n");

                    $this->checkForOtherFiles($test);

                    $ret = $this->run($test);

                    $difference = null;
                    $passed = $this->compare($test, $ret, $difference);

                    if($passed) {
                        $this->success++;
                        printHTMLItem('success', $this->total, $test, "<b>Pass!</b>");

                        fwrite($this->stderr, "\nRESULT: PASSED\n");
                    }
                    else {
                        $out = file_get_contents($test['out']);

                        if(Options::$parseOnly || !Options::$intOnly) {
                            $got = file_get_contents($test['folder'].$test['name'].Options::$tempXMLFileSuffix);
                        }
                        else {
                            $got = file_get_contents($test['folder'].$test['name'].Options::$tempFileSuffix);
                        }

                        printHTMLItem('fail', $this->total, $test, "<h3><b>Failed!</b></h3>
                    
                        <div class='wrapper'>
                            <div class='details'>Expected:<span class='code'><pre>".htmlspecialchars($out)."</pre></span></div>
                            <div class='details'>Got:<span class='code'><pre>".htmlspecialchars($got)."</pre></span></div>
                            <div class='details'>Differences:<span class='code'><pre>".htmlspecialchars($difference)."</pre></span>
                        </div>
                        </div>");

                        fwrite($this->stderr, "\nRESULT: FAILED\n");
                    }

                    fwrite($this->stderr, "-----------------------------------------\n");

                    $this->clearDir($test);
                }
            }

            if($recursive) {
                $folderContent = scandir($dir);

                $toRemove = array('.', '..');
                foreach($folderContent as $folderObject) {
                    if(is_dir($dir.$folderObject) && !in_array($folderObject, $toRemove)) {
                        if(is_readable($dir.$folderObject) && is_writeable($dir.$folderObject)) {
                            $this->findTestFolders($dir.$folderObject, true);
                        }
                        else {
                            fwrite($this->stderr, 'Warning: --recursive: Found '.$dir.$folderObject.' but it is not writeable or readable!'.PHP_EOL);
                        }
                    }
                }
            }

            if(count($srcs) > 0) {
                endFolder();
            }
        }


        function checkForOtherFiles(&$test) {
            $test['rc'] = $this->findFile($test, 'rc', Options::$generatedRCContent);
            $test['out'] = $this->findFile($test, 'out');
            $test['in'] = $this->findFile($test, 'in');
        }


        function findFile($test, $extension, $autoContent = null) {
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

        function invalidFile($path) {
            fwrite($this->stderr, "Error: Cannot acces given file '{$path}'!".PHP_EOL);
            exit(BAD_PATH_GIVEN);
        }
    }
?>    
