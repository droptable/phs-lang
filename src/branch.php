<?php

namespace phs;

require_once 'scope.php';

/** 
 * a branch is a lightweigth scope.
 * 
 * symbols can be duplicated and moved exclusively to the branch.
 * all get() calls gonna be redirected to the duplicate.
 * 
 */
class Branch extends Scope
{
  /**
   * constructor
   * 
   * @param Scope $prev
   */
  public function __construct(Scope $prev)
  {
    // make sure we have a parent scope
    parent::__construct($prev);
  }
  
  /**
   * make a copy of a symbol from the parent scope
   * and move it to the branch 
   * 
   * @param  string $id
   * @return Symbol
   */
  public function move($id)
  {
    $sym = $this->get_prev()->get($id, false, null, true);
    
    if ($sym === null)
      return null;
    
    # print "moving `$id` to branch\n";
    
    $dup = clone $sym;
    parent::set($id, $dup);
  }
  
  public function get($id, $track = true, Location $loc = null, $walk = true)
  {
    // check if the symbol was moved to this branch
    if (!$this->has($id)) $this->move($id); // move it
    
    return parent::get($id, $track, $loc, $walk);
  }
  
  public function add($id, Symbol $sym)
  {
    return $this->get_prev()->add($id, $sym);
  }
  
  public function set($id, Symbol $sym)
  {
    return $this->get_prev()->set($id, $sym);
  }
}
