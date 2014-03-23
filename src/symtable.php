<?php

namespace phs;

use \ArrayIterator;
use \IteratorAggregate;

class SymTable implements IteratorAggregate
{
  // symbols
  private $syms;
  
  /**
   * constructor
   * 
   */
  public function __construct()
  {
    $this->syms = [];
  }
  
  /**
   * checks if at least one symbol is available
   * 
   * @return boolean
   */
  public function avail()
  {
    return !empty ($this->syms);
  }
  
  /**
   * add a symbol
   * 
   * @param string $name
   * @param Symbol $sym 
   * @return boolean
   */
  public function add($name, Symbol $sym)
  {
    if (!isset ($this->syms[$name])) {
      $this->syms[$name] = $sym;
      return true;
    }
    
    return false;
  }
  
  /**
   * set (assign) a symbol
   * 
   * @param string $name
   * @param Symbol $sym
   * @return Symbol the old symbol or null
   */
  public function set($name, Symbol $sym)
  {
    $old = null;
    
    if (isset ($this->syms[$name]))
      $old = $this->syms[$name];
    
    $this->syms[$name] = $sym;
    return $old;
  }
  
  /**
   * get a symbol
   * 
   * @param  string $name
   * @return Symbol
   */
  public function get($name)
  {
    if (isset ($this->syms[$name]))
      return $this->syms[$name];      
    
    return null;
  }
  
  /**
   * checks if a symbol exists
   * 
   * @param  string  $name
   * @return boolean    
   */
  public function has($name)
  {
    return isset ($this->syms[$name]);
  }
  
  /**
   * removes a symbol
   * 
   * @param  string $name
   */
  public function rem($name)
  {
    unset ($this->syms[$name]);
  }
  
  /* ------------------------------------ */
  
  public function getIterator()
  {
    return new ArrayIterator($this->syms);
  }
  
  /* ------------------------------------ */
  
  public function debug($dp = '', $pf = '')
  {
    if (empty ($this->syms))
      print "$dp{$pf}(empty symtable)\n";
    else
      foreach ($this->syms as $sym)
        $sym->debug($dp, $pf);
  }
}
