<?php

namespace phs\ast;

class Param extends Node
{
  public $mods;
  public $hint;
  public $id;
  public $init;
  public $opt;
  
  public function __construct($mods, $hint, $id, $init, $opt)
  {
    $this->mods = $mods;
    $this->hint = $hint;
    $this->id = $id;
    $this->init = $init;
    $this->opt = $opt;
  }
}
