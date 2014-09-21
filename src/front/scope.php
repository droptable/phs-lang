<?php

namespace phs\front;

require_once 'utils.php';
require_once 'usage.php';
require_once 'symbols.php';

use \ArrayIterator;
use \AppendIterator;

use phs\Logger;
use phs\Session;

use phs\util\Set;
use phs\util\Map;
use phs\util\Entry;
use phs\util\Result;

use phs\front\ast\Node;
use phs\front\ast\Unit;
use phs\front\ast\Name;
use phs\front\ast\Ident;

// flags returned from Scope#check()
const
  CHK_RES_ERR = 1,  // error
  // TODO: more error flags
  
  CHK_RED_OK   = 2,  // okay
  CHK_RES_NOP  = 6,  // okay, no action required          (4|2)
  CHK_RES_ADD  = 10, // okay, symbol can be added         (8|2)
  CHK_RES_REPL = 18  // okay, symbol should be replaced  (16|2)
;

/** scope result */
class ScResult extends Result
{
  // symbol is private
  public $priv = false;
  
  /**
   * returns true if this result failed because the 
   * requested symbol is private
   *
   * @return boolean
   */
  public function is_priv()
  {
    return $this->priv === true;
  }
  
  /**
   * generates a "fail" result
   * 
   * @return Result
   */
  public static function Priv($data)
  {
    $res = static::Error($data);
    $res->priv = true;
    return $res;
  }
}

/** scope */
class Scope extends SymbolMap
{
  // @var int  unique id
  public $uid;
  
  // @var boolean
  public $root;
  
  // @var Scope  parent scope
  public $prev;
  
  // @var array  references
  public $refs;
  
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
    $this->refs = [];
  }
  
  public function __tostring()
  {
    return '<scope>';
  }
  
  /**
   * gets called if this scope is the current scope
   *
   * @return void
   */
  public function enter() {}
  
  /**
   * gets called if this scope is no longer the current scope
   *
   * @return void
   */
  public function leave() {}
  
  /**
   * adds a reference
   *
   * @param Name|Ident $node
   */
  public function ref($node)
  {
    assert($node instanceof Name ||
           $node instanceof Ident);
    
    $uid = null;
    
    if ($node instanceof Name)
      $uid = name_to_str($node);
    else
      $uid = ident_to_str($node);
    
    $this->refs[$uid] = $node;
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
    $res = null;
    
    if ($sym === null && $this->prev && !$this->sealed) {
      $res = $this->prev->get($key, $ns);
      
      // if found, mark symbol as captured
      if ($res->is_some())
        $this->capt->add($res->unwrap());
      
    } else
      $res = ScResult::from($sym);
    
    return $res;
  }
  
  /**
   * add symbol
   * 
   * @param Symbol $sym
   * @return boolean
   */
  public function add(Symbol $sym)
  {
    #Logger::debug('adding %s (check=%d) <%s>', 
      #$sym->id, $this->check($sym, false), get_class($this));
    
    switch ($this->check($sym)) {
      // error
      case CHK_RES_ERR:
        return false;
      
      // no action required
      case CHK_RES_NOP:
        return true;
        
      // replace
      case CHK_RES_REPL:
        $res = $this->get($sym->id, $sym->ns);
        assert($res->is_some());
        
        $prv = &$res->unwrap();        
        $prv->scope->put($sym);
        return true;
        
      // add
      case CHK_RES_ADD:
        $res = parent::add($sym);
        if ($res) $sym->scope = $this;
        return $res;
        
      default:
        assert(0);
    }
  }
   
  /**
   * checks if a symbol can be added to this scope
   *
   * @param  Symbol $sym
   * @param  boolean $rant
   * @return int
   */
  public function check(Symbol $sym, $rant = true)
  {      
    $res = $this->get($sym->id, $sym->ns);
    
    if ($res->is_some()) {
      $prv = &$res->unwrap();
      
      // managed
      if ($prv->managed) {
        Logger::error_at($sym->loc, 'can not override or shadow managed %s', $prv);
        return CHK_RES_ERR;
      }
      
      // incomplete
      if ($prv->flags & SYM_FLAG_INCOMPLETE) {
        // must be the same kind of symbol
        if ($sym->kind !== $prv->kind) {
          if ($rant === true) {
            Logger::error_at($sym->loc, 'cannot refine %s with %s', $prv, $sym);
            Logger::error_at($prv->loc, 'incomplete declaration of %s was here', $prv);
          }
          return CHK_RES_ERR;
        }
        
        // a incomplete symbol must not replace an 
        // existing incomplete symbol in the same scope-chain
        if ($sym->flags & SYM_FLAG_INCOMPLETE)
          // do nothing in this case
          return CHK_RES_NOP;
        
        // check flags/modifiers
        if (// $sym->flags !== SYM_FLAG_NONE && 
            $sym->flags !== ($prv->flags ^ SYM_FLAG_INCOMPLETE)) {
          if ($rant === true) {
            Logger::error_at($sym->loc, 'refinement modifier(s) mismatch of %s', $sym);
            
            $diff = sym_flags_diff($prv->flags, $sym->flags);
            
            if ($diff->add)
              Logger::error_at($sym->loc, 'modifiers added: %s',
                sym_flags_to_str($diff->add));
            
            if ($diff->del)
              Logger::error_at($sym->loc, 'modifiers missing: %s',
                sym_flags_to_str($diff->del));
            
            Logger::error_at($prv->loc, 'incomplete declaration of %s was here', $prv);           
          }
          return CHK_RES_ERR;
        }
        
        // should be replaced
        return CHK_RES_REPL;
      }
      
      // shadowing of final symbol
      if ($prv->flags & SYM_FLAG_FINAL) {
        if ($rant === true) {
          Logger::error_at($sym->loc, 'override (shadowing) of final %s', $prv);
          Logger::error_at($prv->loc, 'definition of %s was here', $prv);
        }
        return CHK_RES_ERR;
      }
      
      // override check
      if ($prv->scope === $this) {
        if ($rant === true) {
          Logger::error_at($sym->loc, 'cannot define %s in this scope', $sym);
          Logger::error_at($prv->loc, '%s (same name) already defined here', $prv);
        }
        return CHK_RES_ERR;
      }
    }
    
    // symbol can be added
    return CHK_RES_ADD;
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
  
  /**
   * checks if a symbol is reachable from this scope
   *
   * @param  string  $id
   * @param  integer $ns
   * @return boolean
   */
  public function has($id, $ns = -1)
  {
    if (parent::has($id, $ns))
      return true;
    
    if ($this->prev)
      return $this->prev->has($id, $ns);
    
    return false;
  }
  
  /* ------------------------------------ */
  
  public function dump($tab = '')
  {
    foreach ($this->refs as $ref => $_)
      echo "\n", $tab, '  * ', $ref;
    
    parent::dump($tab);
  }
}

/** root-scope: common class for scopes 
    with (sub-)modules and/or private symbols */
abstract class RootScope extends Scope
{
  // @var UsageMap  usage of this scope
  public $umap;
  
  // @var ModuleMap (sub-)modules
  public $mmap;
  
  // @var Scope inner scope (for private symbols)
  public $inner;
  
  // @var boolean  whenever this scope is active
  public $active = false;
  
  // @var int  nesting level
  private $nest = 0;
  
  /**
   * constructor
   *
   * @param Scope $prev
   */
  public function __construct(Scope $prev = null)
  {
    parent::__construct($prev);
    $this->umap = new UsageMap;
    $this->mmap = new ModuleMap;
    $this->inner = new Scope;
  }
  
  /**
   * "enter" scope
   *
   * @return void
   */
  public function enter()
  {
    parent::enter();
    $this->active = true;
  }
  
  /**
   * "leave" scope
   *
   * @return void
   */
  public function leave()
  {
    parent::leave();
    $this->active = false;
  }
  
  /**
   * @see Scope#get()
   * @param  string  $id
   * @param  integer $ns
   * @return Symbol
   */
  public function get($id, $ns = -1)
  {    
    // try public scope first
    $res = parent::get($id, $ns);
    
    if ($res->is_none()) {
      // try private scope
      $res = $this->inner->get($id, $ns);
      
      if ($res->is_some() && !$this->active)
        // return "trap" if symbol is private but 
        // this scope is not active
        $res = ScResult::Priv($res->unwrap());
    }
  
    return $res;
  }
  
  /**
   * @see Scope#add()
   * @param Symbol $sym
   */
  public function add(Symbol $sym)
  {
    if ($this->umap->has($sym->id)) {
      Logger::error_at($sym->loc, 'symbol-name `%s` \\', $sym->id);
      Logger::error('collides with an imported symbol'); 
      Logger::info_at($this->umap->get($sym->id)->loc, 'import was here');
      return false; 
    }
    
    #Logger::debug('adding %s (check=%d) <%s>', 
      #$sym->id, $this->check($sym, false), get_class($this));
            
    switch ($this->check($sym)) {
      case CHK_RES_ERR:
        return false;
        
      case CHK_RES_NOP:
        return true;
        
      case CHK_RES_REPL:
        $res = $this->get($sym->id, $sym->ns);
        assert($res->is_some());
        
        $prv = &$res->unwrap();
        $prv->scope->put($sym);
        return true;
      
      case CHK_RES_ADD:
        if ($sym->flags & SYM_FLAG_PRIVATE)
          return $this->inner->add($sym);
        
        $this->put($sym);
        return true;
      
      default:
        assert(0);
    }
  }
  
  /**
   * @see Scope#has()
   * @param  string  $id
   * @param  integer $ns
   * @return boolean
   */
  public function has($id, $ns = -1)
  {
    if (parent::has($id, $ns))
      return true;
    
    return $this->inner->has($id, $ns);
  }
  
  /**
   * @see SymbolMap#iter()
   *
   * @param  integer $ns
   */
  public function iter($ns = -1) 
  {
    // AppendIterator crashes with generators
    
    foreach (parent::iter($ns) as $sym)
      yield $sym;
    
    if ($this->active)
      foreach ($this->inner->iter($ns) as $sym)
        yield $sym;
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
        
    // usage
    foreach ($this->umap as $use) 
      $use->dump($tab . '  ');
        
    // modules
    foreach ($this->mmap as $mod) 
      $mod->dump($tab . '  ');
    
    // symbols
    $this->inner->dump($tab);
    parent::dump($tab);
  }
}

/** global scope */
class GlobScope extends RootScope
{
  /**
   * constructor
   *
   */
  public function __construct()
  {
    parent::__construct(null);
    $this->root = true;
  }
  
  public function __tostring()
  {
    return '<global scope>';
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
  public function __construct(Session $root, Unit $unit)
  {
    parent::__construct($root->scope);
    $this->unit = $unit;
    $this->file = $unit->loc->file;
  }
  
  public function __tostring()
  {
    return '<unit scope>';
  }
  
  public function dump($tab = '')
  {
    echo "\n<unit> @ ", $this->file;
    parent::dump($tab);
  }
  
  /**
   * @see RootScope#add()
   * @param Symbol $sym
   */
  public function add(Symbol $sym)
  {
    $res = parent::add($sym);
    
    if ($res && !($sym->flags & SYM_FLAG_PRIVATE))
      // move symbol to the global scope
      // but keep a reference for faster lookups
      $res = $this->prev->add($sym);
    
    return $res;
  }
}

/** unit-scope set */
class_alias('phs\\util\\Set', 'phs\\front\\UnitScopeSet');
class_alias('phs\\util\\Set', 'phs\\front\\UnitSet');

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
  
  public function __tostring()
  {
    return implode('::', $this->path());
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

/** module-scope map */
class_alias('phs\\util\\Map', 'phs\\front\\ModuleScopeMap');
class_alias('phs\\util\\Map', 'phs\\front\\ModuleMap');

/** member scope */
class MemberScope extends Scope
{    
  // @var FnSymbol  constructor-symbol
  public $ctor;
  
  // @var FnSymbol  destructor-symbol
  public $dtor;
  
  // @var SymbolMap  getter
  public $getter;
  
  // @var SymbolMap  setter
  public $setter;
  
  /**
   * constructor
   *
   * @param Scope $prev
   */
  public function __construct(Scope $prev)
  {
    parent::__construct($prev);
    #$this->sealed = true;
    $this->getter = new SymbolMap;
    $this->setter = new SymbolMap;
  }
  
  /* ------------------------------------ */
  
  public function dump($tab = '')
  {
    if ($this->ctor)
      $this->ctor->dump($tab . '  ');
    
    if ($this->dtor)
      $this->dtor->dump($tab . '  ');
        
    foreach ($this->getter->iter(SYM_FN_NS) as $sym)
      echo "\n", $tab, '  + <get> ', $sym->id;
    
    foreach ($this->setter->iter(SYM_FN_NS) as $sym)
      echo "\n", $tab, '  + <set> ', $sym->id;
    
    parent::dump($tab);
  }
}

