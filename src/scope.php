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
  
  // captured symbols (derived from parent scope)
  private $capt;
  
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
    $this->capt = new SymTable;
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
    
    if (!$res && $walk) {
      if ($this->prev) {
        $res = $this->prev->get($id, $track, $loc, $walk);
        
        if ($res !== null)
          $this->capt->add($id, $res);
        
        goto out;
      }
      
      // the symbol was not found and we do not have a parent scope!
      // add it as "unresolved-reference"
      if ($track && !isset ($this->uref[$id]))
        $this->uref[$id] = $loc;
    }
    
    out:
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
    $sym->binding = SYM_BIND_NONE;
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
    $sym->binding = SYM_BIND_NONE;
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
  
  /**
   * returns all captured variables
   * 
   * @return SymTable
   */
  public function get_captures()
  {
    return $this->capt;
  }
  
  public function has_captures()
  {
    return $this->capt->avail();
  }
  
  /* ------------------------------------ */
  
  public function debug($dp = '', $pf = '@ ')
  {
    if ($this->capt->avail())
      $this->capt->debug($dp, '& ');
    
    parent::debug($dp, $pf);
  }
}

/** delegating unit-scope */
class UnitScope extends Scope
{
  // the superior scope (module)
  public $super;
  
  /** 
   * constructor
   * 
   * @param Scope $super
   */
  public function __construct(Scope $super)
  {
    parent::__construct(/* super not needed */);
    $this->super = $super;
  }
  
  /**
   * adds a symbol
   * 
   * @param string $id
   * @param Symbol $sym
   * @return boolean
   */
  public function add($id, Symbol $sym)
  {
    if (parent::has($id) || $this->super->has($id))
      return false; // abort early
        
    if ($sym->kind > SYM_REF_DIVIDER || $sym->flags & SYM_FLAG_PRIVATE)
      return parent::add($id, $sym);
    
    return $this->super->add($id, $sym);
  }
  
  /** 
   * sets/updates a symbol
   * 
   * @param string $id
   * @param Symbol $sym
   * @return boolean
   */
  public function set($id, Symbol $sym)
  {
    if ($sym->kind > SYM_REF_DIVIDER || $sym->flags & SYM_FLAG_PRIVATE)
      return parent::set($id, $sym);
    
    return $this->super->set($id, $sym);
  }
  
  /** 
   * checks if symbol exists
   * 
   * @param  string  $id
   * @return boolean
   */
  public function has($id)
  {
    if (parent::has($id))
      return true;
    
    return $this->super->has($id);
  }
  
  /** 
   * returns a symbol
   * 
   * @param  string  $id
   * @param  boolean $track
   * @param  Location  $loc
   * @param  boolean $walk
   * @return Symbol
   */
  public function get($id, $track = true, Location $loc = null, $walk = true)
  {
    if (parent::has($id))
      return parent::get($id, $track, $loc, $walk);
    
    return $this->super->get($id, $track, $loc, $walk);
  }
  
  /**
   * returns the parent scope
   * 
   * @return Scope
   */
  public function get_prev()
  {
    return $this->super;
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
    $sym->binding = SYM_BIND_THIS;
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
    $sym->binding = SYM_BIND_THIS;
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
  
  // captured variables
  
  public function __construct(FnSym $fnsym, Scope $prev = null)
  {
    parent::__construct($prev);
    $this->symbol = $fnsym;
  }
}
