<?php

namespace phs\front\ast;

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
}
