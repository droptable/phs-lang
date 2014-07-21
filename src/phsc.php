<?php

require_once 'config.php';
require_once 'logger.php';
require_once 'source.php';
require_once 'session.php';
require_once 'compiler.php';

require_once 'front/lexer.php';
require_once 'front/parser.php';
require_once 'front/analyze.php';

#require_once 'back/optimizer.php';
#require_once 'back/codegen.php';

use phs\Config;
use phs\Logger;
use phs\Session;
use phs\Compiler;
use phs\FileSource;

use phs\front\Lexer;
use phs\front\Parser;

use phs\front\ImportCollector;
use phs\front\ExportCollector;

assert_options(ASSERT_ACTIVE, true);
assert_options(ASSERT_BAIL, true);
assert_options(ASSERT_CALLBACK, function($s, $l, $m = null) {
  $w = $m ? " with message: $m" : '!';
  print "\nassertion failed$w\n\nfile: $s\nline: $l\n";
  exit;
});

function init(Session $sess) {
  $conf = $sess->conf;
  
  Logger::init($conf);  
  Logger::hook(phs\LOG_LEVEL_ERROR, [ $sess, 'abort'] );
  
  if ($conf->get('werror') === true)
    Logger::hook(phs\LOG_LEVEL_WARNING, [ $sess, 'abort' ]);
  
  Logger::debug('initialized');
}

function main() {
  
  $path = new FileSource(__DIR__ . '/test/test.phs');
  $root = dirname($path->get_path());
  
  $conf = new Config;
  $conf->set_defaults();
  $sess = new Session($conf, $root);
  
  init($sess);
  
  $comp = new Compiler($sess);
  $comp->add_source($path);
  $comp->compile();
}

main();
