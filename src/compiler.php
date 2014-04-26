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

/** source-set */
class SourceSet extends Set
{
  /**
   * constructor
   */
  public function __construct()
  {
    parent::__construct();
  }
  
  /**
   * adds an item
   * 
   * @see Set#add()
   */
  public function add($val)
  {
    assert($val instanceof Source);
    
    if ($val instanceof FileSource)
      foreach ($this as $itm) {
        if (!($itm instanceof FileSource))
          continue;
        
        if ($itm->get_name() === $val->get_name())
          return false;
      }
    
    return parent::add($val);
  }
}

/** unit-set */
class UnitSet extends Set
{
  public function __construct()
  {
    parent::__construct();
  }
  
  public function add($val)
  {
    assert($val instanceof Unit);
    return parent::add($val);
  }
}

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
    $this->srcs = new SourceSet;
    $this->units = new UnitSet;
    
    $this->parser = new Parser($this->sess);
    $this->analyzer = new Analyzer($this->sess);
  }
  
  public function add_unit(Unit $unit)
  {
    $this->units->add($unit);
  }
  
  public function add_source(Source $src)
  {
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
    foreach ($this->units as $unit)
      $this->analyze($unit); 
    
    // phase 3: -> backend!
    
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
    return $this->analyzer->analyze($unit);
  }
}
