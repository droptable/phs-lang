<?php

namespace phs\ast;

class Param extends Node
{
  public $hint;
  public $id;
  public $init;
  public $opt;
  
  public function __construct($hint, $id, $init, $opt)
  {
    $this->hint = $hint;
    $this->id = $id;
    $this->init = $init;
    $this->opt = $opt;
  }
}
