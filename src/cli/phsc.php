<?php

define('PHS_DEBUG', true);

chdir(__DIR__ . '/..');

require_once 'config.php';
require_once 'logger.php';
require_once 'source.php';
require_once 'session.php';

#require_once 'back/optimizer.php';
#require_once 'back/codegen.php';

use phs\Config;
use phs\Logger;
use phs\Session;
use phs\FileSource;

assert_options(ASSERT_ACTIVE, true);
assert_options(ASSERT_BAIL, true);
assert_options(ASSERT_CALLBACK, function($s, $l, $c, $m = null) {
  echo "\nassertion failed", $m ? " with message: $m" : '!', "\n";
  echo "\nfile: $s\nline: $l\n", $c ? "code: $c\n" : ''; 
  echo "\n";
  debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
  exit;
});

set_error_handler(function($n, $s, $f, $l) {
  echo "\nerror: $s ($n)\nfile: $f\nline: $l\n\n";
  debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
  exit;
});

function init(Config $conf, $root) {
  $qarg = in_array('-q', $_SERVER['argv']);
  
  if ($qarg || in_array('--nologo', $_SERVER['argv']))
    $conf->set('nologo', true);
  
  if (in_array('--nostd', $_SERVER['argv']))
    $conf->set('nostd', true);
  
  if ($qarg || in_array('--nodebug', $_SERVER['argv']))
    $conf->set('log_level', phs\LOG_LEVEL_WARNING);
  
  if ($conf->get('nologo') === false)
      echo <<<END_LOGO
     ___  __ ______
    / _ \/ // / __/
   / ___/ _  /\ \  
  /_/  /_//_/___/   Version 0.1a1
  
  Copyright (C) 2014 - The PHS Team.
  Report bugs to http://ggggg.de/issues
  
END_LOGO;
  
  Logger::init($conf, $root, !in_array('--nocolors', $_SERVER['argv']));    
}

function main() {  
  $path = new FileSource(__DIR__ . '/../test/test.phs');
  $root = dirname($path->get_path());
  
  $conf = new Config;
  $conf->set_defaults();
   
  init($conf, $root);
  
  $sess = new Session($conf, $root);
  Logger::hook(phs\LOG_LEVEL_ERROR, [ $sess, 'abort'] );
  
  if ($conf->get('werror') === true)
    Logger::hook(phs\LOG_LEVEL_WARNING, [ $sess, 'abort' ]);
  
  if (in_array('-v', $_SERVER['argv'])) {
    if ($conf->get('nologo'))
      print "PHS 0.1a1 (c) 2014 - The PHS Team";
    exit; 
  }
  
  // phs-runtime
  $sess->add_library(new FileSource(__DIR__ . '/../../lib/run.phs'));
  
  // phs-stdlib
  #if ($conf->get('nostd', false) === false)
    #$sess->add_library(new FileSource(__DIR__ . '/../../lib/std.phs'));
  
  $sess->add_source($path);
  $sess->process();
}

main();
