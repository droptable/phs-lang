<?php

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

use phs\front\Lexer;
use phs\front\Parser;

use phs\front\UseCollector;

function main() {
  $config = new Config;
  $config->set_defaults();
  
  Logger::init($config);
  
  $sess = new Session;
  
  $src = new FileSource(__DIR__ . '/test/test.phs');
  $ast = phase_1($sess, $src);
  $ast = phase_2($sess, $ast);
}

function phase_1($sess, $src) {
  $lex = new Lexer($src);
  $psr = new Parser;
  
  $ast = $psr->parse($lex);  
  return $ast;
}

function phase_2($sess, $ast) {
  $use = new UseCollector;
  $map = $use->collect($sess, $ast);
  
  #foreach ($map as $imp)
    #import($imp);
  
  
}

main();
