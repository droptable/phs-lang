<?php

namespace phs;

require_once 'utils.php';
require_once 'types.php';
require_once 'values.php';

use Countable;
use ArrayIterator;
use IteratorAggregate;

use phs\ast\Node;
use phs\ast\Decl;
use phs\ast\Name;
use phs\ast\Ident;
use phs\ast\FnDecl;
use phs\ast\FnExpr;
use phs\ast\VarDecl;
use phs\ast\VarItem;
use phs\ast\EnumVar;
use phs\ast\Param;
use phs\ast\ThisParam;
use phs\ast\RestParam;
use phs\ast\CtorDecl;
use phs\ast\DtorDecl;
use phs\ast\GetterDecl;
use phs\ast\SetterDecl;

use phs\util\Set;
use phs\util\Map;
use phs\util\Entry;
use phs\util\LooseSet;

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
  SYM_FLAG_INCOMPLETE = 0x0800, // symbol is incomplete
  SYM_FLAG_PARAM      = 0x1000, // symbol is a parameter
  SYM_FLAG_UNSAFE     = 0x2000  // symbol (name) is unsafe (no mangle) 
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
  
  // @var string  raw identifier (gets not changed in the mangler)
  public $rid;
  
  // @var Location
  public $loc;
  
  // @var int
  public $kind;
  
  // @var Node  the ast-node associated with this symbol
  public $node;
  
  // @var int
  public $flags;
  
  // @var Scope  scope where this symbol was defined in
  public $scope = null;
  
  // @var boolean  de-optimized symbol
  public $deopt = false;
      
  // @var boolean  managed symbol (intrinsic)
  public $managed = false;  
  
  // @var bool  whenever this symbol must be captured by other scopes
  public $capture = false;
  
  // @var bool  whenever this symbol was captured by a other scope
  public $captured = false;
  
  // @var boolean  whenever this symbol is reachable
  public $reachable = true;
  
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
    $this->rid = $id;
    $this->loc = $loc;
    $this->kind = $kind;
    $this->flags = $flags;
        
    $this->value = Value::$UNDEF;
  }
  
  /**
   * __clone
   *
   * @return void
   */
  public function __clone()
  {
    $this->scope = null;
    
    if ($this->node)
      $this->node = clone $this->node;
  }
  
  /**
   * returns a label for this symbol.
   * e.g.: IfaceSymbol -> "iface `$name`" 
   *
   * @return string
   */
  public function __tostring()
  { 
    $str = '';
    
    if ($this->flags & SYM_FLAG_PARAM)
      $str .= 'parameter';
    else
      $str = sym_kind_to_str($this->kind);
    
    $str .= ' ';
    $abs = $this->path(false);
    
    $str .= '`';
    $str .= path_to_str($abs);
    
    if (!empty ($abs)) {
      if ($this->scope instanceof MemberScope ||
          ($this->scope instanceof InnerScope &&
           $this->scope->outer instanceof MemberScope))
        $str .= '.';
      else
        $str .= '::';
    }
    
    $str .= $this->id;
    $str .= '`';
    
    return $str;
  }
  
  /**
   * returns the absolute path (fully qualified) to this symbol
   *
   * @return array
   */
  public function path($self = true)
  {
    $abs = [];
    
    if ($self === true)
      $abs[] = $this->id;
    
    if ($this->flags & SYM_FLAG_GLOBAL)
      return $abs;
        
    // don't compute a path for parameters
    if ($this->flags & SYM_FLAG_PARAM)
      return $abs;
       
    for ($scp = $this->scope; 
         $scp !== null; 
         $scp = $scp->prev) {
      
      if ($scp instanceof InnerScope)
        $scp = $scp->outer;
      
      while (!($scp instanceof RootScope)) {
        if (!$scp->prev) break 2;
        
        if ($scp instanceof MemberScope)
          $abs[] = $scp->host->id;
        
        $scp = $scp->prev;
      }
      
      if ($scp instanceof UnitScope ||
          $scp instanceof GlobScope)
        break;
      
      if ($scp instanceof ModuleScope)
        $abs[] = $scp->id;
    }
        
    return array_reverse($abs);
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
      foreach ($this->mem as &$mem)
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
   * clears a symbol namespace (or all)
   *
   * @param  integer $ns
   */
  public function clear($ns = -1)
  {
    if ($ns === -1) 
      $this->mem = [ [], [], [], [] ];
    else
      $this->mem[$ns] = [];
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
      
      foreach ($this->mem as &$mem) 
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
  
  /**
   * iterator support
   *
   * @param  integer $ns
   */
  public function iter($ns = -1) 
  {
    if ($ns > -1)
      foreach ($this->mem[$ns] as $sym)
        yield $sym;
    else
      foreach ($this->mem as $mem)
        foreach ($mem as $sym)
          yield $sym;
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
    return $a === $b || (
      $a->id === $b->id &&
      $a->kind === $b->kind
    );
  }
}

/* ------------------------------------ */

/** trait usage */
class TraitUsage
{
  // @var Location
  public $loc; 
  
  // @var Name  trait
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
   * @param Name $trait
   * @param Location  $loc
   * @param string    $orig
   * @param string    $dest
   * @param int    $flags
   */
  public function __construct(Name $trait, Location $loc,
                              $orig, $dest, $flags = SYM_FLAG_NONE)
  {
    $this->trait = $trait;
    $this->loc = $loc;
    $this->orig = $orig;
    $this->dest = $dest;
    $this->flags = $flags;
  }
}

/* ------------------------------------ */

/** function symbol */
class FnSymbol extends Symbol
{  
  private static $aid = 0;
  
  // @var boolean  this is a function-expression
  public $expr;
  
  // @var boolean
  public $ctor;
  
  // @var boolean
  public $dtor;
  
  // @var boolean
  public $getter;
  
  // @var boolean
  public $setter;
  
  // @var boolean  whenever this symbol is nested
  public $nested;
  
  // @var array  parameters
  public $params;
  
  // @var TraitSymbol  origin
  public $origin = null;
  
  // @var TypeSet  inferred types
  public $types;
  
  // @var bool  this symbol must be captured
  // note: most likely this is not the case
  public $capture = true;
  
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
    $this->params = [];
    $this->types = new TypeSet;
  }
  
  /* ------------------------------------ */
  
  public function dump($tab = '')
  {
    parent::dump($tab);
    
    if ($this->origin !== null)
      echo ' (& ', $this->origin->id, ')';
  }
  
  /* ------------------------------------ */
  
  /**
   * creates a function-symbol from an FnDecl ast-node
   * 
   * @param  FnDecl|FnExpr|GetterDecl|SetterDecl|CtorDecl|DtorDecl $node
   * @return FnSymbol
   */
  public static function from($node)
  {
    $id = '<unknown>';
    
    if ($node instanceof FnDecl || 
        ($node instanceof FnExpr && $node->id) ||
        $node instanceof GetterDecl ||
        $node instanceof SetterDecl)
      $id = ident_to_str($node->id);
    elseif ($node instanceof FnExpr)
      $id = '~anonymus#' . self::$aid++;
    elseif ($node instanceof CtorDecl)
      $id = '<ctor>';
    elseif ($node instanceof DtorDecl)
      $id = '<dtor>';
    else
      assert(0);
    
    $flags = SYM_FLAG_NONE;
    
    if ($node instanceof FnDecl ||
        $node instanceof CtorDecl ||
        $node instanceof DtorDecl) {
      $flags = mods_to_sym_flags($node->mods);
    
      if (($node instanceof FnDecl ||
           $node instanceof DtorDecl) && 
          $node->body === null &&
          !($flags & SYM_FLAG_EXTERN))
        $flags |= SYM_FLAG_ABSTRACT;
      
      if ($node instanceof CtorDecl && 
          $node->body === null) {
        // ctor can be abstract too,
        // but only if there are no "this-params"
        $that = false;
        foreach ($node->params as $param)
          if ($param instanceof ThisParam) {
            $that = true;
            break;
          }
          
        if ($that === false)
          $flags |= SYM_FLAG_ABSTRACT;
      }
    }
    
    $sym = new FnSymbol($id, $node->loc, $flags);
    $sym->expr = $node instanceof FnExpr;
    $sym->ctor = $node instanceof CtorDecl;
    $sym->dtor = $node instanceof DtorDecl;
    $sym->getter = $node instanceof GetterDecl;
    $sym->setter = $node instanceof SetterDecl;
    $sym->node = $node;
    $sym->nested = false;
    return $sym;
  }
}

/** var symbol */
class VarSymbol extends Symbol
{  
  // @var Node  initializer
  public $init;
  
  // @var Value  whenever a value could be computed during compilation 
  public $value = null;
  
  // @var TraitSymbol  origin
  public $origin = null;
  
  // @var TypeSet  inferred types
  public $types;
  
  // @var boolean  whenever this variable is assigned
  public $assign = false;
  
  // @var bool  this symbol must be captured
  public $capture = true;
  
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
    $this->reachable = false;
    $this->types = new TypeSet;
  }
  
  /**
   * __clone
   *
   * @return void
   */
  public function __clone()
  {
    parent::__clone();
    
    if ($this->value !== null)
      $this->value = clone $this->value;
  }
  
  /* ------------------------------------ */
  
  public function dump($tab = '')
  {
    parent::dump($tab);
    
    if ($this->origin !== null)
      echo ' (& ', $this->origin->id, ')';
  }
  
  /* ------------------------------------ */
  
  /**
   * creates a variable-symbol from an VarItem or EnumVar ast-node.
   * 
   * @todo VarItem and EnumVar should implement a shared interface
   * 
   * @param  VarItem|EnumVar|Ident $var
   * @param  mixed  $mods
   * @return VarSymbol
   */
  public static function from($var, $mods = SYM_FLAG_NONE)
  {
    assert($var instanceof VarItem ||
           $var instanceof EnumVar ||
           $var instanceof Ident);
    
    $id = ident_to_str($var instanceof Ident ? $var : $var->id);
    $flags = is_int($mods) ? $mods : mods_to_sym_flags($mods);
    
    $sym = new VarSymbol($id, $var->loc, $flags);
    $sym->node = $var;
    $sym->init = $var instanceof Ident ? null : $var->init;
    
    return $sym;
  }
}

class ParamSymbol extends VarSymbol
{
  // @var boolean
  public $opt = false;
  
  // @var bool
  public $ref = false;
  
  // @var bool
  public $that = false;
  
  // @var boolean  
  public $rest = false;
  
  // @var TypeId|Name  hint
  public $hint;
  
  // @var Node initializer
  public $init;
  
  /**
   * constructor
   *
   * @param string   $id
   * @param Location $loc
   * @param int   $flags
   */
  public function __construct($id, Location $loc, $flags)
  {
    // super
    parent::__construct($id, $loc, $flags | SYM_FLAG_PARAM);
  }
  
  /* ------------------------------------ */
  
  /**
   * param factory
   *
   * @param  Param|ThisParam|RestParam $node
   * @return ParamSymbol
   */
  public static function from($node)
  {
    if (!($node instanceof Param ||
          $node instanceof ThisParam ||
          $node instanceof RestParam))
      assert(0);
    
    $flags = SYM_FLAG_NONE;
    
    if ($node instanceof Param)
      $flags = mods_to_sym_flags($node->mods);
    
    $sym = new ParamSymbol(ident_to_str($node->id), $node->loc, $flags);
    
    if (!($node instanceof ThisParam))
      $sym->opt = $node instanceof RestParam || $node->opt || 
        ($node instanceof Param && $node->init);
    
    $sym->ref = $node->ref;
    $sym->rest = $node instanceof RestParam;
    $sym->hint = $node->hint;
    $sym->that = $node instanceof ThisParam;
    $sym->init = null;
    
    if ($node instanceof Param || $node instanceof ThisParam)
      $sym->init = $node->init;
    
    $sym->node = $node;
    return $sym;
  }
}

/** class symbol */
class ClassSymbol extends Symbol
{
  // @var Name  super class
  public $super = null;
  
  // @var array  interfaces
  public $ifaces;
  
  // @var array  traits
  public $traits;
  
  // @var MemberScope  members
  public $members;
  
  // @var boolean  whenever this class is resolved
  public $resolved = false;
  
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
      echo "\n", $tab, '  : ', path_to_str($this->super->symbol->path());
        
    // dump interfaces 
    if ($this->ifaces)
      foreach ($this->ifaces as $iface)
        echo "\n", $tab, '  ~ ', path_to_str($iface->symbol->path());
    
    // dump traits
    if ($this->traits)
      foreach ($this->traits as $trait) {
        echo "\n", $tab, '  & ', path_to_str($trait->trait->symbol->path());
        
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

/** trait symbol */
class TraitSymbol extends Symbol
{    
  // @var TraitUsageMap  traits
  public $traits;
  
  // @var SymbolMap  members
  public $members;
  
  // @var boolean  whenever this trait is resolved
  public $resolved = false;
  
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
        echo "\n", $tab, '  & ', name_to_str($trait->trait);
        
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
  
  // @var boolean  whenever this interface is resolved
  public $resolved = false;
  
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
