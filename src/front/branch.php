<?php

namespace phs\front;

/**
 * a branch is a scope for temporary symbols
 * inside a 'real' scope.
 * 
 * e.g.
 * 
 * let bar = 1;        <- saved to unit-branch
 * fn foo()            <- function creates a scope
 * {                   <- block creates a branch
 *   bar = 2;          <- bar gets copied to this branch
 * }                   <- branch gets removed
 * 
 */

/** branch scope */
class Branch extends Scope
{
  // @var Scope original scope
  public $orig;
  
  /**
   * constructor
   *
   * @param Scope $orig
   */
  public function __construct(Scope $orig)
  {
    parent::__construct(null);
    $this->orig = $orig;
  }
  
  /**
   * @see Scope#enter() 
   * @return void
   */
  public function enter()
  {
    $this->orig->enter();
  }
  
  /**
   * @see Scope#leave()
   * @return void
   */
  public function leave()
  {
    $this->orig->leave();
  }
  
  /**
   * @see Scope#add()
   * @param Symbol $sym
   */
  public function add(Symbol $sym)
  {
    // TODO: this is pretty inefficient
    
    if ($this->orig->add($sym)) {
      // move symbol from orig scope to branch
      $this->orig->delete($sym->id, $sym->ns);
      return parent::add($sym);
    }
    
    return false;
  }
  
  /**
   * fetch a symbol
   *
   * @param  string  $key
   * @param  integer $ns
   * @return Symbol
   */
  public function get($key, $ns = -1)
  {
    $sym = parent::get($key, $ns);
    
    if (!$sym) {
      // move symbol from orig scope to branch
      $sym = $this->orig->get($key, $ns);
      
      if (!$sym) return null;
      
      $sym = clone $sym;
      $this->put($sym);
    }
    
    return $sym;
  }
}

/** root branch */
abstract class RootBranch extends Branch
{
  // @var ModuleMap (sub-)modules reference
  public $mmap;
  
  // @var boolean  reference to root-scope "active" prop
  public $active;
  
  /**
   * constructor
   *
   * @param RootScope $orig
   */
  public function __construct(RootScope $orig)
  {
    parent::__construct($orig);
    $this->mmap = &$orig->mmap;
    $this->active = &$orig->active;
  }
}

/** unit branch */
class UnitBranch extends RootBranch
{  
  // @var Unit  the unit reference
  public $unit;
  
  // @var string  file-path reference
  public $file;
  
  /**
   * constructor
   *  
   * @param UnitScope  $orig
   */
  public function __construct(UnitScope $orig)
  {
    parent::__construct($orig);
    $this->unit = &$orig->unit;
    $this->file = &$orig->file;
  }
}

/** module branch */
class ModuleBranch extends RootScope
{
  // @var string  module-id reference
  public $id;
  
  /**
   * constructor
   * 
   * @param string    $id 
   * @param RootScope $prev
   */
  public function __construct(RootScope $orig)
  {
    parent::__construct($orig);
    $this->id = &$orig->id;
  }
  
  /**
   * delegates to the original module
   * 
   * @return array
   */
  public function path()
  {
    return $this->orig->path();
  }
}
