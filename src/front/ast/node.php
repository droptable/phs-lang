<?php

namespace phs\front\ast;

abstract class Node
{
  public $loc;
  
  // string-represetation of this node-name
  private $kind = null;  
  
  /**
   * set/get the kind of this node
   * 
   * @param  string $set
   * @return string
   */
  public function kind($set = null)
  {
    if ($set) 
      $this->kind = $set;
    else {
      if (!$this->kind) {
        $name = get_class($this);
        $name = substr(strrchr($name, "\\"), 1);
        $name = preg_replace('/([a-z\d])([A-Z])/', '$1_$2', $name);
        $name = strtolower($name);
        $this->kind = $name;
      }
      
      return $this->kind;
    }
  }
}
