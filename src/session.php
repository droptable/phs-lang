<?php

namespace phs;

class Session
{
  public $imports;
  
  public function __construct()
  {
    $this->imports = new ImportMap;
  }
}

class ImportMap
{
  public function add() {}
}
