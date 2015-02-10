<?php

namespace phs;

require_once 'utils.php';
require_once 'usage.php';
require_once 'symbols.php';

use ArrayIterator;
use AppendIterator;

use phs\ast\Node;
use phs\ast\Unit;
use phs\ast\Name;
use phs\ast\Ident;

use phs\util\Set;
use phs\util\Map;
use phs\util\Entry;
use phs\util\Result;

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
  
  // access was restricted
  public $restricted = false;
  
  // symbol path (set by lookup)
  public $path = null;
  
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
   * returns true if this result failed because the 
   * requested symbol can not be accessed in the current context
   *
   * @return boolean
   */
  public function is_restricted()
  {
    return $this->restricted === true;
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
  
  /**
   * generated a "fail" result
   *
   * @param ScResult
   */
  public static function Restricted($data)
  {
    $res = static::Error($data);
    $res->restricted = true;
    return $res;
  }
}

/** scope */
class Scope extends SymbolMap
{
  // @var int  unique id
  public $uid;
  
  // @var boolean
  public $root = false;
  
  // @var Scope  parent scope
  public $prev;
  
  // @var array  references
  public $refs;
  
  // @var Set  captured symbols from the parent scope
  public $capt;
  
  // @var Scope  delegate scope
  private $other;
  
  // @var boolean
  public $sealed = false;
  
  // unique id counter
  private static $uidcnt = 0;
  
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
    return '<scope ' . get_class($this) . '>';
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
  public function leave() 
  {
    if ($this->other)
      $this->other->leave();
  }
  
  /**
   * captures a s symbol
   *
   * @param  Symbol $sym 
   */
  public function capture(Symbol $sym)
  {
    $this->capt->add($sym);
    $sym->captured = true;
    
    // stop at root-scopes (global scope)
    if ($this->root) return;
    
    // walk up all scopes from here 
    // and mark the symbol as captured 
    // until the origin-scope of the 
    // symbol was reached
    $prev = $this->prev;
        
    Logger::assert($prev, 'unable to capture %s', $sym);
    
    if ($sym->scope !== $prev)
      $prev->capture($sym);
  }
  
  /**
   * sets a delegate scope.
   * this scope gets only used in get() ignoring the `sealed` flag
   *
   * @param  Scope  $other
   */
  public function delegate(Scope $other)
  {
    $this->other = $other;
    $this->other->enter();
  }
  
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
   * @return ScResult
   */
  public function get($key, $ns = -1)
  {
    $res = $this->rec($key, $ns);
    
    if ($res->is_some()) {
      $sym = &$res->unwrap();
      
      if ($sym->capture && $sym->scope !== $this)
        $this->capture($sym);
    }
    
    return $res;
  }
  
  /**
   * searches for a symbol and returns it as result
   *
   * @param  string  $key
   * @param  integer $ns
   * @return ScResult
   */
  public function rec($key, $ns = -1)
  {
    $sym = parent::get($key, $ns);
    $res = null;
    
    if ($sym === null) {
      $test = [];
      
      if ($this->prev && !$this->sealed)
        $test[] = $this->prev;
      
      if ($this->other)
        $test[] = $this->other;
      
      foreach ($test as $scp) {
        $res = $scp->rec($key, $ns);
        
        // if found, mark symbol as captured
        if ($res->is_some()) 
          goto out;
      }
      
      $res = ScResult::None();
    } else
      $res = ScResult::from($sym);
    
    out:
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
    $this->prep($sym);
    $res = $this->rec($sym->id, $sym->ns);
    
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
      if (self::in_same_scope($prv, $this)) {
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
  
  /**
   * hook: should prepare the symbol (set default flags and so on)
   *
   * @param  Symbol $sym
   */
  public function prep(Symbol $sym)
  {
    // set default visibility
    if (!($sym->flags & SYM_FLAG_PUBLIC) &&
        !($sym->flags & SYM_FLAG_PRIVATE))
      $sym->flags |= SYM_FLAG_PRIVATE;
  }
  
  /**
   * checks if a symbol is a member of this scope
   *
   * @param  Symbol $sym
   * @return boolean
   */
  public function contains(Symbol $sym)
  {
    return $sym->scope && $sym->scope === $this;
  }
  
  /**
   * returns true if this scope has one or more captured symbols
   *
   * @return boolean
   */
  public function has_captures()
  {
    return $this->capt->count() > 0;
  }
  
  /**
   * returns true if this scope is global-ish
   *
   * @return boolean
   */
  public function is_global()
  {
    return false; 
  }
  
  /**
   * returns true if this scope has one or more local captured symbols.
   * local captured symbols := symbols from other block- or function-scopes
   *
   * @return boolean
   */
  public function has_local_captures()
  {
    foreach ($this->capt as $sym)
      if (!$sym->scope->is_global())
        return true;
      
    return false;
  }
  
  /**
   * checks if this scope has local symbols
   *
   * @return boolean
   */
  public function has_locals()
  {
    foreach ($this->iter() as $sym)
      if (!($sym->flags & SYM_FLAG_EXTERN))
        return true;
  }
  
  /* ------------------------------------ */
  
  public static function in_same_scope(Symbol $sym, Scope $scp)
  {
    // same scope
    if ($sym->scope === $scp)
      return true;
    
    // symbol is in the global scope and
    // the given scope refers to a unit, 
    // which delegates to the global scope.
    // those two scopes are considered "same"
    return $sym->scope->root && 
           $scp instanceof UnitScope;
  }
  
  /* ------------------------------------ */
  
  public function dump($tab = '')
  {
    foreach ($this->refs as $ref => $_)
      echo "\n", $tab, '  * ', $ref;
    
    parent::dump($tab);
  }
}

class FnScope extends Scope {}

/** inner scope */
class InnerScope extends Scope
{
  // @var Scope outer scope
  public $outer;
  
  public function __construct(Scope $outer)
  {
    parent::__construct(null); 
    $this->outer = $outer;
  }
  
  /**
   * captures a s symbol
   *
   * @param  Symbol $sym 
   */
  public function capture(Symbol $sym)
  {
    // only capture in outer scope
    $this->outer->capture($sym);
  }
  
  /**
   * @see Scope#is_global()
   *
   * @return boolean
   */
  public function is_global()
  {
    return $this->outer->is_global();
  }
}

/** a scope for private symbols */
abstract class PrivScope extends Scope
{
  // @var Scope inner scope (for private symbols)
  public $inner;
  
  // @var boolean  whenever this scope is active
  public $active = false;
  
  /**
   * constructor
   *
   * @param Scope $prev
   */
  public function __construct(Scope $prev = null)
  {
    parent::__construct($prev);
    $this->inner = new InnerScope($this);
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
   * captures a s symbol
   *
   * @param  Symbol $sym 
   */
  public function capture(Symbol $sym)
  {
    if ($sym->scope === $this->inner)
      return; // no need to capture
    
    parent::capture($sym);
  }
  
  /**
   * @see Scope#rec()
   * @param  string  $id
   * @param  integer $ns
   * @return Symbol
   */
  public function rec($id, $ns = -1)
  {    
    // try public scope first
    $res = parent::rec($id, $ns);
    
    if ($res->is_none()) {
      // try private scope
      $res = $this->inner->rec($id, $ns);
      
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
    
    foreach ($this->inner->iter($ns) as $sym)
      yield $sym;
  }
  
  /**
   * checks if a symbol is a member of this scope
   *
   * @param  Symbol $sym
   * @return boolean
   */
  public function contains(Symbol $sym)
  {
    return $sym->scope && (
      $sym->scope === $this ||
      $sym->scope === $this->inner
    );
  }
}

/** root-scope: common class for scopes 
    with (sub-)modules and/or usage */
abstract class RootScope extends PrivScope
{
  // @var UsageMap  usage of this scope
  public $umap;
  
  // @var ModuleMap (sub-)modules
  public $mmap;
  
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
            
    return parent::add($sym);
  }
  
  /**
   * @see Scope#is_global()
   *
   * @return boolean
   */
  public function is_global()
  {
    return true;
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
  public function __construct(GlobScope $root)
  {
    parent::__construct($root);
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
class_alias('phs\\util\\Set', 'phs\\UnitScopeSet');
class_alias('phs\\util\\Set', 'phs\\UnitSet');

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
class_alias('phs\\util\\Map', 'phs\\ModuleScopeMap');
class_alias('phs\\util\\Map', 'phs\\ModuleMap');

/** member scope */
class MemberScope extends PrivScope
{    
  // @var TraitSymbol|ClassSymbol|IfaceSymbol
  public $host;
  
  // @var MemberScope  super-class members
  public $super = null;
  
  // @var FnSymbol  constructor-symbol
  public $ctor;
  
  // @var FnSymbol  destructor-symbol
  public $dtor;
  
  // @var FnSymbol  clone-method
  public $clone;
  
  // @var SymbolMap  getter
  public $getter;
  
  // @var SymbolMap  setter
  public $setter;
  
  // @var boolean  restricted access
  public $restricted = false;
  
  // @var Symbol  root-object
  private static $robj;
  
  /**
   * constructor
   *
   * @param Scope $prev
   */
  public function __construct(Symbol $host, Scope $prev)
  {
    parent::__construct($prev);
    $this->host = $host;
    $this->getter = new SymbolMap;
    $this->setter = new SymbolMap;
  }
  
  /**
   * restrict access
   *
   * @param  bool $flag
   */
  public function restrict($flag)
  {
    $this->restricted = (bool) $flag;
    
    if ($this->super)
      $this->super->restrict($flag);
  }
  
  /**
   * @see Scope#capture()
   *
   * @param  Symbol $sym
   */
  public function capture(Symbol $sym)
  {
    if (!($sym->scope instanceof MemberScope))
      // use normal capturing
      parent::capture($sym);
    else {
      // check if the symbol derived from a super-class
      $dest = $this;
      
      for (;;) {
        if (!$dest->super) {
          // use normal capturing
          parent::capture($sym);
          break;
        }
        
        if ($dest->super === $sym->scope)
          break; // no capturing needed
        
        $dest = $dest->super;
      }
    }
  }
  
  /**
   * @see Scope#get()
   *
   * @param  string  $id
   * @param  integer $ns
   * @return ScResult
   */
  public function rec($id, $ns = -1)
  {
    $res = parent::rec($id, $ns);
    
    // ask super-class
    if ($res->is_none() && !$res->is_priv() && $this->super)
      $res = $this->super->rec($id, $ns);
    
    if ($res->is_some() && $this->restricted) {
      $sym = &$res->unwrap();
      
      if ($sym->scope === $this ||
          $sym->scope === $this->inner)
        $res = ScResult::Restricted($sym);
    }
    
    return $res;
  }
  
  /**
   * @see Scope#prep()
   *
   * @param  Symbol $sym
   */
  public function prep(Symbol $sym)
  {
    // set default visibility
    if (!($sym->flags & SYM_FLAG_PUBLIC) &&
        !($sym->flags & SYM_FLAG_PRIVATE) && 
        !($sym->flags & SYM_FLAG_PROTECTED))
      $sym->flags |= SYM_FLAG_PRIVATE;
  } 
  
  public function iter($ns = -1)
  {
    if ($ns === -1 || $ns === SYM_FN_NS) {
      if ($this->ctor) yield $this->ctor;
      if ($this->dtor) yield $this->dtor;
      
      foreach ($this->getter->iter() as $sym)
        yield $sym;
      
      foreach ($this->setter->iter() as $sym)
        yield $sym;
    }
    
    foreach (parent::iter($ns) as $sym)
      yield $sym;
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
    
    $this->inner->dump($tab);
    parent::dump($tab);
  }
}

