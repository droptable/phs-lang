<?php

namespace phs;

use phs\ast\Node;

/** expression validator */
class Validator extends Walker
{
  // context
  private $ctx;
  
  // scope
  private $scope;
  
  // status
  private $valid;
  
  /**
   * constructor
   * 
   * @param Context $ctx
   */
  public function __construct(Context $ctx)
  {
    $this->ctx = $ctx;
  }
  
  /**
   * start validating
   * 
   * @param  Node   $expr
   * @param  Scope  $scope
   * @param  boolean $aur allow unknown referneces
   * @return boolean
   */
  public function validate(Node $expr, Scope $scope = null, $aur = false)
  {
    $this->scope = $scope;
    $this->valid = true;
    
    return $this->validate_node($expr);
  }
  
  protected function validate_node(Node $node)
  {
    $this->walk_node($node);
    return $this->valid;
  }
  
  /* ------------------------------------ */
  
  public function visit_call_expr($node)
  {
    if ($this->validate_node($node->callee)) {
      
    }
    
    if ($node->args !== null)
      $this->validate_node($node->args);
  }
  
  public function visit_name($node)
  {
    if (!lookup_name($node, $this->scope, $this->ctx)) {
      $this->error_at($node->loc, ERR_ERROR, 'unknown reference to `%s`', name_to_str($node));
      $this->valid = false;
    }
  }
  
  /* ------------------------------------ */
  
  /**
   * error handler
   * 
   */
  public function error_at()
  {
    $args = func_get_args();    
    $loc = array_shift($args);
    $lvl = array_shift($args);
    $msg = array_shift($args);
    
    $this->ctx->verror_at($loc, COM_VLD, $lvl, $msg, $args);
  }
}
