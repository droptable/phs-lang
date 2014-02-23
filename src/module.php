<?php

namespace phs;

class Module extends Scope
{
  // name of this module
  public $name;
  
  // child modules
  private $childs = [];
  
  /**
   * constructor
   * 
   * @param Module $prev
   */
  public function __construct($name, Module $prev = null)
  {
    parent::__construct($prev);
    $this->name = $name;
  }
  
  /**
   * returns the absolute path if this module
   * 
   * @param boolean $join
   * @return string|array
   */
  public function path($join = true)
  {
    $path = [];
    $prev = $this->get_prev();
    
    while ($prev instanceof Module) {
      $path[] = $prev->name;
      $prev = $prev->get_prev();
    }
    
    // pop <root>
    array_pop($path);
    
    $path = array_reverse($path);
    $path[] = $this->name;
    
    if ($join) return implode('::', $path);
    return $path;
  }
  
  /**
   * fetch a child-module or create it
   * 
   * @param  array $path
   * @return Module
   */
  public function fetch($path)
  {
    if (!is_array($path))
      $path = [ $path ];
    
    $curr = $this;
    
    foreach ($path as $name) {
      if (!$curr->has_child($name)) {
        $newm = new Module($name, $curr);
        $curr->set_child($name, $newm);
        $curr = $newm;
      } else
        $curr = $curr->get_child($name);
    }
    
    return $curr;
  }
  
  /**
   * check if a module exists
   * 
   * @param  string  $name
   * @return boolean
   */
  public function has_child($name)
  {
    return isset ($this->childs[$name]);
  }
  
  /**
   * add a new module
   * 
   * @param string $name
   * @param Module $mod
   */
  public function add_child($name, Module $mod)
  {
    if (!$this->has_child($name))
      $this->set_child($name, $mod);
  }
  
  /**
   * set (assign) a module
   * 
   * @param string $name
   * @param Module $mod
   */
  public function set_child($name, Module $mod)
  {
    $this->childs[$name] = $mod;
  }
  
  /**
   * return a module 
   * 
   * @param  string $name
   * @return Module
   */
  public function get_child($name)
  {
    if (!isset ($this->childs[$name]))
      return null;
    
    return $this->childs[$name];
  }
  
  /* ------------------------------------ */
  
  public function debug($dp = '')
  {       
    print "{$dp}-> {$this->name}\n";
    parent::debug("{$dp}   ");
    
    if (!empty ($this->childs))
      foreach ($this->childs as $child)
        $child->debug("$dp   ");
  }
}
