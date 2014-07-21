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
  
  /**
   * constructor
   * @param Session $sess
   */
  public function __construct(Session $sess)
  {
    $this->sess = $sess;
    $this->srcs = new SourceSet;
    $this->units = new Set;
    
    $this->parser = new Parser($this->sess);
    $this->analyzer = new Analyzer($this->sess);
  }
  
  /**
   * add a unit (ast)
   * @param Unit $unit
   */
  public function add_unit(Unit $unit)
  {
    $this->units->add($unit);
  }
  
  /**
   * add a source
   * @param Source $src
   */
  public function add_source(Source $src)
  {
    $this->srcs->add($src);
  }
  
  /**
   * compiles all sources/units
   * @return void
   */
  public function compile()
  {    
    // phase 1
    $this->phase('syntax analysis', function() {      
      // parse all sources
      foreach ($this->srcs as $src) {
        $unit = $this->parse($src);
        
        if ($unit)
          $this->units->add($unit);
        
        // ignore result and continue parsing to 
        // report as much errors as possible
      }
    });
    
    if ($this->sess->aborted) return;
    
    // phase 2
    $this->phase('semantic analysis', function() {
      // analyze all units    
      foreach ($this->units as $unit)
        $ares = $this->analyze($unit);
    });
  }
  
  /* ------------------------------------ */
  
  /**
   * does a compile-phase callback
   *
   * @param  string   $type
   * @param  callable $func
   * @return void
   */
  protected function phase($type, callable $func)
  {    
    $time = microtime(true);
    $func();
    $done = microtime(true) - $time;
    
    Logger::debug('%s took %fs', $type, $done);
    
    if ($this->sess->aborted)
      Logger::debug('aborted', $type);
  }
  
  /* ------------------------------------ */
  
  /**
   * parses a source and generates an ast
   * @param  Source $src
   * @return Unit
   */
  protected function parse(Source $src)
  {
    $lex = new Lexer($src);
    return $this->parser->parse($lex);    
  }
  
  /**
   * analyzes a unit
   * @param  Unit $unit
   * @return Analysis
   */
  protected function analyze(Unit $unit)
  {
    $anl = new Analyzer($this->sess);
    return $anl->analyze($unit);
  }
}
