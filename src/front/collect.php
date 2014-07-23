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
  
  // the kind of this import (gets resolved later)
  // 'phm' => must be a module
  // 'phs' => can be anything
  public $kind;

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
class UsageCollector
{
  // @var UsageMap  collected improts
  private $umap;
  
  // @var UsageMap  nested imports
  private $unst;
  
  // @var array  nested import stack
  private $nstk = [];
  
  /**
   * collector entry-point
   *
   * @param array $uses
   * @return UsageMap
   */
  public function collect(RootScope $scope, array $uses)
  {
    $this->umap = $scope->umap;
    
    foreach ($uses as $use)
      $this->handle_import(null, $use);
    
    return $this->umap;
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
    
    if ($this->umap->add($uimp)) {
      Logger::debug_at($uimp->loc, 'import %s as `%s`', 
        implode('::', $uimp->path), $key);
      
      // add it to the nested map too
      if ($this->unst) $this->unst->add($uimp);
      
      return true;
    }
    
    Logger::error_at($uimp->loc, 'duplicate import of a symbol named `%s`', $key);
    Logger::error_at($this->umap->get($key)->loc, 'previous import was here');
    
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
    if ($this->unst !== null) {
      if ($this->unst->has($root))
        $base = $this->unst->get($root);
    
    // check global imports
    } elseif ($this->umap->has($root))
      $base = $this->umap->get($root);
    
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
    array_push($this->nstk, $this->unst);
    $this->unst = new UsageMap;
    
    foreach ($item->items as $nimp)
      $this->handle_import($base, $nimp);
    
    // pop previous nested imports of the stack
    $this->unst = array_pop($this->nstk);
  }
}

/** unit collector */
class UnitCollector extends Visitor
{  
  // @var Session
  private $sess;
  
  // @var Scope  scope
  private $scope;
  
  // @var Scope  root scope
  private $sroot;
  
  // @var Walker  walker
  private $walker;
  
  /**
   * constructor
   * 
   * @param Session  $sess
   */
  public function __construct(Session $sess)
  {
    // init visitor
    parent::__construct();
    $this->sess = $sess;
  }
  
  /**
   * collect type-symbols from a node/node-list
   * 
   * @param  Node|array $some
   * @param  Scope $scope
   */
  public function collect(Unit $unit)
  {
    $this->scope = new UnitScope($unit);
    $this->sroot = $this->scope;
    
    $this->walker = new Walker($this);
    $this->walker->walk_some($unit);
    
    return $this->scope;
  }
  
  /* ------------------------------------ */
  
  /**
   * Visitor#visit_unit()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_unit($node)
  {
    $this->walker->walk_some($node->body);
  }
  
  /**
   * Visitor#visit_module()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_module($node)
  {
    $prev = $this->scope;
    
    if ($node->name) {
      $base = $this->scope;
      $name = name_to_arr($node->name);
      
      if ($node->name->root)
        $base = $this->sroot; // use unit-scope
      
      $mmap = null;
      $nmod = $this->sroot;
      
      // must be inside a unit or a module
      assert($base instanceof RootScope);
      $mmap = $base->mmap;
      
      foreach ($name as $mid) {
        if ($mmap->has($mid))
          // fetch sub-module
          $nmod = $mmap->get($mid);
        else {
          // create and assign a new module
          $nmod = new ModuleScope($mid, $nmod);
          $mmap->add($nmod);
        }
        
        $mmap = $nmod->mmap;
      }
      
      $this->scope = $nmod;
    } else 
      // switch to global scope
      $this->scope = $this->sroot;
    
    // walk module-body
    $this->walker->walk_some($node->body);
    $this->scope = $prev;
  }
  
  /**
   * Visitor#visit_content()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_content($node) {
    // collect usage if any
    if ($node->uses) {
      $usc = new UsageCollector;
      $usc->collect($this->scope, $node->uses);
    }
    
    // continue walking
    $this->walker->walk_some($node->body);
  }
  
  /**
   * Visitor#visit_fn_decl()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_fn_decl($node)
  {
    $sym = FnSymbol::from($node);
    $this->scope->add($sym);
  }
  
  /**
   * Visitor#visit_enum_decl()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_enum_decl($node)
  {
    $flags = mods_to_sym_flags($node->mods, SYM_FLAG_CONST);
    
    foreach ($node->vars as $var) {
      $sym = VarSymbol::from($var, $flags);
      $this->scope->add($sym);
    }
  }
  
  /**
   * Visitor#visit_class_decl()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_class_decl($node)
  {
    $sym = ClassSymbol::from($node);    
    $this->scope->add($sym);
    
    $col = new ClassCollector($this->sess);
    $col->collect($this->scope, $sym, $node);
  }
  
  /**
   * Visitor#visit_iface_decl()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_iface_decl($node)
  {
    $sym = IfaceSymbol::from($node);    
    $this->scope->add($sym); 
    
    $col = new IfaceCollector($this->sess);
    $col->collect($this->scope, $sym, $node);
  }
  
  /**
   * Visitor#visit_trait_decl()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_trait_decl($node)
  {
    $sym = TraitSymbol::from($node);    
    $this->scope->add($sym); 
    
    $col = new TraitCollector($this->sess);
    $col->collect($this->scope, $sym, $node);
  }
}

/** member collector */
abstract class MemberCollector extends Visitor
{
  // @var Session
  protected $sess;
  
  // @var Scope
  protected $scope;
  
  // @var Walker
  protected $walker;
  
  /**
   * constructor
   */
  public function __construct(Session $sess)
  {
    // init visitor
    parent::__construct();
    $this->sess = $sess;
  }
  
  /**
   * collect members
   *
   * @param  Scope                         $prev
   * @param  ClassDecl|IfaceDecl|TraitDecl $decl
   * @return MemberScope
   */
  protected function collect_members(Scope $prev, $decl)
  {
    $this->scope = new MemberScope($prev);
    
    $this->walker = new Walker($this);
    $this->walker->walk_some($decl->members);
    
    return $this->scope;
  }
  
  /**
   * Visitor#visit_fn_decl()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_fn_decl($node)
  {
    $sym = FnSymbol::from($node);
    $this->scope->add($sym); 
  }
  
  /**
   * Visitor#visit_var_decl()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_var_decl($node)
  {
    $flags = mods_to_sym_flags($node->mods);
    
    foreach ($node->vars as $var) {
      $sym = VarSymbol::from($var, $flags);
      $this->scope->add($sym);
    }
  }
  
  /**
   * Visitor#visit_enum_decl()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_enum_decl($node)
  {
    $flags = mods_to_sym_flags($node->mods, SYM_FLAG_CONST);
    
    foreach ($node->vars as $var) {
      $sym = VarSymbol::from($var, $flags);
      $this->scope->add($sym);
    }
  }
}

/** class collector */
class ClassCollector extends MemberCollector
{
  // @var ClassSymbol
  protected $sym;
  
  /**
   * constructor
   *
   * @param Session $sess
   */
  public function __construct(Session $sess)
  {
    parent::__construct($sess);
  }
  
  /**
   * collector 
   *
   * @param  ClassSymbol $sym 
   * @param  Node      $decl
   * @return void
   */
  public function collect(Scope $prev, ClassSymbol &$sym, $decl)
  {
    // for ctor/dtor visitors
    $this->sym = $sym;
    
    $sym->super = $this->collect_super($decl);
    $sym->traits = $this->collect_traits($decl);
    $sym->ifaces = $this->collect_ifaces($decl);
    $sym->members = $this->collect_members($prev, $decl);
  }
  
  /**
   * collect a class-super (extend) declaration
   *
   * @param  ClassDecl $decl
   * @return SymbolRef
   */
  protected function collect_super($decl)
  {
    if (!$decl->ext) return null;
    return new SymbolRef($decl->ext, SYM_KIND_CLASS);
  }
  
  /**
   * collect trait-usage
   *
   * @param  ClassDecl $decl
   * @return TraitUsageSet
   */
  protected function collect_traits($decl)
  {
    if (!$decl->traits) return null;
    
    $tset = new TraitUsageSet;
    
    foreach ($decl->traits as $trait) {
      $tuse = new SymbolRef($trait->name, SYM_KIND_TRAIT);
      
      if ($trait->items === null)
        // use all members
        $tset->add(new TraitUsage($tuse, $trait->loc, null, null));
      else      
        foreach ($trait->items as $item) {
          $orig = ident_to_str($item->id);
          $dest = null;
          
          if ($item->alias)
            $dest = ident_to_str($item->alias);
          
          $flags = mods_to_sym_flags($item->mods);
          $tset->add(new TraitUsage($tuse, $item->loc, $orig, $dest, $flags));
        }
    }
    
    return $tset;
  }
  
  /**
   * collect implemented interfaces
   *
   * @param  ClassDecl $decl
   * @return SymbolRefSet
   */
  protected function collect_ifaces($decl)
  {
    if (!$decl->impl) return null;
    
    $iset = new SymbolRefSet;
    
    foreach ($decl->impl as $impl)
      $iset->add(new SymbolRef($impl, SYM_KIND_IFACE));
      
    return $iset;    
  }
  
  /* ------------------------------------ */
  
  /**
   * Visitor#visit_ctor_decl()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_ctor_decl($node)
  {
    if ($this->sym->ctor !== null)
      Logger::error_at($node->loc, 'duplicate constructor declaration');
    
    $this->sym->ctor = FnSymbol::from($node);
  }
  
  /**
   * Visitor#visit_dtor_decl()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_dtor_decl($node)
  {
    if ($this->sym->dtor !== null)
      Logger::error_at($node->loc, 'duplicate destructor declaration');
    
    $this->sym->dtor = FnSymbol::from($node);
  }
}

/** interface collector */
class IfaceCollector extends MemberCollector
{
  /**
   * constructor
   *
   * @param Session $sess
   */
  public function __construct(Session $sess)
  {
    parent::__construct($sess);
  }
  
  /**
   * collector
   *
   * @param  Scope       $prev
   * @param  IfaceSymbol $sym
   * @param  Node      $decl
   * @return void
   */
  public function collect(Scope $prev, IfaceSymbol $sym, $decl)
  {
    $sym->ifaces = $this->collect_ifaces($decl);
    $sym->members = $this->collect_members($prev, $decl);
  }
  
  /**
   * collect implemented interfaces
   *
   * @param  ClassDecl $decl
   * @return SymbolRefSet
   */
  protected function collect_ifaces($decl)
  {
    if (!$decl->exts) return null;
    
    $iset = new SymbolRefSet;
    
    foreach ($decl->exts as $impl)
      $iset->add(new SymbolRef($impl, SYM_KIND_IFACE));
      
    return $iset;    
  }
  
  /* ------------------------------------ */
  
  /**
   * Visitor#visit_var_decl()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_var_decl($node)
  {
    Logger::error_at($node->loc, 'variables are not allowed in interfaces');
  }
  
  /**
   * Visitor#visit_enum_decl()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_enum_decl($node)
  {
    Logger::error_at($node->loc, 'enumerations are not allowed in interfaces');
  }
  
  /**
   * Visitor#visit_ctor_decl()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_ctor_decl($node)
  {
    Logger::error_at($node->loc, 'constructors are not allowed in interfaces');
  }
  
  /**
   * Visitor#visit_dtor_decl()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_dtor_decl($node)
  {
    Logger::error_at($node->loc, 'destructors are not allowed in interfaces');
  }
}

/** trait collector */
class TraitCollector extends ClassCollector
{
  /**
   * constructor
   *
   * @param Session $sess
   */
  public function __construct(Session $sess)
  {
    parent::__construct($sess);
  }
  
  /**
   * collector 
   *
   * @param  ClassSymbol $sym 
   * @param  Node      $decl
   * @return void
   */
  public function collect(Scope $prev, TraitSymbol &$sym, $decl)
  {
    $sym->traits = $this->collect_traits($decl);
    $sym->members = $this->collect_members($prev, $decl);
  }
  
  /* ------------------------------------ */
  
  /**
   * Visitor#visit_ctor_decl()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_ctor_decl($node)
  {
    Logger::error_at($node->loc, 'constructors are not allowed in traits');
  }
  
  /**
   * Visitor#visit_dtor_decl()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_dtor_decl($node)
  {
    Logger::error_at($node->loc, 'destructors are not allowed in traits');
  }
}
