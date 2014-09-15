<?php

namespace phs\front\ast;

class Param extends Node
{
  public $ref;
  public $mods;
  public $hint;
  public $id;
  public $init;
  public $opt;
  
  // @var ParamSymbol
  public $symbol;
  
  public function __construct($ref, $mods, $hint, $id, $init, $opt)
  {
    $this->ref = $ref;
    $this->mods = $mods;
    $this->hint = $hint;
    $this->id = $id;
    $this->init = $init;
    $this->opt = $opt;
  }
}
