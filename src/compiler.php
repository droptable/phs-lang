<?php

namespace phs;

require_once 'util/set.php';
require_once 'util/map.php';

use phs\util\Set;
use phs\util\Map;

require_once 'front/ast.php';
require_once 'front/lexer.php';
require_once 'front/parser.php';
require_once 'front/analyze.php';

use phs\front\Lexer;
use phs\front\Parser;
use phs\front\Analyzer;
use phs\front\Analysis;

use phs\front\ast\Node;
use phs\front\ast\Unit;

class Compiler
{
  // session
  private $sess;
  
  // sources
  private $srcs;
  
  // added units
  private $units;
  
  // parser component
  private $parser;
  
  // ast-check component
  private $revisor;
  
  // analyzer component
  private $analyzer;
  
  public function __construct(Session $sess)
  {
    $this->sess = $sess;
    $this->srcs = new Set;
    $this->units = new Set;
    
    $this->parser = new Parser($this->sess);
    $this->analyzer = new Analyzer($this->sess);
  }
  
  public function add_unit(Unit $unit)
  {
    $this->units->add($unit);
  }
  
  public function add_source($src)
  {
    if (!($src instanceof Source))
      $src = Source::from($src);
    
    $this->srcs->add($src);
  }
  
  public function compile()
  {    
    // phase 1: parse all sources
    foreach ($this->srcs as $src) {
      $unit = $this->parse($src);
      
      if ($unit)
        $this->units->add($unit);
      
      // ignore result and continue parsing to 
      // report as much errors as possible
    }
    
    if ($this->sess->abort) {
      Logger::debug('abort after phase 1');
      return;
    }
    
    // phase 2: analyze unit    
    foreach ($this->units as $unit) {
      $ares = $this->analyze($unit); 
      var_dump($ares->usage);
    }
    
    // phase 3: codegen modules
    
    if ($this->sess->abort) {
      Logger::debug('abort after phase 2');
      return;
    }
  }
  
  /* ------------------------------------ */
  
  protected function parse($src)
  {
    $lex = new Lexer($src);
    return $this->parser->parse($lex);    
  }
  
  protected function analyze($unit)
  {
    $anl = new Analyzer($this->sess);
    return $anl->analyze($unit);
  }
}
