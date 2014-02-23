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
  SYM_FLAG_WEAK       = 0x1000  // symbol is weak (the type of this symbol was a assumption)
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
  
  public function __construct($name, $flags, Location $loc = null)
  {
    $this->name = $name;
    $this->flags = $flags;
    $this->loc = $loc;
    $this->reads = 0;
    $this->kind = 'symbol';
  }
  
  public function debug($dp) 
  {
    $flags = symflags_to_str($this->flags);
    print "$dp{$this->name} (flags=$flags)";
  }
}

/** a expression symbol */
class ExprSym extends Symbol
{
  public $expr;
  
  public function __construct($name, $flags, $expr, Location $loc = null)
  {
    parent::__construct($name, $flags, $loc);
    $this->expr = $expr;
  }
  
  /* ------------------------------------ */
  
  public function debug($dp)
  {
    $flags = symflags_to_str($this->flags);
    print "$dp{$this->name} (flags={$flags}) (reads={$this->reads}) expr\n";
  }
}

/** value symbol */
class ValueSym extends Symbol
{
  public $value;
  
  public function __construct($name, $flags, $value, Location $loc = null)
  {
    parent::__construct($name, $flags, $loc);
    $this->value = $value;
  }
  
  /* ------------------------------------ */
  
  public function debug($dp)
  {
    parent::debug($dp);
    
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
    parent::__construct($name, $flags, null, $loc);
  }
}

/** class symbol */
class ClassSym extends Symbol
{
  // member symboltable
  public $mst;
  
  public function __construct($name, $flags, Location $loc = null)
  {
    parent::__construct($name, $flags, $loc);
    $this->mst = new SymTable;
    $this->kind = 'class';
  }
  
  /* ------------------------------------ */
  
  public function debug($dp)
  {
    parent::debug($dp);
    print " class\n";
    
    if (substr($dp, -2) === '@ ')
      $dp = substr($dp, 0, -2);
    
    $this->mst->debug("  $dp# ");
    print "\n";
  }
}

/** function symbol */
class FnSym extends Symbol
{
  // param count
  public $params;
  
  public function __construct($name, $flags, Location $loc = null)
  {
    parent::__construct($name, $flags, $loc);
    $this->params = 0;
    $this->kind = 'fn';
  }
  
  /* ------------------------------------ */
  
  public function debug($dp)
  {
    parent::debug($dp);
    print " fn\n";
  }
}

/** module reference */
class ModuleRef extends Symbol
{
  // the base module
  public $base;
  
  // the full name of this module
  public $path;
  
  public function __construct($name, $flags, Module $base, Name $path, Location $loc = null)
  {
    parent::__construct($name, $flags, $loc);
    $this->base = $base;
    $this->path = $path;
    $this->kind = 'module';
  }
  
  public function debug($dp)
  {
    parent::debug($dp);
    print " module ref (base={$this->base->name}) -> ";
    print path_to_str($this->path, false);
    print "\n";
  }
}
