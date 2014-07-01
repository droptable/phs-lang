<?php

namespace phs\front;

require_once 'utils.php';
require_once 'scope.php';
require_once 'symbols.php';

use phs\util\Map;
use phs\util\Entry;

/** module */
class Module extends Scope implements Entry
{
  // @var string  module name
  public $id;
  
  // @var ModuleMap  submodules
  public $subm;
  
  // @var SymbolMap  exports
  public $emap;
  
  // @var Scope  types
  public $types;
  
  /**
   * constructor
   * 
   * @param string $id
   * @param Module $prev
   */
  public function __construct($id, Module $prev = null)
  {
    parent::__construct($prev);
    
    $this->id = $id;
    $this->subm = new ModuleMap;
  }
  
  /**
   * returns the absolute path of this module
   * 
   * @return array
   */
  public function path()
  {
    $path = [];
    
    // walk up to root
    for ($prev = $this->prev; 
         $prev !== null; 
         $prev = $prev->prev)
      $path[] = $prev->id;
    
    // reverse for the correct order
    $path = array_reverse($path);
    
    // add own name
    $path[] = $this->id;
    
    // done
    return $path;
  }
  
  /**
   * @see Entry#key()
   * @return string
   */
  public function key()
  {
    return $this->id;
  }
}

/** module-map */
class ModuleMap extends Map
{
  /**
   * constructor
   */
  public function __construct()
  {
    parent::__construct();
  }
  
  /**
   * typecheck
   * 
   * @param  Entry $ent
   * @return boolean
   */
  protected function check(Entry $ent)
  {
    return $ent instanceof Module;
  }
}
