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
  SYM_FLAG_WEAK       = 0x1000, // symbol is weak (can be replaced)
  SYM_FLAG_EXPORT     = 0x2000  // symbol is a export
;

// typo recover
const SYM_FLAGS_NONE = SYM_FLAG_NONE;

const
  // various symbol kinds 
  SYM_KIND_MODULE = 1, 
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
  
  // true if the symbol was exported
  public $exported;
  
  // the name of the exported symbol
  public $export_alias;
  
  public function __construct($kind, $name, $flags, Location $loc = null)
  {
    $this->name = $name;
    $this->flags = $flags;
    $this->loc = $loc;
    $this->reads = 0;
    $this->writes = 0;
    $this->kind = $kind;
    $this->exported = false;
  }
  
  public function is_ref()
  {
    return $this->kind > SYM_REF_DIVIDER;
  }
  
  public function debug($dp = '', $pf = '') 
  {
    $flags = symflags_to_str($this->flags);
    print "$dp$pf{$this->name} (flags=$flags) (rw={$this->reads}/{$this->writes})";
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
    $this->value->symbol = $this;
  }
  
  /* ------------------------------------ */
  
  public function __clone()
  {
    $this->scope = null;
    $this->value = clone $this->value;
    $this->value->symbol = $this;
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

interface ClassLikeSym {}

/** class symbol */
class ClassSym extends Symbol implements ClassLikeSym
{
  // super class
  public $super;
  
  // interfaces
  public $impls;
  
  // member symboltable
  public $members;
  
  // inherit symboltable
  public $inherit;
  
  public function __construct($name, $flags, Location $loc = null)
  {
    parent::__construct(SYM_KIND_CLASS, $name, $flags, $loc);
    $this->members = new SymTable;
    $this->inherit = new SymTable;
  }
  
  /* ------------------------------------ */
  
  public function debug($dp = '', $pf = '')
  {
    parent::debug($dp, $pf);
    print " class\n";
    
    $this->members->debug("  $dp", '# ');
    print "\n";
  }
}

/** interface symbol */
class IFaceSym extends Symbol implements ClassLikeSym
{
  // extended interfaces
  public $exts;
  
  // member symboltable
  public $members;
  
  // inherit symboltable
  public $inherit;
  
  public function __construct($name, $flags, Location $loc = null)
  {
    parent::__construct(SYM_KIND_IFACE, $name, $flags, $loc);
    $this->members = new SymTable;
    $this->inherit = new SymTable;
  }
  
  /* ------------------------------------ */
  
  public function debug($dp = '', $pf = '')
  {
    parent::debug($dp, $pf);
    print " iface\n";
    
    $this->members->debug("  $dp", '# ');
    print "\n";
  }
}

/** function symbol */
class FnSym extends Symbol
{
  // function-scope 
  public $fn_scope;
  
  // parameters
  public $params;
  
  // calls
  public $calls = 0;
  
  public function __construct($name, $flags, Location $loc = null)
  {
    parent::__construct(SYM_KIND_FN, $name, $flags, $loc);
    $this->params = [];
  }
  
  /* ------------------------------------ */
  
  public function debug($dp = '', $pf = '')
  {
    parent::debug($dp, $pf);
    print " function\n";
    
    if ($this->fn_scope)
      $this->fn_scope->debug("  $dp", '@ ');
  }
}

/** parameter symbol */
class ParamSym extends VarSym
{
  // hint
  public $hint;
  
  // rest-param?
  public $rest;
  
  public function __construct($name, Value $value, $flags, Location $loc)
  {
    parent::__construct($name, $value, $flags, $loc);
    $this->hint = null;
    $this->rest = false;
  }
}

/** module symbol */
class ModuleSym extends Symbol
{
  // the module
  public $module;
  
  public function __construct($name, Module $module, $flags, Location $loc = null)
  {
    parent::__construct(SYM_KIND_MODULE, $name, $flags, $loc);
    $this->module = $module;
  }
  
  /* ------------------------------------ */
  
  public function debug($dp = '', $pf = '')
  {
    parent::debug($dp, '-> ');
    print " module\n";
    
    $this->module->debug("$dp   ", '@ ');
  }
}

/** a symbol reference */
class SymbolRef extends Symbol
{
  // the symbol
  public $symbol;
  
  // the full name of this reference
  public $path;
  
  public function __construct($kind, $name, Symbol $sym, $path, Location $loc = null)
  {
    parent::__construct($kind, $name, $sym->flags, $loc);
    $this->symbol = $sym;
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
  
  public static function from($id, Symbol $sym, $path, Location $loc)
  {
    // no need to create a reference for a reference
    if ($sym->kind > SYM_REF_DIVIDER)
      // ignore the given path and use the reference-path which is 
      // most likely shorter (points directly to the origin)
      return new SymbolRef($sym->kind, $id, $sym->symbol, $sym->path, $loc);
    
    // create a new refernece
    return new SymbolRef($sym->kind + SYM_REF_DIVIDER, $id, $sym, $path, $loc);
  }
}

/** module refernece */
class ModuleRef extends SymbolRef
{
  // the module
  public $module;
  
  public function __construct($name, ModuleSym $mod, $path, Location $loc)
  {
    parent::__construct(REF_KIND_MODULE, $name, $mod, $path, $loc);
    $this->module = $mod->module;
  }
    
  /* ------------------------------------ */
  
  public static function from($id, Symbol $mod, $path, Location $loc)
  {
    assert($mod->kind === SYM_KIND_MODULE ||
           $mod->kind === REF_KIND_MODULE);
    
    // no need to create a reference for a reference
    if ($mod->kind === REF_KIND_MODULE)
      // ignore the given path and use the reference-path which is 
      // most likely shorter (points directly to the origin)
      return new ModuleRef($id, $mod->symbol, $mod->path, $loc);
    
    // just forward to the constructor (for now)
    return new ModuleRef($id, $mod, $path, $loc);
  }
}
