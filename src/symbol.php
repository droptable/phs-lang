<?php

namespace phs;

use phs\ast\Name;

const
  SYM_FLAG_NONE       = 0x0000, // no flags
  SYM_FLAG_CONST      = 0x0001, // symbol is constant
  SYM_FLAG_FINAL      = 0x0002, // symbol is final
  SYM_FLAG_GLOBAL     = 0x0004, // symbol is a global-reference
  SYM_FLAG_STATIC     = 0x0008, // symbol is file/module static
  SYM_FLAG_PUBLIC     = 0x0010, // symbol is public
  SYM_FLAG_PRIVATE    = 0x0020, // symbol is private
  SYM_FLAG_PROTECTED  = 0x0040, // symbol is protected
  SYM_FLAG_SEALED     = 0x0080, // symbol is sealed (for functions)
  SYM_FLAG_INLINE     = 0x0100, // symbol is inline (for functions)
  SYM_FLAG_EXTERN     = 0x0200, // symbol is extern
  SYM_FLAG_ABSTRACT   = 0x0400, // symbol is abstract (for classes)
  SYM_FLAG_INCOMPLETE = 0x0800, // symbol is incomplete
  SYM_FLAG_WEAK       = 0x1000  // symbol is a weak reference (the type of this reference was a assumption)
;

const 
  // not a real symbol
  SYM_KIND_MODULE = 1, 
  
  // various symbol kinds
  SYM_KIND_CLASS = 2,
  SYM_KIND_TRAIT = 3,
  SYM_KIND_IFACE = 4,
  SYM_KIND_VAR = 5,
  SYM_KIND_FN = 6,
  
  // extra symbols
  SYM_KIND_VALUE = 7,
  SYM_KIND_EMPTY = 8,
  
  // divider
  SYM_REF_DIVIDER = 9,
  
  // reference kinds
  REF_KIND_MODULE = 10,
  REF_KIND_CLASS = 11,
  REF_KIND_TRAIT = 12,
  REF_KIND_IFACE = 13,
  REF_KIND_VAR = 14,
  REF_KIND_FN = 15,
  
  // invalid references
  REF_KIND_VALUE = 16,
  REF_KIND_EMPTY = 17
;

abstract class Symbol 
{
  // location of the symbol
  public $loc;
  
  // name of the symbol
  public $name;
  
  // kind
  public $kind;
  
  // flags
  public $flags;
  
  // read-access tracker
  public $reads;
  
  // write-access tracker
  public $writes;
  
  // the scope where this symbol is defined
  public $scope;
  
  public function __construct($kind, $name, $flags, Location $loc = null)
  {
    $this->name = $name;
    $this->flags = $flags;
    $this->loc = $loc;
    $this->reads = 0;
    $this->kind = $kind;
  }
  
  public function is_ref()
  {
    return $this->kind > SYM_REF_DIVIDER;
  }
  
  public function debug($dp = '', $pf = '') 
  {
    $flags = symflags_to_str($this->flags);
    print "$dp$pf{$this->name} (flags=$flags)";
  }
}

/** a variable symbol */
class VarSym extends Symbol
{
  public function __construct($name, $flags, Location $loc = null)
  {
    parent::__construct(SYM_KIND_VAR, $name, $flags, $loc);
  }
  
  /* ------------------------------------ */
  
  public function debug($dp = '', $pf = '')
  {
    parent::debug($dp, $pf);
    print " var\n";
  }
}

/** value symbol */
class ValueSym extends Symbol
{
  public $value;
  
  public function __construct($name, $flags, $value, Location $loc = null)
  {
    parent::__construct(SYM_KIND_VALUE, $name, $flags, $loc);
    $this->value = $value;
  }
  
  /* ------------------------------------ */
  
  public function debug($dp = '', $pf = '')
  {
    parent::debug($dp, $pf);
    
    $value = $this->value;
    $value = $value ? $value->value : '(none)';
    
    print " value={$value}\n";
  }
}

/** empty sym (like value-sym) but the value gets filled-in later */
class EmptySym extends ValueSym
{
  public function __construct($name, $flags, Location $loc = null)
  {
    parent::__construct(SYM_KIND_EMPTY, $name, $flags, null, $loc);
  }
}

/** class symbol */
class ClassSym extends Symbol
{
  // member symboltable
  public $mst;
  
  public function __construct($name, $flags, Location $loc = null)
  {
    parent::__construct(SYM_KIND_CLASS, $name, $flags, $loc);
    $this->mst = new SymTable;
  }
  
  /* ------------------------------------ */
  
  public function debug($dp = '', $pf = '')
  {
    parent::debug($dp, $pf);
    print " class\n";
    
    $this->mst->debug("  $dp", '# ');
    print "\n";
  }
}

/** function symbol */
class FnSym extends Symbol
{
  // function-scope 
  public $fn_scope;
  
  // param count
  public $params;
  
  public function __construct($name, $flags, Location $loc = null)
  {
    parent::__construct(SYM_KIND_FN, $name, $flags, $loc);
    $this->params = 0;
  }
  
  /* ------------------------------------ */
  
  public function debug($dp = '', $pf = '')
  {
    parent::debug($dp, $pf);
    print " fn\n";
    
    $this->fn_scope->debug("  $dp", '@ ');
  }
}

/** a symbol reference */
class SymRef extends Symbol
{
  // the base module (can be <root>)
  public $base;
  
  // the full name of this reference
  public $path;
  
  public function __construct($kind, $name, $flags, Module $base, Name $path, Location $loc = null)
  {
    parent::__construct($kind, $name, $flags, $loc);
    $this->base = $base;
    $this->path = $path;
  }
    
  /* ------------------------------------ */
  
  public function debug($dp = '', $pf = '')
  {
    parent::debug($dp, $pf);
    
    $kind = refkind_to_str($this->kind);
    print " {$kind} (base={$this->base->name}) -> ";
    print path_to_str($this->path, false);
    print "\n";
  }
  
  /* ------------------------------------ */
  
  public static function from(Symbol $sym, Module $base, Name $path, Location $loc)
  {
    assert($sym->kind < SYM_REF_DIVIDER);
    $kind = SYM_REF_DIVIDER + $sym->kind;
    
    return new SymRef($kind, $sym->name, $sym->flags, $base, $path, $loc);
  }
}
