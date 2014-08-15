<?php

namespace phs\front;

require_once 'utils.php';
require_once 'symbols.php';

use phs\Logger;
use phs\Session;

use phs\util\Set;
use phs\util\Map;
use phs\util\Entry;

use phs\front\ast\Node;
use phs\front\ast\Unit;

/** scope */
class Scope extends SymbolMap
{
  // @var int  unique id
  public $uid;
  
  // @var boolean
  public $root;
  
  // @var Scope  parent scope
  public $prev;
  
  // @var Set  captured symbols from the parent scope
  public $capt;
  
  // @var boolean
  public $sealed = false;
  
  // unique id counter
  private static $uidcnt = 0;
  
  // access constants for the assigment-handler
  const MADD = 1, MPUT = 2;
  
  /**
   * constructor
   * 
   * @param Scope $prev
   */
  public function __construct(Scope $prev = null)
  {
    parent::__construct();
    
    $this->uid = self::$uidcnt++;
    $this->capt = new SymbolSet;
    $this->prev = $prev;
    $this->root = $prev === null; // <- no parent scope
  }
  
  /**
   * returns a symbol
   * 
   * @param  string  $key
   * @parsm  int     $ns  namespace
   * @return Symbol
   */
  public function get($key, $ns = -1)
  {
    $sym = parent::get($key, $ns);
    
    if ($sym === null && $this->prev && !$this->sealed) {
      $sym = $this->prev->get($key, $ns);
      
      // if found, mark symbol as captured
      if ($sym !== null)
        $this->capt->add($sym);
    }
    
    return $sym;
  }
  
  /**
   * add symbol
   * 
   * @param Symbol $sym
   * @return boolean
   */
  public function add(Symbol $sym)
  {
    Logger::debug_at($sym->loc, 'adding symbol "%s"', $sym->id);
    $prv = $this->get($sym->id, $sym->ns);
    
    if ($prv !== null) {
      // incomplete
      if ($prv->flags & SYM_FLAG_INCOMPLETE) {
        // must be the same kind of symbol
        if ($sym->kind !== $prv->kind) {
          Logger::error_at($sym->loc, 'refinement type mismatch');
          Logger::error_at($prv->loc, 'incomplete declaration was here');
          return false;
        }
        
        // a incomplete symbol must not replace an 
        // existing incomplete symbol in the same scope-chain
        if ($sym->flags & SYM_FLAG_INCOMPLETE)
          // do nothing in this case
          return true;
        
        // check flags/modifiers
        if ($sym->flags !== SYM_FLAG_NONE && 
            $sym->flags !== ($prv->flags ^ SYM_FLAG_INCOMPLETE)) {
          Logger::error_at($sym->loc, 'refinement modifier(s) mismatch');
          Logger::error_at($prv->loc, 'incomplete declaration was here');
          return false;
        }

        // merge flags
        $sym->flags |= $prv->flags;
        $sym->flags &= ~SYM_FLAG_INCOMPLETE;
        
        // replace previous symbol
        $prv->scope->put($sym);
        return true;
      }
      
      // shadowing of final smbol
      if ($prv->flags & SYM_FLAG_FINAL) {
        Logger::error_at($sym->loc, 'override of final symbol `%s`', $sym->id);
        Logger::error_at($prv->loc, 'previous symbol was here');
        return false;
      }
      
      // override check
      if ($prv->scope === $this) {
        Logger::error_at($sym->loc, 'redefinition of symbol `%s`', $sym->id);
        Logger::error_at($prv->loc, 'previous symbol was here');
        return false;
      }
    }
    
    $res = parent::add($sym);
    assert($res); // must be true, see "override check"
    
    $sym->scope = $this;
    return true;
  }
  
  /**
   * put a symbol.
   * warning: does not check anything, always prefer add() !!!
   * 
   * @param Symbol $sym
   * @return Symbol  the previous symbol
   */
  public function put(Symbol $sym)
  {
    $prv = parent::put($sym);
    $sym->scope = $this;
      
    if ($prv !== null) 
      $prv->scope = null;
      
    return $prv;
  }
}

/** root-scope: common class for scopes with (sub-)modules and/or usage */
abstract class RootScope extends Scope
{
  // @var ModuleMap (sub-)modules
  public $mmap;
  
  public function __construct(Scope $prev = null)
  {
    parent::__construct($prev);
    $this->mmap = new ModuleMap;
  }
  
  /* ------------------------------------ */
  
  /**
   * debug dump
   *
   * @return void
   */
  public function dump($tab = '')
  {
    assert(PHS_DEBUG);
        
    // modules
    foreach ($this->mmap as $mod) 
      $mod->dump($tab . '  ');
    
    // symbols
    parent::dump($tab);
  }
}

/** unit scope */
class UnitScope extends RootScope
{  
  // @var Unit  the unit
  public $unit;
  
  // @var string  file-path
  public $file;
  
  /**
   * constructor
   *    
   */
  public function __construct(Unit $unit)
  {
    parent::__construct(null);
    $this->unit = $unit;
    $this->file = $unit->loc->file;
  }
  
  public function dump($tab = '')
  {
    echo "\n<unit> @ ", $this->file;
    parent::dump($tab);
  }
}

/** unit-scope set */
class UnitScopeSet extends Set
{
  /**
   * constructor
   * 
   */
  public function __construct()
  {
    // super
    parent::__construct();
  }
  
  /**
   * typecheck
   *
   * @param  mixed $ent
   * @return boolean
   */
  protected function check($ent)
  {
    return $ent instanceof Unit;
  }
}

/** module scope */
class ModuleScope extends RootScope implements Entry
{
  // @var string  module-id
  public $id;
  
  /**
   * constructor
   * 
   * @param string    $id 
   * @param RootScope $prev
   */
  public function __construct($id, RootScope $prev)
  {
    // $prev is not optional
    // a module must be defined in a unit or in a other module
    parent::__construct($prev);
    
    $this->id = $id;
  }
  
  /**
   * @see Entry#key()
   * @return string
   */
  public function key()
  {
    return $this->id;
  }
  
  /**
   * returns the absolute path of this module
   * 
   * @return array
   */
  public function path()
  {
    $path = [];
    
    // walk up to root
    for ($prev = $this->prev; 
         $prev instanceof self; 
         $prev = $prev->prev)
      $path[] = $prev->id;
    
    // reverse for the correct order
    $path = array_reverse($path);
    
    // add own name
    $path[] = $this->id;
    
    // done
    return $path;
  }
  
  public function dump($tab = '')
  {
    echo "\n", $tab, '# ', $this->id;
    parent::dump($tab);
  }
}

/** Map<ModuleScope> */
class ModuleScopeMap extends Map
{
  /**
   * constructor
   */
  public function __construct()
  {
    parent::__construct();
  }
  
  /**
   * @see Map#check()
   * @param  Entry  $ent
   * @return boolean
   */
  protected function check(Entry $ent)
  {
    return $ent instanceof ModuleScope;
  }
}

class_alias(__NAMESPACE__ . '\\ModuleScopeMap', __NAMESPACE__ . '\\ModuleMap');

/** member scope */
class MemberScope extends Scope
{  
  /**
   * constructor
   *
   * @param Scope $prev
   */
  public function __construct(Scope $prev)
  {
    parent::__construct($prev);
    $this->sealed = true;
  }
}

/* ------------------------------------ */

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

abstract class RootBranch extends Branch
{
  // @var ModuleMap (sub-)modules reference
  public $mmap;
  
  public function __construct(RootScope $orig)
  {
    parent::__construct($orig);
    $this->mmap = &$orig->mmap;
  }
}

class UnitBranch extends RootBranch
{  
  // @var Unit  the unit reference
  public $unit;
  
  // @var string  file-path reference
  public $file;
  
  /**
   * constructor
   *    
   */
  public function __construct(UnitScope $orig)
  {
    parent::__construct($orig);
    $this->unit = &$orig->unit;
    $this->file = &$orig->file;
  }
}

/** module scope */
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
