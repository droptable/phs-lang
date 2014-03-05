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

/** delegateing class-scope */
class ClassScope extends Scope
{
  // class-symbol
  private $csym;
  
  public function __construct(ClassSym $csym, Scope $prev = null)
  {
    parent::__construct($prev);
    $this->csym = $csym;
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
    return $this->csym->mst->add($id, $sym);
  }
  
  /**
   * sets/updates a symbol 
   * 
   * @param string $id
   * @param Symbol $sym
   */
  public function set($id, Symbol $sym)
  {
    return $this->csym->mst->set($id, $sym);
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
    if ($this->csym->mst->has($id))
      return true;
    
    // use scope
    return parent::has($id);
  }
  
  /**
   * return a class-member.
   * fallback to scope
   * 
   * @param  string $id
   * @param boolean $track 
   * @param Location $loc
   * @return Symbol
   */
  public function get($id, $track = true, Location $loc = null, $walk = true)
  {
    // check class-members first
    if ($this->csym->mst->has($id))
      return $this->csym->mst->get($id);
    
    // use scope
    return parent::get($id, $track, $loc, $walk);
  }
}

/** function scope */
class FnScope extends Scope
{
  // the function symbol
  private $fsym;
  
  public function __construct(FnSym $fsym, Scope $prev = null)
  {
    parent::__construct($prev);
    $this->fsym = $fsym;
  }
}
