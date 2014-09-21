<?php

// referenzen zu funktionen, klassen (usw) müssen innerhalb der 
// unit aufgelöst werden (eine extern-deklaration ist zulässig).
// 
// referenzen zu variablen sind nicht gestattet.

namespace phs\front;

require_once 'utils.php';
require_once 'visitor.php';
require_once 'symbols.php';
require_once 'scope.php';
require_once 'branch.php';
require_once 'usage.php';

use phs\Logger;
use phs\Session;

use phs\util\Set;
use phs\util\Map;
use phs\util\Cell;
use phs\util\Dict;
use phs\util\Entry;

use phs\front\ast\Node;
use phs\front\ast\Unit;

use phs\front\ast\FnExpr;

use phs\front\ast\Name;
use phs\front\ast\Ident;
use phs\front\ast\UseAlias;
use phs\front\ast\UseUnpack;

const DS = DIRECTORY_SEPARATOR;

/** unit collector */
class UnitCollector extends AutoVisitor
{  
  // @var Session
  private $sess;
  
  // @var UsageMap
  private $umap;
  
  // @var UsageMap nested uses
  private $unst;
  
  // @var array  nested use-stack
  private $nstk;
  
  // @var Scope  scope
  private $scope;
  
  // @var Scope  root scope
  private $sroot;
  
  private static $pass = 1;
  
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
   * @param  Unit $unit
   */
  public function collect(Unit $unit)
  {
    $unit->scope = new UnitScope($this->sess, $unit);
    $this->scope = $unit->scope;
    $this->sroot = $this->scope;
    
    $this->umap = $unit->scope->umap;    
    $this->unst = null;
    $this->nstk = [];
    
    $this->visit($unit);
  }
  
  /* ------------------------------------ */
    
  /**
   * collect a class-super (extend) declaration
   *
   * @param  ClassDecl $decl
   * @return SymbolRef
   */
  private function collect_super($decl)
  {
    if (!$decl->ext) return null;
    return $decl->ext;
  }
  
  /**
   * collect trait-usage
   *
   * @param  ClassDecl $decl
   * @return array
   */
  private function collect_traits($decl)
  {
    if (!$decl->traits) return null;
    
    $tset = [];
    
    foreach ($decl->traits as $trait) {
      $tuse = $trait->name;
      
      if ($trait->items === null)
        // use all members
        $tset[] = new TraitUsage($tuse, $trait->loc, null, null);
      else      
        foreach ($trait->items as $item) {
          $orig = ident_to_str($item->id);
          $dest = $orig;
          
          if ($item->alias)
            $dest = ident_to_str($item->alias);
          
          $flags = mods_to_sym_flags($item->mods);
          $tset[] = new TraitUsage($tuse, $item->loc, $orig, $dest, $flags);
        }
    }
    
    return $tset;
  }
  
  /**
   * collect implemented interfaces
   *
   * @param  ClassDecl $decl
   * @return array
   */
  private function collect_ifaces($decl)
  {
    if (!$decl->impl) return null;
    return $decl->impl;   
  }
  
  /**
   * collects members
   *
   * @param  ClassDecl $sym
   * @return MemberScope
   */
  private function collect_members($decl)
  {
    $prev = $this->scope;
    $mscp = new MemberScope($prev);
    
    if ($decl->members) {
      $this->scope = $mscp;
      $this->visit($decl->members);
      $this->scope = $prev;
    }
    
    return $mscp;
  }
  
  /**
   * collects a use-declaration
   *
   * @param  Usage $base
   * @param  Name|UseAlias|UseUnpack $item
   * @param  bool  $pub
   */
  private function collect_use($base, $item, $pub)
  {
    if ($item instanceof Name)
      $this->collect_use_name($base, $item, $pub);
    elseif ($item instanceof UseAlias)
      $this->collect_use_alias($base, $item, $pub);
    elseif ($item instanceof UseUnpack)
      $this->collect_use_unpack($base, $item, $pub);
    else
      assert(0);
  }
  
  /**
   * handles a simple use-import `use foo::bar;`
   *
   * @param UseImport $base (optional)
   * @param Name $item
   * @param bool  $pub
   */
  private function collect_use_name($base, $item, $pub)
  {
    $base = $this->fetch_use_base($item, $base);
    $uimp = new Usage($pub, $item, $base);
    
    $this->add_use_import($uimp);
  }
  
  /**
   * handles a simple use-import with alias `use foo::bar as baz;`
   *
   * @param UseImport $base (optional)
   * @param UseAlias $item
   */
  private function collect_use_alias($base, $item, $pub)
  {
    $base = $this->fetch_use_base($item->name, $base);
    $uimp = new Usage($pub, $item->name, $base, $item->alias);
    
    $this->add_use_import($uimp);
  }
  
  /**
   * handles complex use-imports
   *
   * @param UseImport $base (optional)
   * @param UseAlias $item
   */
  private function collect_use_unpack($base, $item, $pub)
  {
    if ($item->base !== null) {
      $base = $this->fetch_use_base($item->base, $base);
      $base = new Usage($pub, $item->base, $base);
      $base->path[] = $base->orig;
    }
    
    // push nested imports onto the stack and create a new map
    array_push($this->nstk, $this->unst);
    $this->unst = new UsageMap;
    
    foreach ($item->items as $nimp)
      $this->collect_use($base, $nimp, $pub);
    
    // pop previous nested imports of the stack
    $this->unst = array_pop($this->nstk);
  }
  
  /**
   * fetches the base import for a name
   *
   * @param Name $name
   * @param Usage $base  fallback
   * @return Usage or null
   */
  private function fetch_use_base(Name $name, Usage $base = null)
  {    
    // TODO: this code enables realtive imports.
    
    /*
    $root = ident_to_str($name->base);
    
    // check nested imports first
    if ($this->unst !== null) {
      if ($this->unst->has($root))
        $base = $this->unst->get($root);
    
    // check global imports
    } elseif ($this->umap->has($root))
      $base = $this->umap->get($root);
    */
   
    return $base;
  }
  
  /**
   * adds a use-import to the map
   *
   * @param Usage $uimp
   */
  protected function add_use_import(Usage $uimp)
  {    
    $key = $uimp->key();
    
    if ($this->umap->add($uimp)) {
      $path = arr_to_path($uimp->path);
      
      // add it to the nested map too
      if ($this->unst) $this->unst->add($uimp);
      
      Logger::debug_at($uimp->loc, 'adding import %s (%s) to %s',
        $uimp->item, path_to_str($uimp->path), $this->scope);
      return true;
    }
    
    Logger::error_at($uimp->loc, 'duplicate import of a symbol named `%s`', $key);
    Logger::error_at($this->umap->get($key)->loc, 'previous import was here');
    
    return false;
  }
  
  /**
   * enters a function-ish node
   *
   * @param  FnDecl|FnExpr|GetterDecl|SetterDecl|CtorDecl|DtorDecl $node
   */
  protected function enter($node)
  {
    $prev = $this->scope;
    $node->scope = new Scope($prev);
    $this->scope = $node->scope;
    
    if ($node instanceof FnExpr && $node->id) {
      $sym = $node->symbol = FnSymbol::from($node);
      $this->scope->add($sym);
    }
    
    if ($node->params)
      foreach ($node->params as $param) {
        $sym = $param->symbol = ParamSymbol::from($param);
        $this->scope->add($sym);
      }
    
    if ($node->body)
      $this->visit($node->body);
    
    $this->scope = $prev;
  }
  
  /* ------------------------------------ */
  
  /**
   * Visitor#visit_module()
   *
   * @param Node $node
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
    
    $node->scope = $this->scope;
    $this->scope = $node->scope;
    
    // use module for uses
    $this->umap = $this->scope->umap;
    
    // walk module body
    $this->scope->enter();
    $this->visit($node->body);
    $this->scope->leave();
    
    $this->scope = $prev;
    
    // use prev scope (again) for uses
    $this->umap = $this->scope->umap;
  }
  
  public function visit_block($node)
  {
    $prev = $this->scope;
    $node->scope = new Scope($prev);
    $this->scope = $node->scope;
    
    $this->scope->enter();
    $this->visit($node->body);
    $this->scope->leave();
    
    $this->scope = $prev;
  }
  
  /**
   * Visitor#visit_class_decl()
   *
   * @param Node $node
   */
  public function visit_class_decl($node)
  {
    $sym = $node->symbol = ClassSymbol::from($node);
    $sym->super = $this->collect_super($node);
    $sym->traits = $this->collect_traits($node);
    $sym->ifaces = $this->collect_ifaces($node);
    $sym->members = $this->collect_members($node);
    
    if ($node->incomp)
      $sym->flags |= SYM_FLAG_INCOMPLETE;
    
    $this->scope->add($sym);
  }
  
  /**
   * Visitor#visit_trait_decl()
   *
   * @param Node $node
   */
  public function visit_trait_decl($node)
  {
    $sym = $node->symbol = TraitSymbol::from($node);    
    $sym->traits = $this->collect_traits($node);
    $sym->members = $this->collect_members($node);
    
    if ($node->incomp)
      $sym->flags |= SYM_FLAG_INCOMPLETE;
    
    $this->scope->add($sym);
  }
  
  /**
   * Visitor#visit_iface_decl()
   *
   * @param Node $node
   */
  public function visit_iface_decl($node)
  {
    $sym = $node->symbol = IfaceSymbol::from($node);
    $sym->ifaces = $this->collect_ifaces($node);
    $sym->members = $this->collect_members($node);
    
    if ($node->incomp)
      $sym->flags |= SYM_FLAG_INCOMPLETE;
    
    $this->scope->add($sym); 
  }
  
  /**
   * Visitor#visit_fn_decl()
   *
   * @param Node $node
   */
  public function visit_fn_decl($node)
  {
    $sym = $node->symbol = FnSymbol::from($node);
    $this->scope->add($sym);
        
    $this->enter($node);
  }
  
  /**
   * Visitor#visit_use_decl()
   *
   * @param  Node $node
   */
  public function visit_use_decl($node) 
  {
    $this->collect_use(null, $node->item, $node->pub);
  }
  
  /**
   * Visitor#visit_var_decl()
   *
   * @param  Node $node
   */
  public function visit_var_decl($node)
  {
    $flags = mods_to_sym_flags($node->mods);
    
    foreach ($node->vars as $var) {
      $sym = $var->symbol = VarSymbol::from($var, $flags);
      
      if ($var->init)
        $this->visit($var->init);
      
      $this->scope->add($sym);
    }
  }
  
  /**
   * Visitor#visit_enum_decl()
   *
   * @param  Node $node
   */
  public function visit_enum_decl($node)
  {
    $flags = mods_to_sym_flags($node->mods, SYM_FLAG_CONST);
    
    foreach ($node->vars as $var) {
      $sym = $var->symbol = VarSymbol::from($var, $flags);
      
      if ($var->init)
        $this->visit($var->init);
      
      $this->scope->add($sym);
    }
  }
  
  /**
   * Visitor#visit_ctor_decl()
   *
   * @param  Node $node
   */
  public function visit_ctor_decl($node)
  {
    assert($this->scope instanceof MemberScope);
    
    if ($this->scope->ctor !== null)
      Logger::warn_at($node->loc, 'duplicate constructor');
    
    $this->scope->ctor = FnSymbol::from($node);
    $this->enter($node);
  }
  
  /**
   * Visitor#visit_dtor_decl()
   *
   * @param  Node $node
   */
  public function visit_dtor_decl($node)
  {
    assert($this->scope instanceof MemberScope);
    
    if ($this->scope->dtor !== null)
      Logger::warn_at($node->loc, 'duplicate destructor');
    
    $this->scope->dtor = FnSymbol::from($node);
    $this->enter($node);
  }
  
  /**
   * Visitor#visit_getter_decl()
   *
   * @param  Node $node
   */
  public function visit_getter_decl($node)
  {
    assert($this->scope instanceof MemberScope);
    
    $sym = $node->symbol = FnSymbol::from($node);
    $this->scope->getter->add($sym);
    $this->enter($node);
  }
  
  /**
   * Visitor#visit_setter_decl()
   *
   * @param  Node $node
   */
  public function visit_setter_decl($node)
  {
    assert($this->scope instanceof MemberScope);
    
    $sym = $node->symbol = FnSymbol::from($node);
    $this->scope->setter->add($sym);
    $this->enter($node);
  }
  
  /**
   * Visitor#visit_fn_expr()
   *
   * @param  Node $node
   */
  public function visit_fn_expr($node)
  {
    $this->enter($node);
  }
  
  /**
   * Visitor#visit_for_in_stmt()
   *
   * @param  Node $node
   */
  public function visit_for_in_stmt($node)
  {
    $prev = $this->scope;
    $node->scope = new Scope($prev);
    $this->scope = $node->scope;
    
    $vars = [];
    
    if ($node->lhs->key)
      $vars[] = $node->lhs->key;
    
    if ($node->lhs->arg)
      $vars[] = $node->lhs->arg;
      
    foreach ($vars as $var)   
      $this->scope->add($var->symbol = new VarSymbol(
        ident_to_str($var), 
        $var->loc, 
        SYM_FLAGS_NONE
      ));
        
    $this->visit($node->rhs);
    $this->visit($node->stmt);
    
    $this->scope = $prev;
  }
  
  /**
   * Visitor#visit_for_stmt()
   *
   * @param  Node $node
   */
  public function visit_for_stmt($node)
  {
    $prev = $this->scope;
    $node->scope = new Scope($prev);
    $this->scope = $node->scope;
    
    $this->visit($node->init);
    $this->visit($node->test);
    $this->visit($node->each);
    $this->visit($node->stmt);
    
    $this->scope = $prev;
  }
}
