<?php

namespace phs\front\ast;

class TraitItem extends Node
{
  public $src_mods;
  public $src_id;
  public $dest_mods;
  public $dest_id;
  
  public function __construct($src_mods, $src_id, $dest_mods, $dest_id)
  {
    $this->src_mods = $src_mods;
    $this->src_id = $src_id;
    $this->dest_mods = $dest_mods;
    $this->dest_id = $dest_id;
  }
}
