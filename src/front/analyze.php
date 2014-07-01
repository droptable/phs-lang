<?php

namespace phs\front;

require_once 'utils.php';
require_once 'walker.php';
require_once 'visitor.php';
require_once 'symbols.php';
require_once 'scope.php';
require_once 'module.php';
require_once 'collect.php';

use phs\Config;
use phs\Logger;
use phs\Session;

use phs\util\Set;
use phs\util\Map;
use phs\util\Cell;
use phs\util\Entry;

use phs\front\ast\Node;
use phs\front\ast\Unit;

class Analysis 
{
  // @var Unit  the unit in question
  public $unit;
  
  // @var Scope
  public $types;
  
  // @var UsageMap
  public $usage;
  
  // @var ModuleMap
  public $modules;
  
  /**
   * constructor
   * 
   * @param Unit $unit
   */
  public function __construct(Unit $unit) 
  {
    $this->unit = $unit;
  }
}

/** analyzer */
class Analyzer extends Visitor
{
  // @var Session  session
  private $sess;
  
  // @var Scope  scope
  private $scope;
  
  // @var Scope  root scope
  private $sroot;
  
  // @var Walker  walker
  private $walker;
    
  // @var TypeCollector
  private $tcl;
  
  // @var UsageCollector
  private $ucl;
  
  /**
   * constructor
   * 
   * @param Session $sess
   */
  public function __construct(Session $sess)
  {
    parent::__construct();
    $this->sess = $sess;
    
    // collectors
    $this->tcl = new TypeCollector;
    $this->ucl = new UsageCollector;
  }
  
  /**
   * starts the analyzer
   * 
   * @param  Unit   $unit
   * @return Analysis
   */
  public function analyze(Unit $unit)
  {    
    $anl = new Analysis($unit);
    $anl->usage = $this->collect_usage($unit);    
    $anl->types = $this->collect_types($unit);
    $anl->modules = $this->collect_modules($unit);
    
    return $anl;   
  }
  
  protected function collect_usage(Unit $unit) 
  {
    return $this->ucl->collect($unit);
  }
  
  public function visit_unit($node)
  {
    $this->walker->walk_some($node->body);
  }
  
  public function visit_module($node)
  {
    $prev = $this->scope;
    
    if ($node->name) {
      $base = $this->scope;
      $name = name_to_arr($node->name);
      
      if ($node->name->root)
        $base = $this->sroot; // use unit-scope
      
      $mmap = null;
      $nmod = null; // assume no parent module by default
      
      if ($base instanceof UnitScope)
        // global unit-scope
        $mmap = $base->mmap;
      else {
        // must be inside a module
        assert($base instanceof Module);
        $mmap = $base->subm; // use submodules
        $nmod = $base;
      }
      
      foreach ($name as $mid) {
        if ($mmap->has($mid))
          // fetch sub-module
          $nmod = $mmap->get($mid);
        else {
          // create and assign a new module
          $nmod = new Module($mid, $nmod);
          $mmap->add($newm);
        }
        
        $mmap = $nmod->subm;
      }
      
      $this->scope = $nmod;
    } else 
      // switch to global scope
      $this->scope = $this->sroot;
    
    $this->walk_some($node->body);
    $this->scope = $prev;
  }
  
  public function visit_content($node) {
    // collect types
    $this->tcl->collect($node->body, $this->scope);
    // collect usage
    $this->ucl->collect($node->uses, $this->scope);
    
    // continue walking
    $this->walker->walk_some($node->body);
  }
  
  public function visit_fn_decl($node)
  {
    $sym = FnSymbol::from($node);    
    $this->scope->add($sym); 
  }
}

