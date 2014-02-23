<?php

namespace phs;

class SymTable
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
   * add a symbol
   * 
   * @param string $name
   * @param Symbol $sym 
   * @return boolean
   */
  public function add($name, Symbol $sym)
  {
    if (!isset ($this->syms[$name])) {
      $this->set($name, $sym);
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
  public function get($name, $track = false)
  {
    if (isset ($this->syms[$name])) {
      $sym = $this->syms[$name];
      if ($track) $sym->reads++;
      
      return $sym;
    }
    
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
  
  public function debug($dp = '')
  {
    if (empty ($this->syms))
      print "$dp(empty symtable)\n";
    else
      foreach ($this->syms as $sym)
        $sym->debug($dp);
  }
}
