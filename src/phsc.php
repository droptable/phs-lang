<?php

define('PHS_DEBUG', true);
define('PHS_STDLIB', realpath(__DIR__ . '/../lib'));

require_once 'config.php';
require_once 'logger.php';
require_once 'source.php';
require_once 'session.php';

require_once 'front/lexer.php';
require_once 'front/parser.php';
require_once 'front/analyze.php';

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
  exit;
});

function init(Session $sess) {
  $conf = $sess->conf;
  
  Logger::init($conf, $sess->root, !in_array('--no-colors', $_SERVER['argv']));  
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
  
  $sess->add_source($path);
  $sess->compile();
}

main();
