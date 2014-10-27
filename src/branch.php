<?php

namespace phs;

/** branch scope */
class Branch extends Scope
{  
  // @var Scope  original scope
  public $orig;
  
  /**
   * constructor
   *
   * @param Scope $prev
   */
  public function __construct(Scope $orig, Branch $prev = null)
  {
    // super
    parent::__construct($prev);
    $this->orig = $orig;
  }
    
  // forward enter() to the original scope
  public function enter()
  {
    $this->orig->enter();
  }
  
  // forward leave() to the original scope
  public function leave()
  {
    $this->orig->leave();
  }
    
  /**
   * @see Scope#add()
   * @param  Symbol $sym
   * @return boolean
   */
  public function add(Symbol $sym)
  {
    Logger::error('[bug] can not add symbols to a branch');
    Logger::info('symbol name was `%s`', $sym->id);
    assert(0);
    return false;
  }
  
  /**
   * @see Scope#get()
   * @param  string  $id
   * @param  integer $ns
   * @return ScResult
   */
  public function get($id, $ns = -1)
  {
    $res = parent::get($id, $ns);
    
    // copy the symbol if its a var
    if ($res->is_some()) {
      $sym = $res->unwrap();
      
      // do not copy symbol if it was deoptimized
      // or has the constant-flag
      if ($sym->deopt || $sym->flags & SYM_FLAG_CONST) 
        return $res;
      
      $dup = clone $sym;
      $this->put($dup);
      
      return ScResult::Some($dup);
    }
      
    if ($res->is_priv())
      return $res;
    
    // delegate to original scope
    return $this->orig->get($id, $ns);
  }
  
  /**
   * @see Scope#has()
   * @param  string  $id
   * @param  integer $ns
   * @return boolean
   */
  public function has($id, $ns = -1)
  {
    return parent::has($id, $ns) || 
      $this->orig->has($id, $ns);
  }
}

/** root branch */
abstract class RootBranch extends Branch
{
  // @var UsageMap
  public $umap;
  
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
    $this->umap = &$orig->umap;
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
class ModuleBranch extends RootBranch
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

/** conditional branch (used for loops/switch and if/elsif) */
class CondBranch extends Branch 
{
  /**
   * @see Branch#get()
   * @param  string  $id
   * @param  integer $ns
   * @return ScResult
   */
  public function get($id, $ns = -1)
  {
    $res = parent::get($id, $ns);
    
    // all symbols get copied to the conditional branch
    // and the original symbols lose their value used to 
    // fold/reduce constant expressions
    
    if ($res->is_some()) {
      $sym = $res->unwrap();
      
      if ($sym->kind === SYM_KIND_VAR && 
          //$sym->scope !== $this && 
          $sym->deopt !== true) {
        
        $dup = clone $sym;
        $this->put($dup);
        $res = ScResult::Some($dup);
        
        // mark original value as "unknown" 
        // and deoptimize the symbol
        $sym->value = Value::$UNDEF;
        $sym->deopt = true;
      }
    }
    
    return $res;
  }
}
