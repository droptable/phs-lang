<?php

namespace phs;

require_once 'symbol.php';
require_once 'symtable.php';

class Scope extends SymTable
{
  // parent/previous scope
  private $prev;
  
  // unresolved references (via get())
  private $uref;
  
  // unreachable (dropped) symbols
  private $drop;
  
  /**
   * constructor
   * 
   * @param Scope $prev parent scope or null
   */
  public function __construct(Scope $prev = null)
  {
    parent::__construct();
    
    $this->prev = $prev;
    $this->uref = [];
    $this->drop = [];
  }
  
  /**
   * fetch a symbol 
   * 
   * @see SymTable#get()
   * @param  string $id
   * @param  bool   $track track undefined references
   * @param  Location $loc the location where this symbol was referenced
   * @param  boolean  $walk 
   * @return Symbol
   */
  public function get($id, $track = true, Location $loc = null, $walk = true)
  {    
    $res = parent::get($id, $track);
    
    if (!$res) {
      if ($this->prev && $walk)
        return $this->prev->get($id, $track, $loc, $walk);
      
      // the symbol was not found and we do not have a parent scope!
      // add it as "unresolved-reference"
      if ($track && !isset ($this->uref[$id]))
        $this->uref[$id] = $loc;
    }
    
    return $res;
  }
  
  /**
   * add a symbol
   * 
   * @see SymTable#add
   */
  public function add($id, Symbol $sym)
  {
    $sym->scope = $this;    
    return parent::add($id, $sym);
  }
  
  /**
   * set (assign) a symbol
   * 
   * @see SymTable#set
   */
  public function set($id, Symbol $sym)
  {
    $sym->scope = $this;
    return parent::set($id, $sym);
  }
  
  /**
   * drops a symbol (mark as unreachable)
   * this symbols are kept, because they may have sideefects
   * 
   * @param  string $id
   * @param  Symbol $sym
   */
  public function drop($id, Symbol $sym)
  {
    if (!isset ($this->drop[$id]))
      $this->drop[$id] = [];
    
    $this->rem($id);
    $this->drop[$id][] = $sym;
  }
  
  /**
   * returns the parent scope
   * 
   * @return Scope
   */
  public function get_prev()
  {
    return $this->prev;
  }
  
  /**
   * returns unresolved references this scope has collected
   * @return array
   */
  public function get_uref()
  {
    return $this->uref;
  }
  
  /**
   * returns all dropped symbols this scope has collected
   * 
   * @return array
   */
  public function get_drop()
  {
    return $this->drop;
  }
  
  /* ------------------------------------ */
  
  public function debug($dp = '', $pf = '@ ')
  {
    parent::debug($dp, $pf);
  }
}

/** delegating unit-scope */
class UnitScope extends Scope
{
  // the root-module
  public $root;
  
  public function __construct(Scope $root)
  {
    parent::__construct(/* root not needed */);
    
    assert($root instanceof Module);
    assert($root->root === true);
    
    $this->root = $root;
  }
  
  public function add($id, Symbol $sym)
  {
    if ($sym->kind > SYM_REF_DIVIDER || $sym->flags & SYM_FLAG_STATIC)
      return parent::add($id, $sym);
    
    return $this->root->add($id, $sym);
  }
  
  public function set($id, Symbol $sym)
  {
    if ($sym->kind > SYM_REF_DIVIDER || $sym->flags & SYM_FLAG_STATIC)
      return parent::set($id, $sym);
    
    return $this->root->set($id, $sym);
  }
  
  public function has($id)
  {
    if (parent::has($id))
      return true;
    
    return $this->root->has($id);
  }
  
  public function get($id, $track = true, Location $loc = null, $walk = true)
  {
    if (parent::has($id))
      return parent::get($id, $track, $loc, $walk);
    
    return $this->root->get($id, $track, $loc, $walk);
  }
  
  /**
   * returns the parent scope
   * 
   * @return Scope
   */
  public function get_prev()
  {
    return $this->root;
  }
}

/** delegateing class-scope */
class ClassScope extends Scope
{
  // class-symbol
  public $symbol;
  
  public function __construct(ClassLikeSym $clsym, Scope $prev = null)
  {
    parent::__construct($prev);
    $this->symbol = $clsym;
  }
  
  /**
   * adds a symbol to this scope (does not delegate)
   * 
   * @param string $id
   * @param Symbol $sym   
   */
  public function add($id, Symbol $sym)
  {
    # print "adding member '$id' to class-symtable\n";
    $sym->scope = $this;
    return $this->symbol->members->add($id, $sym);
  }
  
  /**
   * sets/updates a symbol 
   * 
   * @param string $id
   * @param Symbol $sym
   */
  public function set($id, Symbol $sym)
  {
    $sym->scope = $this;
    return $this->symbol->members->set($id, $sym);
  }
  
  /**
   * check if a symbol is defined in the class.
   * fallback to scope
   * 
   * @param  string  $id 
   * @return boolean
   */
  public function has($id)
  {
    // check class-members first
    if ($this->symbol->members->has($id))
      return true;
    
    // use scope
    return parent::has($id);
  }
  
  /**
   * return a class-member or a inherited member.
   * falls back to scope
   * 
   * @param  string $id
   * @param boolean $track 
   * @param Location $loc
   * @return Symbol
   */
  public function get($id, $track = true, Location $loc = null, $walk = true)
  {
    // check class-members first
    if ($this->symbol->members->has($id))
      return $this->symbol->members->get($id);
    
    // check inherited members
    if ($this->symbol->inherit->has($id))
      return $this->symbol->inherit->get($id);
    
    // use scope
    return parent::get($id, $track, $loc, $walk);
  }
}

/** function scope */
class FnScope extends Scope
{
  // the function symbol
  public $symbol;
  
  public function __construct(FnSym $fnsym, Scope $prev = null)
  {
    parent::__construct($prev);
    $this->symbol = $fnsym;
  }
}
