<?php

namespace phs\front;

require_once 'utils.php';

use \Countable;
use \ArrayIterator;
use \IteratorAggregate;

use phs\util\Set;
use phs\util\Map;
use phs\util\Entry;

// used in SymbolSet
use phs\util\LooseSet;

use phs\front\ast\Node;
use phs\front\ast\Decl;
use phs\front\ast\Name;
use phs\front\ast\Ident;

use phs\front\ast\FnDecl;
use phs\front\ast\VarDecl;
use phs\front\ast\VarItem;
use phs\front\ast\EnumVar;
use phs\front\ast\CtorDecl;
use phs\front\ast\DtorDecl;

// kinds
const
  SYM_KIND_UNDEF  = 0,
  SYM_KIND_FN     = 1,
  SYM_KIND_VAR    = 2,
  SYM_KIND_CLASS  = 3,
  SYM_KIND_TRAIT  = 4,
  SYM_KIND_IFACE  = 5,
  SYM_KIND_ALIAS  = 6
;

// flags
const
  SYM_FLAG_NONE       = 0x0000, // no flags
  SYM_FLAG_CONST      = 0x0001, // symbol is constant
  SYM_FLAG_FINAL      = 0x0002, // symbol is final
  SYM_FLAG_GLOBAL     = 0x0004, // symbol is a global-reference
  SYM_FLAG_STATIC     = 0x0008, // symbol is static
  SYM_FLAG_PUBLIC     = 0x0010, // symbol is public
  SYM_FLAG_PRIVATE    = 0x0020, // symbol is private
  SYM_FLAG_PROTECTED  = 0x0040, // symbol is protected
  SYM_FLAG_SEALED     = 0x0080, // symbol is sealed (for functions)
  SYM_FLAG_INLINE     = 0x0100, // symbol is inline (for functions)
  SYM_FLAG_EXTERN     = 0x0200, // symbol is extern
  SYM_FLAG_ABSTRACT   = 0x0400, // symbol is abstract (for classes)
  SYM_FLAG_INCOMPLETE = 0x0800 // symbol is incomplete
;

const SYM_FLAGS_NONE = SYM_FLAG_NONE;

// namespaces
const
  SYM_NS0 = 0, // internal
  SYM_NS1 = 1, // defn namespace
  SYM_NS2 = 2, // type namespace
  SYM_NS3 = 3  // alias namespace
;

// namespace lookup
const
  SYM_FN_NS    = SYM_NS1,
  SYM_VAR_NS   = SYM_NS1,
  SYM_CLASS_NS = SYM_NS2,
  SYM_TRAIT_NS = SYM_NS2,
  SYM_IFACE_NS = SYM_NS2,
  SYM_ALIAS_NS = SYM_NS3
;

/**
 * returns a namespace-id for a symbol-kind
 * 
 * @param  int $kind
 * @return int
 */
function get_sym_ns($kind) {
  static $tbl = [
    SYM_KIND_FN => SYM_FN_NS,
    SYM_KIND_VAR => SYM_VAR_NS,
    SYM_KIND_CLASS => SYM_CLASS_NS,
    SYM_KIND_TRAIT => SYM_TRAIT_NS,
    SYM_KIND_IFACE => SYM_IFACE_NS
  ];
  
  assert($kind >= 0 && $kind <= SYM_KIND_IFACE);
  return $tbl[$kind];
}

/** symbol base */
abstract class Symbol
{
  // @var int
  public $ns;
  
  // @var string
  public $id;
  
  // @var Location
  public $loc;
  
  // @var int
  public $kind;
  
  // @var int
  public $flags;
  
  // @var Node  the ast-node which defines this symbol
  public $node = null;
  
  // @var Scope  scope where this symbol was defined in
  public $scope = null;
  
  /**
   * constructor
   * 
   * @param Ident|string  $id
   * @param int $ns
   * @param Location $loc 
   * @param int   $kind
   * @param int   $flags
   */
  public function __construct($id, $ns, Location $loc, $kind, $flags)
  {
    if ($id instanceof Ident)
      $id = ident_to_str($id);
    
    $this->id = $id;
    $this->ns = $ns;
    $this->loc = $loc;
    $this->kind = $kind;
    $this->flags = $flags;
  }
  
  /**
   * __clone
   *
   * @return void
   */
  public function __clone()
  {
    $this->scope = null;
  }
  
  /* ------------------------------------ */
  
  /**
   * debug dump
   *
   * @param  string $tab
   * @return void
   */
  public function dump($tab = '') 
  {
    echo "\n", $tab, '+ ', $this->id, ' (', 
         sym_kind_to_str($this->kind), ')';
    
    $mods = sym_flags_to_str($this->flags);
    
    if ($mods !== 'none')
      echo ' ~ ', $mods;
  }
}

/** symbol ref */
class SymbolRef
{
  // @var Name  the name (path) 
  public $name;
  
  // @var int
  public $kind;
  
  // @var Symbol  the resolved symbol
  public $symbol = null;
  
  // @var boolean  whenever this reference is resolved
  public $resolved = false;
  
  /**
   * constructor
   *
   * @see Symbol#__construct()
   */
  public function __construct(Name $name, $kind = SYM_KIND_UNDEF)
  {
    $this->name = $name;
    $this->kind = $kind;
  }
}

/** symbol ref set */
class SymbolRefSet extends Set
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
  public function check($ent)
  {
    return $ent instanceof SymbolRef;
  }
}

/* ------------------------------------ */

/** symbol map */
class SymbolMap implements
  IteratorAggregate, Countable
{
  // memory
  private $mem = [ 
    [] /* NS0 */, 
    [] /* NS1 */,
    [] /* NS2 */,
    [] /* NS3 */
  ];
  
  // index
  private $idx = [];
  
  /**
   * constructor 
   */
  public function __construct()
  {
    // empty
  }
  
  /**
   * adds a symbol
   * 
   * @param Symbol $sym
   * @return boolean
   */
  public function add(Symbol $sym)
  {
    $ns = $sym->ns;
    $id = $sym->id;
    
    assert($ns >= 0);
    
    if (isset ($this->mem[$ns][$id]))
      return false;
    
    $this->mem[$ns][$id] = $sym;
    return true;
  }
  
  /**
   * add/update a symbol
   * 
   * @param Symbol $sym
   * @return Symbol  the previous symbol
   */
  public function put(Symbol $sym)
  {
    assert($sym->ns >= 0);
    $prv = null;
    
    if (isset ($this->mem[$sym->ns][$sym->id]))
      $prv = $this->mem[$sym->ns][$sym->id];
    
    $this->mem[$sym->ns][$sym->id] = $sym;
    return $prv;
  }
  
  /**
   * fetches a symbol
   * 
   * @param  string  $id
   * @param  integer $ns
   * @return Symbol
   */
  public function get($id, $ns = -1)
  {
    if ($ns === -1) {
      // search in all namespaces
      foreach ($this->mem as $mem)
        if (isset ($mem[$id])) 
          return $mem[$id];
      
      // no symbol found
      return null;
    }
    
    assert($ns >= 0);
    
    if (isset ($this->mem[$ns][$id]))
      return $this->mem[$ns][$id];
    
    // no symbol found
    return null;
  }
  
  /**
   * check is a symbol exists
   * 
   * @param  string  $id
   * @param  integer $ns
   * @return boolean
   */
  public function has($id, $ns = -1)
  {
    if ($ns === -1) {
      // search in all namespaces
      foreach ($this->mem as $mem)
        if (isset ($mem[$id]))
          return true;
        
      // no symbol found
      return false;
    }
    
    assert($ns >= 0);
    return isset ($this->mem[$ns][$id]);
  }
  
  /**
   * deletes a symbol
   * 
   * @param  string  $id
   * @param  integer $ns
   * @return boolean
   */
  public function delete($id, $ns = -1)
  {
    if ($ns === -1) {
      // delete in all namespaces
      $fd = false;
      
      foreach ($this->mem as $mem) 
        if (isset ($mem[$id])) {
          $fd = true;
          unset ($mem[$id]);
        }
      
      return $fd;
    }
    
    assert($ns >= 0);
    
    if (isset ($this->mem[$ns][$id])) {
      unset ($this->mem[$ns][$id]);
      return true;
    }
    
    return false;
  }
  
  /* ------------------------------------ */
  /* IteratorAggregate */
  
  public function getIterator()
  {
    return new ArrayIterator($this->mem);
  }
  
  /* ------------------------------------ */
  /* Countable */
  
  public function count()
  {
    $count = 0;
    
    foreach ($this->mem as $mem)
      $count += count($mem);
    
    return $count;
  }
  
  /* ------------------------------------ */
  
  public function dump($tab = '')
  {
    assert(PHS_DEBUG);
    
    foreach ($this->mem as $ns => $mem)
      foreach ($mem as $sym)
        $sym->dump($tab . '  ');
  }
}

/* ------------------------------------ */

/** symbol-list */
class SymbolList implements IteratorAggregate, Countable
{
  // @var array
  private $mem;
  
  /**
   * constructor
   */
  public function __construct()
  {
    $this->mem = [];
  }
  
  /**
   * adds a symbol
   * 
   * @param Symbol $sym
   */
  public function add(Symbol $sym)
  {
    $this->mem[] = $sym;
  }
  
  /**
   * returns a symbol at the given index.
   * you should avoid this method, because there is no associativity.
   * 
   * @param  int $idx
   * @return Symbol|null
   */
  public function get($idx)
  {
    if (isset ($this->mem[$idx]))
      return $this->mem[$idx];
    
    return null;
  }
  
  /**
   * checks if a symbol is present in the list.
   * 
   * if $key parameter is a int:
   * this method checks if the list has something at the given index.
   * 
   * if $key parameter is a string:
   * this methods checks if a symbol with the given id exists.
   * 
   * if $key parameter is a Symbol:
   * this method performs a simple 'in-array' lookup.
   * 
   * @param  int|string|Symbol  $key
   * @return boolean
   */
  public function has($key)
  {
    if (is_int($key))
      return isset ($this->mem[$key]);
    
    if (is_string($key)) {
      foreach ($this->mem as $sym)
        if ($sym->id === $key)
          return true;
      
      return false;
    }
    
    if ($key instanceof Symbol)
      return in_array($ke, $this->mem, true);
    
    // invalid key
    return false;
  }
  
  /* ------------------------------------ */
  /* IteratorAggregate */
  
  public function getIterator()
  {
    return new ArrayIterator($this->mem);
  }
  
  /* ------------------------------------ */
  /* Countable */
  
  public function count()
  {
    return count($this->mem);
  }
}

/* ------------------------------------ */

/** symbol-set */
class SymbolSet extends LooseSet
{
  /**
   * constructor
   * 
   */
  public function __construct()
  {
    parent::__construct();
  }
    
  /**
   * @see Set#check()
   * @param  mixed $ent
   * @return boolean
   */
  protected function check($ent)
  {
    return $ent instanceof Symbol;
  }
  
  /**
   * @see LooseSet#compare()
   * @param  mixed $a
   * @param  mixed $b
   * @return boolean
   */
  protected function compare($a, $b)
  {
    // loose compare two symbols.
    // this will prevent add() to assign symbols with the same name.
    return $a === $b || $a->id === $b->id;
  }
}

/* ------------------------------------ */

/** trait usage */
class TraitUsage
{
  // @var Location
  public $loc; 
  
  // @var SymbolRef  trait
  public $trait;
  
  // @var string  original name
  public $orig;
  
  // @var string  destination name (alias)
  public $dest;
  
  // @var int
  public $flags;
  
  /**
   * constructor
   *
   * @param SymbolRef $trait
   * @param Location  $loc
   * @param string    $orig
   * @param string    $dest
   * @param int    $flags
   */
  public function __construct(SymbolRef $trait, Location $loc,
                              $orig, $dest, $flags = SYM_FLAG_NONE)
  {
    $this->trait = $trait;
    $this->loc = $loc;
    $this->orig = $orig;
    $this->dest = $dest;
    $this->flags = $flags;
  }
}

/** trait usage set */
class TraitUsageSet extends Set
{
  /**
   * constructor
   */
  public function __construct()
  {
    parent::__construct();
  }
  
  /**
   * typecheck
   * 
   * @see Set#check()
   * @param  mixed  $ent
   * @return boolean
   */
  protected function check($ent)
  {
    return $ent instanceof TraitUsage;
  }
}

/* ------------------------------------ */

/** function symbol */
class FnSymbol extends Symbol
{  
  /**
   * constructor
   * 
   * @param string   $id
   * @param Location $loc
   * @param int   $flags
   */
  public function __construct($id, Location $loc, $flags)
  {
    // init symbol
    parent::__construct($id, SYM_FN_NS, $loc, SYM_KIND_FN, $flags);
  }
  
  /* ------------------------------------ */
  
  /**
   * creates a function-symbol from an FnDecl ast-node
   * 
   * @param  FnDecl|CtorDecl|DtorDecl $node
   * @return FnSymbol
   */
  public static function from($node)
  {
    $id = '<unknown>';
    
    if ($node instanceof FnDecl)
      $id = ident_to_str($node->id);
    elseif ($node instanceof CtorDecl)
      $id = '<ctor>';
    elseif ($node instanceof DtorDecl)
      $id = '<dtor>';
    else
      assert(0);
    
    $sym = new FnSymbol($id, $node->loc, mods_to_sym_flags($node->mods));
    $sym->node = $node;
    
    return $sym;
  }
}

/** var symbol */
class VarSymbol extends Symbol
{  
  // @var Value  whenever a value could be computed during compilation 
  public $value = null;
  
  /**
   * constructor
   * 
   * @param string   $id
   * @param Location $loc
   * @param int   $flags
   */
  public function __construct($id, Location $loc, $flags)
  {
    // init symbol
    parent::__construct($id, SYM_VAR_NS, $loc, SYM_KIND_VAR, $flags);
  }
  
  /**
   * __clone
   *
   * @return void
   */
  public function __clone()
  {
    parent::__clone();
    $this->value = clone $this->value;
  }
  
  /* ------------------------------------ */
  
  /**
   * creates a variable-symbol from an VarItem or EnumVar ast-node.
   * 
   * @todo VarItem and EnumVar should implement a shared interface
   * 
   * @param  VarItem|EnumVar $var
   * @param  mixed  $mods
   * @return VarSymbol
   */
  public static function from($var, $mods)
  {
    assert($var instanceof VarItem ||
           $var instanceof EnumVar);
    
    $id = ident_to_str($var->id);
    $flags = is_int($mods) ? $mods : mods_to_sym_flags($mods);
    $sym = new VarSymbol($id, $var->loc, $flags);
    $sym->node = $var;
    
    return $sym;
  }
}

class AliasSymbol extends Symbol
{
  // @var Name 
  public $orig;
  
  /**
   * constructor
   * 
   * @param string   $id
   * @param Location $loc
   * @param int   $flags
   */
  public function __construct($id, Location $loc)
  {
    // init symbol
    parent::__construct($id, SYM_ALIAS_NS, $loc, SYM_KIND_ALIAS, SYM_FLAG_NONE);
  }
  
  /* ------------------------------------ */
  
  /**
   * creates an alias-symbol from a ast-node
   *
   * @param  Node   $node
   * @return AliasSymbol
   */
  public static function from(Node $node)
  {
    $sym = new AliasSymbol(ident_to_str($node->id), $node->loc);
    $sym->node = $node;
    $sym->orig = $node->orig;
    
    return $sym;
  }
  
  /* ------------------------------------ */
  
  /**
   * debug dump
   *
   * @param  string $tab
   * @return void
   */
  public function dump($tab = '')
  {
    parent::dump($tab);
    echo ' => ', name_to_str($this->orig);
  }
}

/** class symbol */
class ClassSymbol extends Symbol
{
  // @var SymbolRef  super class
  public $super = null;
  
  // @var SymbolRefSet  interfaces
  public $ifaces;
  
  // @var TraitUsageSet  traits
  public $traits;
  
  // @var ClassScope  members
  public $members;
  
  // @var FnSymbol  constructor
  public $ctor = null;
  
  // @var FnSymbol  destructor
  public $dtor = null;
  
  /**
   * constructor
   * 
   * @param string   $id
   * @param Location $loc
   * @param int   $flags
   */
  public function __construct($id, Location $loc, $flags)
  {
    // init symbol
    parent::__construct($id, SYM_CLASS_NS, $loc, SYM_KIND_CLASS, $flags);
  }
  
  /* ------------------------------------ */
  
  public static function from(Node $node)
  {
    $id = ident_to_str($node->id);
    $sym = new ClassSymbol($id, $node->loc, mods_to_sym_flags($node->mods));
    $sym->node = $node;
    return $sym;
  }
  
  /* ------------------------------------ */
  
  /**
   * debug dump
   *
   * @param  string $tab
   * @return void
   */
  public function dump($tab = '')
  {
    parent::dump($tab);
    
    // dump super-class
    if ($this->super)
      echo "\n", $tab, '  : ', name_to_str($this->super->name);
        
    // dump interfaces 
    if ($this->ifaces)
      foreach ($this->ifaces as $iface)
        echo "\n", $tab, '  ~ ', name_to_str($iface->name);
    
    // dump traits
    if ($this->traits)
      foreach ($this->traits as $trait) {
        echo "\n", $tab, '  & ', name_to_str($trait->trait->name);
        
        if ($trait->orig) {
          echo ' + ', $trait->orig;
          
          if ($trait->dest)
            echo ' as ', $trait->dest;
          
          if ($trait->flags)
            echo ' ~ ', sym_flags_to_str($trait->flags);
        } else
          echo ' * ';
      }
    
    // dump constructor if any
    if ($this->ctor)
      $this->ctor->dump($tab . '  ');
    
    // dump destructor if any
    if ($this->dtor)
      $this->dtor->dump($tab . '  ');
    
    // dump members
    if ($this->members)
      $this->members->dump($tab);
  }
}

/** trait symbol */
class TraitSymbol extends Symbol
{  
  // @var TraitUsageMap  traits
  public $traits;
  
  // @var SymbolMap  members
  public $members;
  
  /**
   * constructor
   * 
   * @param string   $id
   * @param Location $loc  
   * @param Scope $scope
   * @param int   $flags
   */
  public function __construct($id, Location $loc, $flags)
  {
    // init symbol
    parent::__construct($id, SYM_TRAIT_NS, $loc, SYM_KIND_TRAIT, $flags);
  }
  
  /* ------------------------------------ */
  
  public static function from(Node $node)
  {
    $id = ident_to_str($node->id);
    $sym = new TraitSymbol($id, $node->loc, mods_to_sym_flags($node->mods));
    $sym->node = $node;
    return $sym;
  }
  
  /* ------------------------------------ */
  
  /**
   * debug dump
   *
   * @param  string $tab
   * @return void
   */
  public function dump($tab = '')
  {
    parent::dump($tab);
    
    // dump traits
    if ($this->traits)
      foreach ($this->traits as $trait) {
        echo "\n", $tab, '  & ', name_to_str($trait->trait->name);
        
        if ($trait->orig) {
          echo ' + ', $trait->orig;
          
          if ($trait->dest)
            echo ' as ', $trait->dest;
          
          if ($trait->flags)
            echo ' ~ ', sym_flags_to_str($trait->flags);
        } else
          echo ' * ';
      }
        
    // dump members
    if ($this->members)
      $this->members->dump($tab);
  }
}

/** iface symbol */
class IfaceSymbol extends Symbol
{
  // @var SymbolRefSet  interfaces
  public $ifaces;
  
  // @var SymbolMap  members
  public $members;
  
  /**
   * constructor
   * 
   * @param string   $id
   * @param Location $loc  
   * @param int   $flags
   */
  public function __construct($id, Location $loc, $flags)
  {
    // init symbol
    parent::__construct($id, SYM_IFACE_NS, $loc, SYM_KIND_IFACE, $flags);
  }
  
  /* ------------------------------------ */
  
  public static function from(Node $node)
  {
    $id = ident_to_str($node->id);
    $sym = new IfaceSymbol($id, $node->loc, mods_to_sym_flags($node->mods));
    $sym->node = $node;
    return $sym;
  }
  
  /* ------------------------------------ */
  
  /**
   * debug dump
   *
   * @param  string $tab
   * @return void
   */
  public function dump($tab = '')
  {
    parent::dump($tab);
    
    // dump interfaces 
    if ($this->ifaces)
      foreach ($this->ifaces as $iface)
        echo "\n", $tab, '  ~ ', name_to_str($iface->name);
        
    // dump members
    if ($this->members)
      $this->members->dump($tab);
  }
}
