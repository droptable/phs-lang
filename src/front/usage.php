<?php

namespace phs\front;

require_once 'utils.php';

use phs\util\Map;
use phs\util\Entry;

use phs\front\ast\Name;
use phs\front\ast\Ident;

// usage-kinds
const
  USE_KIND_NONE   = 0, // unresolved
  USE_KIND_MODULE = 1, // resolved: module
  USE_KIND_SYMBOL = 2  // resolved: symbol
;

// usage-hints
const
  USE_HINT_NONE   = 0, // no hint
  USE_HINT_MODULE = 1, // must be resolved as module
  USE_HINT_SYMBOL = 2  // must be resolved as symbol
;

/** usage symbol */
class Usage implements Entry
{  
  // @var Location  the location where this import was found
  // the location of the given name (or item) is used here
  public $loc;
  
  // @var bool
  public $pub = false;
  
  // @var bool  relative to current module
  public $self = false;

  // @var string  the name of the imported symbol (alias)
  public $item;

  // @var string  the original name of the imported symbol 
  public $orig; 

  // @var array  the path of the imported symbol
  public $path;
  
  // @var array  origin
  public $root;
  
  /**
   * constructor
   *
   * @param RootScope $root
   * @param bool $pub
   * @param Name $name
   * @param Usage $base a other imported symbol for a relative import
   * @param Ident $item a user-defined name (alias)
   */
  public function __construct(RootScope $root, $pub, Name $name, Usage $base = null, Ident $item = null)
  {
    $narr = name_to_arr($name);
        
    // replace alias to get the real path
    if ($base && $base->orig !== $base->item)
      array_splice($narr, 0, 1, $base->orig);
    
    $this->pub = $pub;
    $this->loc = $item ? $item->loc : $name->loc;
    $this->self = $name->self;
    $this->root = $root;
    $this->orig = array_pop($narr);
    $this->item = $item ? ident_to_str($item) : $this->orig;
    $this->path = $base ? $base->path : [];
    
    // remove symbol-name from base-path
    if ($base) array_pop($this->path);
    
    foreach ($narr as $npth)
      $this->path[] = $npth;
    
    // push symbol-name to get the complete path
    $this->path[] = $this->orig;
  }
  
  /**
   * returns the entry-key
   * 
   * @return string
   */
  public function key()
  {
    return $this->item;
  }
  
  /* ------------------------------------ */
  
  public function dump($tab = '')
  {
    static $type = [ '', 'module', 'symbol' ];
    
    echo "\n", $tab, '& ', arr_to_path($this->path), ' -> ', $this->item;
    
    // use is resolved
    if ($this->kind !== USE_KIND_NONE)
      echo ' (resolved) kind: ', $type[$this->kind];
    
    // use has a hint
    elseif ($this->hint !== USE_HINT_NONE)
      echo ' (unresolved) hints:', 
        ' ', $type[$this->hint & USE_HINT_MODULE], 
        ' ', $type[$this->hint & USE_HINT_SYMBOL];
    
    // use is not resolved/hinted
    else
      echo ' (unresolved) ';
  }
}

/** usage map */
class_alias('phs\\util\\Map', 'phs\\front\\UsageMap');
