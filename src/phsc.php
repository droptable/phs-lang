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

function main() {
  $conf = new Config;
  $conf->set_defaults();
  
  Logger::init($conf);
  
  $sess = new Session($conf);
  $comp = new Compiler($sess);
  $comp->add_source(new FileSource(__DIR__ . '/test/test.phs'));
  $comp->compile();
}

function phase_1($sess, $src) {
  $lex = new Lexer($src);
  $psr = new Parser;
  
  return $psr->parse($lex);  
}

function phase_2($sess, $unit) {
  $use = new ImportCollector($sess);
  $unit->meta->imports = $use->collect($unit);
  
  
  
  $exp = new ExportCollector($sess);
  $unit->meta->exports = $exp->collect($unit);
}

function debug_emap($map, $tab = '') {
  foreach ($map as $itm) {
    print "$tab{$itm->name}\n";
    
    if ($itm instanceof \phs\front\ModuleExport)
      debug_emap($itm->exmap, "{$tab}-> ");
  }
}

main();
