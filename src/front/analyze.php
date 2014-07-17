<?php

namespace phs\front;

require_once 'utils.php';
require_once 'walker.php';
require_once 'visitor.php';
require_once 'symbols.php';
require_once 'scope.php';
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
  
  /**
   * constructor
   * 
   * @param Session $sess
   */
  public function __construct(Session $sess)
  {
    parent::__construct();
    $this->sess = $sess;
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
    
    // collect global usage
    $anl->usage = $this->collect_unit_usage($unit);    
    
    // collect global types
    $anl->types = $this->collect_unit_types($unit);
    
    
    //$anl->modules = $this->collect_modules($unit);
    
    return $anl;
  }
  
  protected function collect_unit_usage(Unit $unit) 
  {
    $ucl = new UsageCollector($this->sess);
    return $ucl->collect_unit($unit);
  }
  
  protected function collect_unit_types(Unit $unit)
  {
    $tcl = new TypeCollector($this->sess);
    return $tcl->collect_unit($unit);
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
      
      // must be inside a unit or a module
      assert($base instanceof RootScope);
      $mmap = $base->mmap;
      
      foreach ($name as $mid) {
        if ($mmap->has($mid))
          // fetch sub-module
          $nmod = $mmap->get($mid);
        else {
          // create and assign a new module
          $nmod = new ModuleScope($mid, $nmod);
          $mmap->add($newm);
        }
        
        $mmap = $nmod->mmap;
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

