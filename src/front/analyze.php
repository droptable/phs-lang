<?php

namespace phs\front;

require_once 'utils.php';
require_once 'walker.php';
require_once 'scope.php';
#require_once 'symbols.php';

use \ArrayIterator;
use \IteratorAggregate;

use phs\Config;
use phs\Logger;
use phs\Session;

use phs\front\ast\Node;
use phs\front\ast\Unit;

use phs\front\ast\Name;
use phs\front\ast\Ident;
use phs\front\ast\UseAlias;
use phs\front\ast\UseUnpack;

class UseImport
{
  // the location where this import was found
  // -> the location of the given name (or item) is used here
  public $loc;
  
  // the name of the imported symbol
  public $item;
  
  // the original name of the imported symbol
  public $orig;
  
  // the path of the imported symbol
  public $path;
  
  /**
   * constructor
   * 
   * @param Name      $name
   * @param UseImport $base a other imported symbol for a relative import
   * @param Ident     $item a user-defined name (alias)
   */
  public function __construct(Name $name, UseImport $base = null, Ident $item = null)
  {        
    $narr = name_to_arr($name);
        
    // replace alias to get the real path
    if ($base && $base->orig !== $base->item)
      array_splice($narr, 0, 1, $base->orig);
        
    $this->loc = $item ? $item->loc : $name->loc;
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
}

class UseImportMap implements IteratorAggregate
{
  // all symbols
  private $imps = [];
  
  /**
   * adds a imported symbol
   * 
   * @param UseImport $imp
   * @return boolean
   */
  public function add(UseImport $imp)
  {
    if (isset ($this->imps[$imp->item]))
      return false;
    
    $this->imps[$imp->item] = $imp;
    return true;
  }
  
  /**
   * checks if a imported symbol exists
   * 
   * @param  string  $item
   * @return boolean
   */
  public function has($item)
  {
    return isset ($this->imps[$item]);
  }
  
  /**
   * returns a assigned symbol
   * 
   * @param  string $item
   * @return UseImport
   */
  public function get($item)
  {
    return $this->imps[$item];
  }
  
  /**
   * assignes or overrides a imported symbol
   * 
   * @param string    $item
   * @param UseImport $imp
   */
  public function set($item, UseImport $imp)
  {
    $this->imps[$item] = $imp;
  }
  
  /**
   * removes a symbol
   * 
   * @param  string $item
   */
  public function delete($item)
  {
    unset ($this->imps[$item]);
  }
  
  /* ------------------------------------ */
  
  /**
   * @see IteratorAggregate#getIterator
   * @return ArrayIterator
   */
  public function getIterator()
  {
    return new ArrayIterator($this->imps);
  }
}

/**
 * this walker collects imports
 */
class UseCollector extends Walker
{
  // session
  private $sess;
  
  // use-import-map
  private $uimap;
  
  // use-import map in nested imports
  private $uinst = null;
  
  // use-import nested stack
  private $nstst = [];
  
  /**
   * collector entry-point
   * 
   * @param  Session $sess
   * @param  Unit    $unit
   * @return UseImportMap
   */
  public function collect(Session $sess, Unit $unit)
  {
    parent::__construct([
      'enter' => [ 'unit', 'module', 'program' ],
      'visit' => [ 'use_decl' ]  
    ]);
    
    $this->sess = $sess;
    $this->uimap = new UseImportMap;
    
    $this->walk($unit);
    
    return $this->uimap;
  }
  
  /**
   * adds a use-import to the map
   * 
   * @param UseImport $uimp
   */
  protected function add_use_import(UseImport $uimp)
  {
    if ($this->uimap->add($uimp)) {
      Logger::debug_at($uimp->loc, 'import %s as `%s`', implode('::', $uimp->path), $uimp->item);
      
      // add it to the nested map too
      if ($this->uinst) $this->uinst->add($uimp);
      
      return true;
    }
    
    Logger::error_at($uimp->loc, 'duplicate import of a symbol named `%s`', $uimp->item);
    Logger::error_at($this->uimap->get($uimp->item)->loc, 'previous import was here');
    return false;
  }
  
  /* ------------------------------------ */
  
  /**
   * fetches the base import for a name
   * 
   * @param  Name $name
   * @return UseImport or null
   */
  protected function fetch_use_base(Name $name, UseImport $base = null)
  {
    $root = ident_to_str($name->base);
    
    // check nested imports first
    if ($this->uinst !== null) {
      if ($this->uinst->has($root))
        $base = $this->uinst->get($root);
    
    // check global imports
    } elseif ($this->uimap->has($root))
      $base = $this->uimap->get($root);
    
    return $base;
  }
  
  /* ------------------------------------ */
  
  /**
   * walker-method
   * 
   * @see Walker#visit_use_decl
   * @param  Node $node
   */
  protected function visit_use_decl($node)
  {
    $this->handle_import(null, $node->item);
  }
  
  /* ------------------------------------ */
  
  /**
   * handles a import
   * 
   * @param  UseImport $base (optional)
   * @param  Name|UseAlias|UseUnpack $item
   */
  protected function handle_import($base, $item)
  {
    if ($item instanceof Name) 
      $this->handle_use_name($base, $item);
    elseif ($item instanceof UseAlias)
      $this->handle_use_alias($base, $item);
    elseif ($item instanceof UseUnpack)
      $this->handle_use_unpack($base, $item);
    else
      assert(0);
  }
  
  /* ------------------------------------ */
  
  /**
   * handles a simple use-import `use foo::bar;`
   * 
   * @param  UseImport $base (optional)
   * @param  Name $item
   * @param  boolean $sealed do not lookup aliases
   */
  protected function handle_use_name($base, $item)
  {    
    $base = $this->fetch_use_base($item, $base);    
    $uimp = new UseImport($item, $base);
    
    $this->add_use_import($uimp);
  }
  
  /**
   * handles a simple use-import with alias `use foo::bar as baz;`
   * 
   * @param  UseImport $base (optional)
   * @param  UseAlias $item
   */
  protected function handle_use_alias($base, $item)
  {    
    $base = $this->fetch_use_base($item->name, $base);
    $uimp = new UseImport($item->name, $base, $item->alias);
    
    $this->add_use_import($uimp);
  }
  
  /**
   * handles complex use-imports 
   * 
   * @param  UseImport $base (optional)
   * @param  UseAlias $item
   */
  protected function handle_use_unpack($base, $item)
  {
    if ($item->base !== null) {
      $base = $this->fetch_use_base($item->base, $base);
      $base = new UseImport($item->base, $base);
      
      // TODO: this is a workaround...
      // 
      // push $base->orig again because UseImport()
      // will pop it off assuming that the base-path was from a actual 
      // imported symbol.
      // 
      // it would be better to segment UseImport using a 'UsePath',
      // but it works for now ...
      $base->path[] = $base->orig;
    }
    
    // push nested imports onto the stack and create a new map
    array_push($this->nstst, $this->uinst);
    $this->uinst = new UseImportMap;
    
    foreach ($item->items as $nimp)
      $this->handle_import($base, $nimp);
    
    // pop previous nested imports of the stack
    $this->uinst = array_pop($this->nstst);
  }
}

/* ------------------------------------ */

class ExportCollector extends Walker
{
  public function __construct()
  {
    parent::__construct([
      'skip'  => [ 'block' ],
      'visit' => [ 'let_decl', 'var_decl', 'enum_decl' ]
    ]);    
  }
  
  
}
