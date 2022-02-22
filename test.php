<?php
    define('BAD_PATH_GIVEN', 41);
    
    $lOpts = array('help', 'directory=::', 'recursive', 'parse-script=::', 'int-script=::', 'parse-only', 'int-only', 'jexampath=::', 'noclean');
    $sOpts = '';
    $args = getopt($sOpts, $lOpts);


?>