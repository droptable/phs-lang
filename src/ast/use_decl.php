<?php

namespace phs\ast;

use phs\Token;
use phs\Location;

class UseDecl extends Decl
{
  // @var array<Token>
  public $mods;
  
  // @var Name|UseAlias|UseUnpack
  public $item;
  
  /**
   * constructor
   *
   * @param Location                    $loc
   * @param array|null                  $mods
   * @param Name|UseAlias|UseUnpack     $item
   */
  public function __construct(Location $loc, array $mods = null, $item)
  {
    parent::__construct($loc);
    
    assert($item instanceof Name ||
           $item instanceof UseAlias ||
           $item instanceof UseUnpack);
    
    $this->mods = $mods;
    $this->item = $item;
  }

  public function __clone()
  {
    $this->item = clone $this->item;
    
    parent::__clone();
  }
}
