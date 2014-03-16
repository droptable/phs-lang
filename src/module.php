<?php

namespace phs;

class Module extends Scope
{
  // this is the root-module
  public $root;
  
  // name of this module
  public $name;
  
  // parent module
  private $prev;
  
  // internal scope, delegates back to this module
  public $scope;
  
  /**
   * constructor
   * 
   * @param Module $prev
   */
  public function __construct($name, Module $prev = null)
  {
    parent::__construct($prev);
    $this->name = $name;
    $this->root = false;
    $this->scope = new UnitScope($this);
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
   * @param  int $flags
   * @param  boolean $create
   * @param  Location $loc
   * @return Module
   */
  public function fetch($path, $create = true, $flags = SYM_FLAG_NONE, Location $loc = null)
  {
    if (!is_array($path))
      $path = [ $path ];
    
    $curr = $this;
    
    foreach ($path as $name) {
      if (!$curr->has_child($name)) {
        if (!$create) return null;
        
        $newm = new Module($name, $curr);
        $news = new ModuleSym($name, $newm, $flags, $loc);
        
        // there is a collision
        if (!$curr->scope->add($name, $news))
          return null;
        
        $curr = $newm;
        
      } else
        $curr = $curr->get_child($name);  
    }
    
    return $curr;
  }
  
  public function has_child($name)
  {
    // the symbol must be a direct child of this module -> do not walk up!
    $sym = $this->get($name, false, null, false);
    return $sym && $sym->kind === SYM_KIND_MODULE;
  }
  
  public function get_child($name)
  {
    if (!$this->has_child($name))
      return null;
    
    return $this->get($name)->module;
  }
  
  /* ------------------------------------ */
  
  public function debug($dp = '', $pf = '-> ')
  {       
    if ($this->root) {
      print "-> <root>\n";
      $dp = "$dp   ";
    }
    
    // the root-module has no symbols
    parent::debug($dp, '@ ');
  }
}
