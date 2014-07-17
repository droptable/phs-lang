<?php

namespace phs\front;

require_once 'utils.php';
require_once 'walker.php';
require_once 'visitor.php';
require_once 'symbols.php';
require_once 'scope.php';

use phs\Logger;
use phs\Session;

use phs\util\Set;
use phs\util\Map;
use phs\util\Cell;
use phs\util\Entry;

use phs\front\ast\Node;
use phs\front\ast\Unit;

use phs\front\ast\Name;
use phs\front\ast\Ident;
use phs\front\ast\UseAlias;
use phs\front\ast\UseUnpack;

const DS = DIRECTORY_SEPARATOR;

/** usage symbol */
class Usage implements Entry
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
   * @param Name $name
   * @param Usage $base a other imported symbol for a relative import
   * @param Ident $item a user-defined name (alias)
   */
  public function __construct(Name $name, Usage $base = null, Ident $item = null)
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
  
  /**
   * returns the entry-key
   * 
   * @return string
   */
  public function key()
  {
    return $this->item;
  }
}

/** usage map */
class UsageMap extends Map
{
  /**
   * constructor
   */
  public function __construct()
  {
    parent::__construct();
  }
  
  /**
   * typecheck
   * 
   * @param  Entry  $ent 
   * @return boolean
   */
  protected function check(Entry $ent)
  {
    return $ent instanceof Usage;
  }
}

/** usage collector */
class UsageCollector extends Visitor
{
  // @var UsageMap  collected improts
  private $uimap;
  
  // @var UsageMap  nested imports
  private $uinst;
  
  // @var Walker
  private $walker;
  
  /**
   * collector entry-point
   *
   * @param array|Node  $some
   * @return UsageMap
   */
  public function collect($some)
  {
    $this->uimap = new UsageMap;
    $this->walker = new Walker($this);
    $this->walker->walk_some($some);
    return $this->uimap;
  }

  // ---------------------------------------
  // visitor boilerplate
  
  /**
   * walker-method
   *
   * @param Node $node
   */
  public function visit_unit($node) 
  { 
    $this->walker->walk_some($node->body); 
  }
  
  /**
   * walker-method
   *
   * @param Node $node
   */
  public function visit_module($node) 
  {
    $this->walker->walk_some($node->body); 
  }
  
  /**
   * walker-method
   *
   * @param Node $node
   */
  public function visit_content($node) 
  { 
    if ($node->uses)
      foreach ($node->uses as $usage)
        $this->handle_import(null, $usage);
      
    // handle sub-modules too?
    // $this->walker->walk_some($node->body);
  }
  
  // ---------------------------------------

  /**
   * adds a use-import to the map
   *
   * @param Import $uimp
   */
  protected function add_use_import(Usage $uimp)
  {    
    $key = $uimp->key();
    
    if ($this->uimap->add($uimp)) {
      Logger::debug_at($uimp->loc, 'import %s as `%s`', 
        implode('::', $uimp->path), $key);
      
      // add it to the nested map too
      if ($this->uinst) $this->uinst->add($uimp);
      
      return true;
    }
    
    Logger::error_at($uimp->loc, 'duplicate import of a symbol named `%s`', $key);
    Logger::error_at($this->uimap->get($key)->loc, 'previous import was here');
    
    return false;
  }

  /* ------------------------------------ */

  /**
   * fetches the base import for a name
   *
   * @param Name $name
   * @param Usage $base  fallback
   * @return Usage or null
   */
  protected function fetch_use_base(Name $name, Usage $base = null)
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
   * handles a import
   *
   * @param UseImport $base (optional)
   * @param Name|UseAlias|UseUnpack $item
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
   * @param UseImport $base (optional)
   * @param Name $item
   * @param boolean $sealed do not lookup aliases
   */
  protected function handle_use_name($base, $item)
  {
    $base = $this->fetch_use_base($item, $base);
    $uimp = new Usage($item, $base);
    
    $this->add_use_import($uimp);
  }

  /**
   * handles a simple use-import with alias `use foo::bar as baz;`
   *
   * @param UseImport $base (optional)
   * @param UseAlias $item
   */
  protected function handle_use_alias($base, $item)
  {
    $base = $this->fetch_use_base($item->name, $base);
    $uimp = new Usage($item->name, $base, $item->alias);
    
    $this->add_use_import($uimp);
  }

  /**
   * handles complex use-imports
   *
   * @param UseImport $base (optional)
   * @param UseAlias $item
   */
  protected function handle_use_unpack($base, $item)
  {
    if ($item->base !== null) {
      $base = $this->fetch_use_base($item->base, $base);
      $base = new Usage($item->base, $base);
      
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
    $this->uinst = new UsageMap;
    
    foreach ($item->items as $nimp)
      $this->handle_import($base, $nimp);
    
    // pop previous nested imports of the stack
    $this->uinst = array_pop($this->nstst);
  }
}

/** type collector */
class TypeCollector extends Visitor
{  
  // @var Scope 
  private $scope;
  
  // @var Walker
  private $walker;
  
  /**
   * constructor
   */
  public function __construct()
  {
    // init visitor
    parent::__construct();
  }
  
  /**
   * collect type-symbols from a node/node-list
   * 
   * @param  Node|array $some
   * @param  Scope $scope
   */
  public function collect($some, Scope $scope)
  {
    $this->scope = $scope;
    $this->walker = new Walker($this);
    $this->walker->walk_some($some);
  }
  
  /* ------------------------------------ */
  
  public function visit_class_decl($node)
  {
    $sym = ClassSymbol::from($node);
    $this->scope->add($sym);
  }
  
  public function visit_trait_decl($node)
  {
    $sym = TraitSymbol::from($node);
    $this->scope->add($sym);
  }
  
  public function visit_iface_decl($node)
  {
    $sym = IfaceSymbol::from($node);
    $this->scope->add($sym);
  }
}

/** member collector */
class MemberCollector extends Visitor
{
  // @var MemberScope  members
  private $scope;
  
  // @var Walker
  private $walker;
  
  /**
   * constructor
   */
  public function __construct()
  {
    // init visitor
    parent::__construct();
  }
  
  /**
   * collector entr-point
   *   
   * @param  array $some
   * @param  MemberScope  $scope
   */
  public function collect($some, MemberScope $scope)
  {
    $this->scope = $scope;
    $this->walker = new Walker($this);
    $this->walker->walk_some($some);
  }
  
  /* ------------------------------------ */
  
  public function visit_fn_decl($node)
  {
    $sym = MethodMember::from($node);
    $this->scope->add($sym);
  }
  
  public function visit_ctor_decl($node)
  {
    $sym = CtorMember::from($node);
    $this->scope->add($sym);
  }
  
  public function visit_dtor_decl($node)
  {
    $sym = DtorMember::from($node);
    $this->scope->add($sym);
  }
  
  public function visit_var_decl($node)
  {
    $flags = mods_to_flags($node->mods);
    
    foreach ($node->vars as $var) {
      $sym = FieldMember::from($node, $flags);
      $this->scope->add($sym);
    }
  }
  
  public function visit_enum_decl($node)
  {
    $flags = mods_to_flags($node->mods, SYM_FLAG_CONST);
    
    foreach ($node->vars as $var) {
      $sym = FieldMember::from($node, $flags);
      $this->scope->add($sm);
    }
  }
  
  public function visit_getter_decl($node)
  {
    $sym = GetterMember::from($node);
    $this->scope->add($sym);
  }
  
  public function visit_setter_decl($node)
  {
    $sym = SetterMember::from($node);
    $this->scope->add($sym);
  }
}
