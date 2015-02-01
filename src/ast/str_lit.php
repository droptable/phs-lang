<?php

namespace phs\ast;

use phs\Token;
use phs\Location;

class StrLit extends Expr
{
  // @var string
  public $data;
  
  // @var string
  public $flag;
  
  // @var string
  public $delim;
  
  // @var array<Expr|StrLit>
  public $parts;
  
  /**
   * constructor
   *
   * @param Location $loc
   * @param Token    $tok
   */
  public function __construct(Location $loc, Token $tok)
  {
    $this->data = $tok->value;
    $this->flag = $tok->flag;
    $this->delim = $tok->delim;
    $this->parts = [];
  }
  
  /**
   * adds a part
   *
   * @param Expr $slice
   */
  public function add(Expr $slice)
  {
    $this->parts[] = $slice;
  }

  public function __clone()
  {
    if ($this->parts) {
      $parts = $this->parts;
      $this->parts = [];
      
      foreach($parts as $part)
        $this->parts[] = clone $part;
    }
    
    parent::__clone();
  }
}
