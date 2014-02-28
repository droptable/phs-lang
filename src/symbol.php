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

// typo recover
const SYM_FLAGS_NONE = SYM_FLAG_NONE;

const 
  // not a real symbol
  SYM_KIND_MODULE = 1, 
  
  // various symbol kinds
  SYM_KIND_CLASS = 2,
  SYM_KIND_TRAIT = 3,
  SYM_KIND_IFACE = 4,
  SYM_KIND_VAR = 5,
  SYM_KIND_FN = 6,
  
  // divider
  SYM_REF_DIVIDER = 7,
  
  // reference kinds
  REF_KIND_MODULE = 8,
  REF_KIND_CLASS = 9,
  REF_KIND_TRAIT = 10,
  REF_KIND_IFACE = 11,
  REF_KIND_VAR = 12,
  REF_KIND_FN = 13
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
    $this->writes = 0;
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
  // the value
  public $value;
  
  /**
   * constructor
   * 
   * @param string $name
   * @param Value $value
   * @param int $flags
   * @param Location $loc
   */
  public function __construct($name, Value $value, $flags, Location $loc = null)
  {
    parent::__construct(SYM_KIND_VAR, $name, $flags, $loc);
    $this->value = $value;
  }
  
  /* ------------------------------------ */
  
  public function debug($dp = '', $pf = '')
  {
    parent::debug($dp, $pf);
    print " variable";
    
    $value = $this->value ?: '(none)';
    print " value={$value}\n";
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
    print " function\n";
    
    $this->fn_scope->debug("  $dp", '@ ');
  }
}

/** a symbol reference */
class SymbolRef extends Symbol
{
  // the symbol
  public $sym;
  
  // the full name of this reference
  public $path;
  
  public function __construct($kind, $name, Symbol $sym, Name $path, Location $loc = null)
  {
    parent::__construct($kind, $name, SYM_FLAG_NONE, $loc);
    $this->sym = $sym;
    $this->path = $path;
  }
    
  /* ------------------------------------ */
  
  public function debug($dp = '', $pf = '')
  {
    parent::debug($dp, $pf);
    
    $kind = refkind_to_str($this->kind);
    print " {$kind} -> ";
    print path_to_str($this->path, false);
    print "\n";
  }
  
  /* ------------------------------------ */
  
  public static function from($id, Symbol $sym, Name $path, Location $loc)
  {
    assert($sym->kind < SYM_REF_DIVIDER);
    $kind = SYM_REF_DIVIDER + $sym->kind;
    
    return new SymbolRef($kind, $id, $sym, $path, $loc);
  }
}

/** module refernece */
class ModuleRef extends Symbol
{
  // the module
  public $mod;
  
  // full path of this reference
  public $path;
  
  public function __construct($name, Module $mod, Name $path, Location $loc)
  {
    parent::__construct(REF_KIND_MODULE, $name, SYM_FLAG_NONE, $loc);
    $this->mod = $mod;
    $this->path = $path;
  }
  
  /* ------------------------------------ */
  
  public function debug($dp = '', $pf = '') 
  {
    parent::debug($dp, $pf);
    
    $kind = refkind_to_str($this->kind);
    print " {$kind} -> ";
    print path_to_str($this->path, false);
    print "\n";
  }
}
