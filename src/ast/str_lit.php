<?php

namespace phs\ast;

class StrLit extends Expr
{
  public $data;
  public $flag;
  public $delim;
  public $parts;
  
  public function __construct($tok)
  {
    $this->data = $tok->value;
    $this->flag = $tok->flag;
    $this->delim = $tok->delim;
    $this->parts = [];
  }
  
  public function add($slice)
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
