<?php

namespace phs\front;

require_once 'utils.php';
require_once 'visitor.php';
require_once 'symbols.php';
require_once 'scope.php';

use phs\Logger;
use phs\Origin;
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
  public function collect(UnitScope $scope, Unit $unit)
  {
    $this->scope = $scope;
    $this->sroot = $this->scope;
    
    $this->visit($unit);
    
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
    $this->visit($node->body);
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
          $nmod = new ModuleScope($mid, null, $nmod);
          $mmap->add($nmod);
        }
        
        $mmap = $nmod->mmap;
      }
      
      $this->scope = $nmod;
    } else 
      // switch to global scope
      $this->scope = $this->sroot;
    
    // save scope-information on the ast-node.
    // TODO: remove scope-ref from ast!
    $node->scope = $this->scope;
    
    // walk module-body
    $this->visit($node->body);
    $this->scope = $prev;
  }
  
  /**
   * Visitor#visit_content()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_content($node) 
  {
    // continue walking
    $this->visit($node->body);
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
    
    $this->visit($decl->members);
    
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
  
  /**
   * Visitor#visit_alias_decl()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_alias_decl($node)
  {
    $sym = AliasSymbol::from($node);
    $this->scope->add($sym);
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
