<?php

namespace phs\front;

use phs\Config;
use phs\Logger;

use phs\front\ast\Node;
use phs\front\ast\Unit;

/** ast walker - texas ranger */
class Walker
{
  // visitors
  private $vstk;
  
  /**
   * constructor
   * 
   * @param Visitor $vst
   */
  public function __construct(Visitor $vst = null)
  {
    // visitor stack
    $this->vstk = [];
    if ($vst) $this->add($vst);
  }
  
  /**
   * adds an visitor
   * 
   * @param Visitor $vst
   */
  public function add(Visitor $vst)
  {
    $this->vstk[] = $vst;
  }
  
  /** 
   * start the walker
   * 
   * @param  Unit   $unit
   */
  public function walk_unit(Unit $node)
  {
    $this->walk_some($node);
  }
      
  /**
   * walks a node or an array
   * 
   * @param  Node|array $node
   */
  public function walk_some($some)
  {    
    // nothing?
    if ($some === null)
      return; // leave early
          
    // walk array-of-nodes
    elseif (is_array($some))
      foreach ($some as $item)
        $this->walk_some($item);
    
    // walk node
    elseif ($some instanceof Node)
      $this->visit($some);
    
    // error
    else {
      Logger::error('don\'t know how to traverse item \\');
      
      ob_start();
      var_dump($some);
      $log = ob_get_clean();
      
      Logger::error(substr($log, 0, 500));
    }
  }
  
  /**
   * walks a node
   * 
   * @param  Node $node
   */
  protected function visit(Node $node) 
  {
    $kind = $node->kind(); 
       
    foreach ($this->vstk as $vst)
      $vst->{'visit_' . $kind}($node);  
  }
}
