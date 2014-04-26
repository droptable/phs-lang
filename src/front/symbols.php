<?php

namespace phs\front;

use phs\util\Set;
use phs\util\Map;
use phs\util\Cell;
use phs\util\Table;

// kinds
const
  SYM_KIND_FN     = 1,
  SYM_KIND_VAR    = 2,
  SYM_KIND_CLASS  = 3,
  SYM_KIND_TRAIT  = 4,
  SYM_KIND_IFACE  = 5
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
  SYM_FLAG_INCOMPLETE = 0x0800  // symbol is incomplete
;

const SYM_FLAGS_NONE = SYM_FLAG_NONE;

// namespaces
const
  SYM_NS0 = 0, // fn, var
  SYM_NS1 = 1  // class, trait, iface
;

/**
 * returns the namespace-id for a symbol-kind
 * 
 * @param  int $kind
 * @return int
 */
function get_sym_ns($kind) {
  if ($kind instanceof Symbol)
    return $kind->row(); // ...
  
  switch ($kind) {
    case SYM_KIND_FN:
    case SYM_KIND_VAR:
      return SYM_NS0;
    default:
      return SYM_NS1;
  }
}

/** symbol base */
abstract class Symbol implements Cell
{
  // @var string
  public $id;
  
  // @var Location
  public $loc;
  
  // @var int
  public $kind;
  
  // @var int
  public $flags;
  
  // @var Scope
  public $scope;
  
  /**
   * constructor
   * 
   * @param string   $id
   * @param int   $kind
   * @param Location $loc  
   * @param Scope    $scope
   * @param int   $flags
   */
  public function __construct($id, $kind, Location $loc, Scope $scope, $flags)
  {
    $this->id = $id;
    $this->kind = $kind;
    $this->loc = $loc;
    $this->scope = $scope;
    $this->flags = $flags;
  }
  
  /**
   * returns a key for this symbol
   * 
   * @see Cell#key()
   * @return string
   */
  public function key()
  {
    return $this->id;
  }
  
  /**
   * should return the row (namespace) for this symbol
   * 
   * @see Cell#row()
   * @return int
   */
  abstract public function row();
}

/* ------------------------------------ */

/** symbol table */
class SymbolTable extends Table
{
  /**
   * constructor
   */
  public function __construct()
  {
    parent::__construct();
  }
  
  /**
   * add a symbol
   * 
   * @param string $key
   * @param Symbol $val
   */
  public function add(Cell $itm)
  {
    assert($itm instanceof Symbol);
    return parent::add($itm);
  }
}

/* ------------------------------------ */

/** trait usage */
class TraitUsage
{
  // @var TraitSymbol  trait
  public $trait;
  
  // @var Symbol  member
  public $member;
  
  // @var int  flags
  public $flags;
  
  // @var string  dest
  public $dest;
  
  public function __construct(TraitSymbol $trait, 
                              Symbol $member, $flags, $dest)
  {
    $this->trait = $trait;
    $this->member = $member;
    $this->flags = $flags;
    $this->dest = $dest;
  }
}

/** trait usage map */
class TraitUsageMap extends Map
{
  /**
   * constructor
   */
  public function __construct()
  {
    parent::__construct();
  }
  
  /**
   * add a symbol
   * 
   * @param string $key
   * @param TraitUsage $val
   */
  public function add($key, $val)
  {
    assert($val instanceof TraitUsage);
    return parent::add($key, $val);
  }
  
  /**
   * add/update a symbol
   * 
   * @param string $key
   * @param TraitUsage $val
   */
  public function set($key, $val)
  {
    assert($val instanceof TraitUsage);
    parent::set($key, $val);
  }
}

/* ------------------------------------ */

/** function symbol */
class FnSymbol extends Symbol
{
  // whenever the function returns something
  public $has_return = false;
  
  /**
   * constructor
   * 
   * @param string   $id
   * @param Location $loc  
   * @param Scope $scope
   * @param int   $flags
   */
  public function __construct($id, Location $loc, Scope $scope, $flags)
  {
    // init symbol
    parent::__construct($id, SYM_KIND_FN, $loc, $scope, $flags);
  }
  
  /**
   * returns the namespace
   * 
   * @return int
   */
  public function row()
  {
    // definition namespace
    return SYM_NS0;
  }
}

/** var symbol */
class VarSymbol extends Symbol
{
  // whenever a value is assigned
  public $value = null;
  
  /**
   * constructor
   * 
   * @param string   $id
   * @param Location $loc  
   * @param Scope $scope
   * @param int   $flags
   */
  public function __construct($id, Location $loc, Scope $scope, $flags)
  {
    // init symbol
    parent::__construct($id, SYM_KIND_VAR, $loc, $scope, $flags);
  }
  
  /**
   * returns the namespace
   * 
   * @return int
   */
  public function row()
  {
    // definition namespace
    return SYM_NS0;
  }
}

/** class symbol */
class ClassSymbol extends Symbol
{
  // super class
  public $super = null;
  
  // @var SymbolMap  interfaces
  public $ifaces;
  
  // @var TraitUsageMap  traits
  public $traits;
  
  // @var SymbolMap  fields
  public $fields;
  
  // @var SymbolMap  methods
  public $methods;
  
  /**
   * constructor
   * 
   * @param string   $id
   * @param Location $loc  
   * @param Scope $scope
   * @param int   $flags
   */
  public function __construct($id, Location $loc, Scope $scope, $flags)
  {
    // init symbol
    parent::__construct($id, SYM_KIND_CLASS, $loc, $scope, $flags);
    
    $this->ifaces = new SymbolTable;
    $this->traits = new TraitUsageMap;
    $this->fields = new SymbolTable;
    $this->methods = new SymbolTable;
  }
  
  /**
   * returns the namespace
   * 
   * @return int
   */
  public function row()
  {
    // definition namespace
    return SYM_NS1;
  }
}

/** trait symbol */
class TraitSymbol extends Symbol
{
  // @var TraitUsageMap  traits
  public $traits;
  
  // @var SymbolMap  fields
  public $fields;
  
  // @var SymbolMap  methods
  public $methods;
  
  /**
   * constructor
   * 
   * @param string   $id
   * @param Location $loc  
   * @param Scope $scope
   * @param int   $flags
   */
  public function __construct($id, Location $loc, Scope $scope, $flags)
  {
    // init symbol
    parent::__construct($id, SYM_KIND_TRAIT, $loc, $scope, $flags);
    
    $this->traits = new TraitUsageMap;
    $this->fields = new SymbolMap;
    $this->methods = new SymbolMap;
  }
  
  /**
   * returns the namespace
   * 
   * @return int
   */
  public function row()
  {
    // definition namespace
    return SYM_NS1;
  }
}

/** iface symbol */
class IfaceSymbol extends Symbol
{
  // @var SymbolMap  interfaces
  public $ifaces;
  
  // @var SymbolMap  fields
  public $fields;
  
  // @var SymbolMap  methods
  public $methods;
  
  /**
   * constructor
   * 
   * @param string   $id
   * @param Location $loc  
   * @param Scope $scope
   * @param int   $flags
   */
  public function __construct($id, Location $loc, Scope $scope, $flags)
  {
    // init symbol
    parent::__construct($id, SYM_KIND_IFACE, $loc, $scope, $flags);
    
    $this->ifaces = new SymbolMap;
    $this->fields = new SymbolMap;
    $this->methods = new SymbolMap;
  }
  
  /**
   * returns the namespace
   * 
   * @return int
   */
  public function row()
  {
    // definition namespace
    return SYM_NS1;
  }
}
