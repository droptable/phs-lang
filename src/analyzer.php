<?php

namespace phs;

require_once 'utils.php';
require_once 'value.php';
require_once 'walker.php';
require_once 'symbol.php';
require_once 'scope.php';
require_once 'branch.php';

require_once 'builtin/map.php';
require_once 'builtin/list.php';

use phs\_Builtin_Map; // used for constant { ... } expressions
use phs\_Builtin_List; // used for constant [ ... ] expressions

use phs\ast\Unit;
use phs\ast\Name;

class Analyzer extends Walker 
{
  // context
  private $ctx;
  
  // the compiler
  private $com;
  
  // current scope
  private $scope;
  
  // scope stack
  private $sstack;
  
  // flags from nested-mods
  private $flags;
  
  // flag stack
  private $fstack;
  
  // reducer used to handle constant-expressions
  private $rdc;
  
  // validation state
  private $valid;
  
  // a value carried around to reduce constant-expressions
  private $value;
  
  // branch-id
  private $branch;
  
  // branch stack
  private $bstack;
  
  // class analyzing:
  // pass 1: vars without initializers
  // pass 2: methods without body
  // pass 3: vars with initializers
  // pass 4: ctor/dtor
  // pass 5: methods with body
  private $pass;
  
  // pass stack
  private $pstack;
  
  // labels
  private $labels;
  
  // label stack
  private $lstack;
  
  // label frame (used to track unreachable labels)
  private $lframe;
  
  // gotos
  private $gotos;
  
  // goto stack
  private $gstack;
  
  // for validation of return/break/continue
  private $infn = 0;
  private $inloop = 0;
  
  // access flags
  const
    ACC_READ = 1,
    ACC_WRITE = 2
  ;
  
  // access flag for assignments
  private $access;
  
  // access location
  private $accloc;
  
  // access stack
  private $astack;
  
  // there a some positions in the code 
  // where constant-assigments are not allowed
  private $allow_const_assign;
  
  // anonymus function id-counter
  private static $anon_uid = 0;
  
  // branch counter
  private static $branch_uid = 0;
  
  // require'd paths
  private static $require_paths = [];
  
  /**
   * constructor
   * 
   * @param Context $ctx
   */
  public function __construct(Context $ctx, Compiler $com)
  {
    parent::__construct($ctx);
    $this->ctx = $ctx;
    $this->com = $com;
  }
  
  /**
   * analyze a unit
   * 
   * @param  Unit   $unit
   */
  public function analyze(Unit $unit)
  {
    $this->scope = $this->ctx->get_root();
    $this->sstack = [];
    $this->flags = SYM_FLAG_NONE;
    $this->fstack = [];
    $this->valid = true;
    $this->pass = 0;
    $this->pstack = [];
    $this->access = self::ACC_READ;
    $this->accloc = $unit->loc;
    $this->astack = [];
    $this->branch = 0;
    $this->bstack = [];
    $this->labels = [];
    $this->lstack = [];
    $this->lframe = [];
    $this->gotos = [];
    $this->gstack = [];
    
    $this->allow_const_assign = true;
    
    $this->walk($unit);
    
    $this->check_jumps();
  }
  
  /* ------------------------------------ */
  
  /**
   * handles a import (use)
   * 
   * @param  Module $base
   * @param  Node $item
   */
  protected function handle_import(Module $base, $item)
  {    
    switch ($item->kind()) {
      case 'name':
        $this->fetch_import($base, $item, true);
        break;
      case 'use_alias':
        $this->fetch_import($base, $item->name, true, ident_to_str($item->alias));
        break;
      case 'use_unpack':
        if ($item->base !== null) {
          if (!$this->fetch_import($base, $item->base, false))
            break;
          
          $base = $base->fetch(name_to_stra($item->base), false);
          
          if (!$base) {
            $this->error_at($item->base->loc, ERR_ERROR, 'import from non-existent module `%s`', name_to_str($item->base));
            break;
          }
        }
        
        foreach ($item->items as $item)
          $this->handle_import($base, $item);
    }
  }
  
  /**
   * fetches a import (use)
   * 
   * @param  Module $base the base module where to look at
   * @param  Node $name the name of the imported lib
   * @param  boolean $add  add symbol to the symtable
   * @param  string $id a name for the symtable
   * @return boolean
   */
  protected function fetch_import(Module $base, Name $name, $add, $id = null) 
  {
    $parts = name_to_stra($name);
    $last = array_pop($parts);
    
    if ($add && !$id)
      $id = $last;
    
    if ($parts) {
      $trk = [];
      
      foreach ($parts as $part) {
        $base = $base->get($part, false, null, false);
        array_push($trk, $part);
        
        if (!$base) {
          $this->error_at($name->loc, ERR_ERROR, 'import from non-existent module `%s`', implode('::', $trk));
          return false;
        }  
        
        if ($base->kind !== REF_KIND_MODULE &&
            $base->kind !== SYM_KIND_MODULE) {
          $this->error_at($name->loc, ERR_ERROR, '`%s` is not a module, import failed', implode('::', $trk));
          return false;
        }
        
        if ($base->kind === REF_KIND_MODULE)
          $trk = $base->module->path(false);
        
        $base = $base->module;
      }
    }
    
    if ($base->has_child($last)) {
      // import a module
      if (!$add) return true; 
      
      // add symbol to scope
      // note: using get() instead of get_child() gives us the symbol
      $sym = ModuleRef::from($id, $base->get($last), $name, $name->loc);
      return $this->add_symbol($id, $sym);
    }
    
    if (!$base->has($last)) {
      // unknown import
      $this->error_at($name->loc, ERR_ERROR, 'unknown import `%s` from module %s', $last, $base->path());
      
      // TODO: allow unknown imports?
      return false;
      
      /*
      // import a module
      if (!$add) return true;
      
      $this->error_at($name->loc, ERR_INFO, 'assuming <module> (default assumption)');
      
      // add symbol to scope
      $sym = new ModuleRef($id, $base->get_child($last), $name, $name->loc, REF_WEAK);      
      return $this->add_symbol($id, $sym);
      */
    }
    
    if (!$add) return true;
    
    $sym = $base->get($last);
    $ref = SymbolRef::from($id, $sym, $name, $name->loc);
    
    // TODO: remove const/final flags?
    
    return $this->add_symbol($id, $ref);
  }
  
  /* ------------------------------------ */
  
  protected function add_symbol($id, $sym)
  {    
    // apply current flags
    $ppp = $sym->flags & SYM_FLAG_PUBLIC;
    $ppp |= $sym->flags & SYM_FLAG_PRIVATE;
    $ppp |= $sym->flags & SYM_FLAG_PROTECTED;
    
    $sym->flags |= $this->flags;
    
    // restore public/private/protected
    if ($ppp) {
      $sym->flags &= ~(SYM_FLAG_PUBLIC|SYM_FLAG_PRIVATE|SYM_FLAG_PROTECTED);
      $sym->flags |= $ppp;
    }
    
    // set branch
    $sym->branch = $this->branch;
    
    // get kind for error-messages
    $kind = symkind_to_str($sym->kind);
    
    # print "adding symbol $id\n";
    $cur = $this->scope->get($id, false, null, true);
    
    if (!$cur) {
      # print "no previous entry, adding it!\n";
      // simply add it
      $this->scope->add($id, $sym);
      return true;
    }
    
    // prev symbol was weak and can be replaced.
    // ignore all other flags
    if ($cur->flags & SYM_FLAG_WEAK) {
      // the prev symbol must not be dropped in this case,
      // just forget about it
      $this->scope->set($id, $sym);
      return true;
    }
    
    assert(!($sym->flags & SYM_FLAG_WEAK));
    
    if ($cur->kind > SYM_REF_DIVIDER) {
      $this->error_at($sym->loc, ERR_ERROR, '%s `%s` collides with a referenced symbol', $kind, $sym->name);
      $this->error_at($cur->loc, ERR_INFO, 'reference was here');
      return false;
    }
    
    $ninc = !!($sym->flags & SYM_FLAG_INCOMPLETE);
    $pinc = !!($cur->flags & SYM_FLAG_INCOMPLETE);
    
    if (($ninc || $pinc) && $cur->kind !== $sym->kind) {
      // incomplete symbols can only replace symbols of the same kind
      $this->error_at($sym->loc, ERR_ERROR, 'type mismatch (incomplete symbol)');
      return false;
    }
    
    if (($ninc && !$pinc) || ($ninc && $pinc)) {
      // no need to add it again
      # print "skipping incomplete symbol\n";
      return false;
    }
    
    if ($pinc && !$ninc) {
      // replace symbol
      # print "replacing previous incomplete symbol\n";
      $this->scope->set($id, $sym);
      return true;
    }
     
    // TODO: if kind is a class, check base class and interfaces as well   
    if ($cur->flags & SYM_FLAG_FINAL) {
      // whops, not allowed
      $this->error_at($sym->loc, ERR_ERROR, '%s `%s` hides a final symbol in this scope-chain', $kind, $sym->name);
      $this->error_at($cur->loc, ERR_INFO, 'previous declaration was here');
      return false;
    }
    
    // check again, but now only in the same scope
    $cur = $this->scope->get($id, false, null, false); // TODO: bad api
    # print "checking same scope\n";
    
    if (!$cur) {
      // prev symbol is not directly in the same scope
      # print "symbol is not in the same scope, adding it!\n";
      $this->scope->add($id, $sym);
      return true;
    }
    
    if ($cur->flags & SYM_FLAG_CONST) {
      // same as final, but only applies in the same scope
      $this->error_at($sym->loc, ERR_ERROR, '%s `%s` overrides a constant symbol in the same scope', $kind, $sym->name);
      $this->error_at($cur->loc, ERR_INFO, 'previous declaration was here');
      return false;
    }
    
    // a reference can not hide other symbols
    if ($sym->kind > SYM_REF_DIVIDER) {
      $this->error_at($sym->loc, ERR_ERROR, '%s `%s` collides with a already defined symbol in this scope', $kind, $sym->name);
      $this->error_at($cur->loc, ERR_INFO, 'previous declaration was here');
      return false;
    }
    
    # print "replacing previous symbol\n";
    
    // mark the previous symbol as unreachable
    $this->scope->drop($id, $cur);
    
    // replace previous symbol
    $this->scope->set($id, $sym);
    return true;
  }
  
  /* ------------------------------------ */
  
  protected function enter_module($node)
  {
    $name = $node->name;
    $rmod = null;
    
    if ($name === null) {
      // switch to global scope
      array_push($this->sstack, $this->scope);
      $this->scope = $this->ctx->get_root();
    } else {
      if ($name->root)
        // use root module
        $rmod = $this->ctx->get_root();
      else {
        // this would be an error in the grammar
        if (!($this->scope instanceof Module)) {
          assert(0);
          return $this->drop();
        }
        
        // use current module
        $rmod = $this->scope;
      }
       
      $curr = $rmod->fetch(name_to_stra($name), true, SYM_FLAG_NONE, $node->loc);
      
      if ($curr === null) {
        $this->reveal_collision($rmod, $name);        
        return $this->drop();
      }
       
      array_push($this->sstack, $this->scope);
      $this->scope = $curr;
          }
  }
  
  protected function leave_module($node) 
  {
    // back to prev scope
    $this->scope = array_pop($this->sstack);
  }
  
  protected function visit_use_decl($node)
  {
    switch ($node->item->kind()) {
      case 'name':
      case 'use_alias':
        $base = name_to_stra($node->item);
        break;
      case 'use_unpack':
        if ($node->item->base === null)
          goto rmod;
        
        $base = name_to_stra($node->item->base);
    }
    
    $base = array_shift($base);
    $bsym = $this->scope->get($base, false, null);
    
    if ($bsym && $bsym->kind === REF_KIND_MODULE)
      // use foo::bar; 
      // use bar::baz; -> use {foo::}bar::baz;
      $base = $bsym->module->get_prev();
    else {
      rmod:
      $base = $this->ctx->get_root();
    }
    
    // this function is recursive
    return $this->handle_import($base, $node->item);
  }
  
  protected function visit_enum_decl($node) 
  {
    if ($node->members === null)
      return; // ignore it
    
    $flags = SYM_FLAG_CONST | SYM_FLAG_FINAL;
    
    if ($node->mods) {
      if (!$this->check_mods($node->mods, false, false))
        return $this->drop();
      
      $flags = mods_to_symflags($node->mods, $flags);
    }
    
    $base = 0;
    
    foreach ($node->members as $member) {
      if ($member->dest->kind() !== 'ident') {
        $this->error_at($member->loc, ERR_ERROR, 'destructors are not allowed in enum-declarations');
        continue; 
      }
      
      $id = ident_to_str($member->dest);
      $val = null;
      
      if ($member->init !== null) {
        $val = $this->handle_expr($member->init);
        
        if ($val->kind === VAL_KIND_UNKNOWN) {
          $this->error_at($member->init->loc, ERR_ERROR, 'enum member-initalizer must be reducible to constant value');
          continue;
        }
        
        if ($val->kind !== VAL_KIND_LNUM) {
          $this->error_at($member->init->loc, ERR_ERROR, 'enum members must be integers');
          continue;
        }
        
        $base = $val->value + 1;
      } else
        $val = new Value(VAL_KIND_LNUM, $base++);
        
      $sym = new VarSym($id, $val, $flags, $member->loc);      
      
      if (!$this->add_symbol($id, $sym))
        return $this->drop();
    }
  }
  
  protected function enter_iface_decl($node)
  {
    $flags = SYM_FLAG_CONST;
        
    if ($node->members === null)
      $flags |= SYM_FLAG_INCOMPLETE;
    else
      // check all members (each must be incomplete or without values)
      if (!$this->check_iface_members($node->members))
        return $this->drop();
    
    $iid = ident_to_str($node->id);
    $sym = new IfaceSym($iid, $flags, $node->loc);
    
    if (!$this->add_symbol($iid, $sym))
      return $this->drop();
    
    array_push($this->sstack, $this->scope);
    $node->scope = new ClassScope($sym, $this->scope);
    $this->scope = $node->scope;
        
    // we don't need pass 1 and 2 here
    $this->pass = 3;
  }
  
  protected function leave_iface_decl($node)
  {
    $this->scope = array_pop($this->sstack);
    $this->pass = 0;
  }
  
  protected function enter_class_decl($node)
  {    
    $flags = SYM_FLAG_CONST;
        
    if ($node->mods) {
      if (!$this->check_mods($node->mods, false, true))
        return $this->drop();
      
      $flags = mods_to_symflags($node->mods, $flags);
    }
    
    if ($node->members === null)
      $flags |= SYM_FLAG_INCOMPLETE;
    else
      // simple test to detect abstract-members
      if (!$this->check_class($node, $flags))
        return $this->drop();    
    
    $super = null;
    if ($node->ext !== null) {
      $super = $this->handle_class_ext($node->ext);
      if (!$super) return $this->drop();
    }
    
    $impls = null;
    if ($node->impl !== null) {
      $impls = $this->handle_class_impl($node->impl);
      if (!$impls) return $this->drop();
    }
          
    $cid = ident_to_str($node->id);
    $sym = new ClassSym($cid, $flags, $node->loc);
    $sym->super = $super;
    $sym->impls = $impls;
          
    if (!$this->add_symbol($cid, $sym))
      return $this->drop();
    
    array_push($this->sstack, $this->scope);
    $node->scope = new ClassScope($sym, $this->scope);
    $this->scope = $node->scope;
            
    // pass 1: add all member-symbols without entering functions
    // pass 2: improve symbols and enter functions ...
    $this->handle_class_members($node->members); 
    
    // TODO: inspect ctor here
    // because the analyzer will complain about uninitialized symbols
  }
  
  protected function leave_class_decl($node)
  {    
    assert($this->scope instanceof ClassScope);
    $sym = $this->scope->symbol;
    
    if (!($sym->flags & SYM_FLAG_ABSTRACT))
      $this->check_class_final($sym);
    
    // back to prev scope
    $this->scope = array_pop($this->sstack);
    $this->pass = 0;
  }
  
  protected function enter_ctor_decl($node) 
  {    
    if ($this->pass !== 4)
      return $this->drop();
      
    $flags = SYM_FLAG_NONE;
    
    if ($node->mods) {
      if (!$this->check_mods($node->mods, true, false))
        return $this->drop();
      
      $flags = mods_to_symflags($node->mods, $flags);
    }
    
    if ($flags & SYM_FLAG_EXTERN && $node->body !== null) {
      $this->error_at($node->loc, ERR_ERROR, 'extern function can not have a body');
      return $this->drop();
    }
    
    if ($flags & SYM_FLAG_STATIC) {
      $this->error_at($node->loc, ERR_ERROR, 'constructor can not be static');
      return $this->drop();
    }
    
    $tparm = false;
    if ($node->params !== null)
      foreach ($node->params as $param)
        if ($param->kind() === 'this_param') {
          $tparm = true;
          break;
        }
    
    if ($node->body === null && !$tparm)
      $flags |= SYM_FLAG_INCOMPLETE;
    
    if ($node->params !== null)
      if (!$this->check_params($node->params, true))
        return $this->drop();
    
    $fid = '#ctor';
    $sym = new FnSym($fid, $flags, $node->loc);
    
    if (!$this->add_symbol($fid, $sym))
      return $this->drop();
    
    $csym = $this->scope->symbol;
    
    array_push($this->pstack, $this->pass);
    $this->pass = 0;
    
    array_push($this->sstack, $this->scope);
    $node->scope = new FnScope($sym, $this->scope);
    $this->scope = $node->scope;
        
    // backup flags
    array_push($this->fstack, $this->flags);
    $this->flags = SYM_FLAG_NONE;
    
    // just for debugging
    $sym->fn_scope = $this->scope;
    
    if ($node->params !== null)
      $this->handle_params($sym, $node->params, $csym);
    
    ++$this->infn;
  }
  
  protected function leave_ctor_decl($node) 
  {
    $this->scope = array_pop($this->sstack);
    $this->flags = array_pop($this->fstack);
    $this->pass = array_pop($this->pstack);
    
    --$this->infn;    
  }
  
  protected function enter_dtor_decl($node) 
  {
    if ($this->pass !== 4)
      return $this->drop();
      
    $flags = SYM_FLAG_NONE;
    
    if ($node->mods) {
      if (!$this->check_mods($node->mods, true, false))
        return $this->drop();
      
      $flags = mods_to_symflags($node->mods, $flags);
    }
    
    if ($flags & SYM_FLAG_EXTERN && $node->body !== null) {
      $this->error_at($node->loc, ERR_ERROR, 'extern function can not have a body');
      return $this->drop();
    }
    
    if ($flags & SYM_FLAG_STATIC) {
      $this->error_at($node->loc, ERR_ERROR, 'destructor can not be static');
      return $this->drop();
    }
    
    if ($node->body === null)
      $flags |= SYM_FLAG_INCOMPLETE;
    
    if ($node->params !== null)
      if (!$this->check_params($node->params, false))
        return $this->drop();
    
    $fid = '#dtor';
    $sym = new FnSym($fid, $flags, $node->loc);
    
    if (!$this->add_symbol($fid, $sym))
      return $this->drop();
    
    array_push($this->pstack, $this->pass);
    $this->pass = 0;
    
    array_push($this->sstack, $this->scope);
    $node->scope = new FnScope($sym, $this->scope);
    $this->scope = $node->scope;
        
    // backup flags
    array_push($this->fstack, $this->flags);
    $this->flags = SYM_FLAG_NONE;
    
    // just for debugging
    $sym->fn_scope = $this->scope;
    
    if ($node->params !== null)
      $this->handle_params($sym, $node->params);
    
    ++$this->infn;
  }
  
  protected function leave_dtor_decl($n) 
  {
    $this->scope = array_pop($this->sstack);
    $this->flags = array_pop($this->fstack);
    $this->pass = array_pop($this->pstack);
    
    --$this->infn;  
  }
  
  protected function enter_nested_mods($node)
  {
    // print "nested mods!\n";
    
    if (!$this->check_mods($node->mods, true, false))
      return $this->drop();
    
    $flags = mods_to_symflags($node->mods);
    
    array_push($this->fstack, $this->flags);
    $this->flags |= $flags;
  }
  
  protected function leave_nested_mods($node)
  {
    $this->flags = array_pop($this->fstack);
  }
  
  protected function enter_fn_decl($node)
  {        
    // skip on pass 1, 3 and 4
    if ($this->pass === 1 || $this->pass === 3 || $this->pass === 4)
      return $this->drop();
    
    $flags = SYM_FLAG_NONE;
    $apppf = $this->pass > 0;
    
    if ($node->mods) {
      if (!$this->check_mods($node->mods, $apppf, !$apppf))
        return $this->drop();
      
      $flags = mods_to_symflags($node->mods, $flags);
    }
    
    if ($flags & SYM_FLAG_EXTERN && $node->body !== null) {
      $this->error_at($node->loc, ERR_ERROR, 'extern function can not have a body');
      return $this->drop();
    }
    
    // if (in class) and (has modifier static) and (has no modifier extern) and (has no body)
    if ($apppf && $flags & SYM_FLAG_STATIC && !($flags & SYM_FLAG_EXTERN) && $node->body === null) {
      $this->error_at($node->loc, ERR_ERROR, 'static function can not be abstract');
      return $this->drop();
    }
    
    // if (not in class) and (has modifier static) and (has modifier extern)
    if (!$apppf && $flags & SYM_FLAG_STATIC && $flags & SYM_FLAG_EXTERN) {
      $this->error_at($node->loc, ERR_ERROR, 'static function can not be extern');
      return $this->drop();
    }
    
    if (!($flags & SYM_FLAG_EXTERN) && $node->body === null)
      $flags |= SYM_FLAG_INCOMPLETE;
    
    if ($node->params !== null)
      if (!$this->check_params($node->params, false))
        return $this->drop();
    
    $fid = ident_to_str($node->id);
    $sym = new FnSym($fid, $flags, $node->loc);
    
    if (!$this->add_symbol($fid, $sym))
      return $this->drop();
        
    if ($this->pass > 0 && $this->pass !== 5)
      return $this->drop(); // skip params and do not enter ...
    
    array_push($this->pstack, $this->pass);
    $this->pass = 0;
    
    $this->enter_fn($sym, $node);
    // just for debugging
    $sym->fn_scope = $this->scope;
    
    if ($node->params !== null)
      $this->handle_params($sym, $node->params);
  }
  
  protected function leave_fn_decl($node)
  {
    $this->pass = array_pop($this->pstack);
    $this->leave_fn();
  }
    
  protected function visit_let_decl($node)
  {
    return $this->visit_var_decl($node);
  }
  
  protected function visit_var_decl($node)
  {            
    // skip on pass 1, 4 and 5
    if ($this->pass === 2 || $this->pass === 4 || $this->pass === 5)
      return;
    
    $flags = SYM_FLAG_NONE;
    $apppf = $this->pass > 0;
    
    if ($node->mods) {
      if (!$this->check_mods($node->mods, $apppf, !$apppf))
        return $this->drop();
      
      $flags = mods_to_symflags($node->mods, $flags);
    }
    
    foreach ($node->vars as $var)
      $this->handle_var($var->dest, $var->init, $flags);
  }
  
  protected function visit_require_decl($node)
  {
    // require must act as require_once
    $path = $this->handle_expr($node->expr);
    
    if ($path->kind !== VAL_KIND_STR) {
      $this->error_at($node->loc, ERR_ERROR, 'require from unknown source');
      return $this->drop();
    }
    
    $path = $path->value;
    $user = $path;
    
    // require is always relative, except if the path starts with an '/'
    // or on windows [letter]:/ or [letter]:\
    static $abs_re_nix = '/^\//';
    static $abs_re_win = '/^(?:[a-z]:)?(?:\/|\\\\)/i';
    
    if (!preg_match((PHP_OS === 'WINNT') ? $abs_re_win : $abs_re_nix, $path))
      // make realtive path
      $path = dirname($node->loc->file) . DIRECTORY_SEPARATOR . $path;
    
    switch (substr(strrchr($path, '.'), 1)) {
      case 'phs': case 'phm';
        break;
      
      default:
        // try 'phm', then fallback to 'phs'
        if (!is_file($path . '.phm'))
          $path .= '.phs';
        else
          $path .= '.phm';
    }
    
    if (!is_file($path)) {
      $this->error_at($node->loc, ERR_ERROR, 'unable to import file "%s"', $user);
      return;
    }
    
    if (!in_array($path, self::$require_paths)) {
      self::$require_paths[] = $path;
      
      require_once 'parser.php';
      require_once 'source.php';
      
      // cache parser?
      $psr = new Parser($this->ctx);
      $src = new FileSource($path);
      
      $ast = $psr->parse_source($src);
      
      if ($ast) {
        $ast->dest = $src->get_dest();
        $anl = new Analyzer($this->ctx, $this->com);
        
        // analyze unit
        $anl->analyze($ast);
            
        // add it to the compiler
        $this->com->add_unit($ast);
      }
    }
  }
  
  protected function enter_block($node) 
  {
    array_push($this->sstack, $this->scope);
    $node->scope = new Scope($this->scope);
    $this->scope = $node->scope;
      }
  
  protected function leave_block($node) 
  {
    $this->scope = array_pop($this->sstack);
  }
  
  protected function visit_attr_decl($node) 
  {
    $todo = ident_to_str($node->attr->name);
    
    switch ($todo) {
      case 'dbg_value':
        $val = $this->handle_expr($node->attr->value->name);
        
        if ($val->kind === VAL_KIND_FN)
          print "$val\n";
        else
          var_dump($val->value);
        
        break;
      case 'dbg_kind':
        $val = $this->handle_expr($node->attr->value->name);
        print "$val\n";
        break;
    }
  }
  
  protected function visit_label_decl($node) 
  {
    $lid = ident_to_str($node->id);
    
    if (isset ($this->labels[$lid])) {
      $this->error_at($node->loc, ERR_ERROR, 'there is already a label with name `%s` in this scope');
      $this->error_at($this->labels[$lid]->loc, ERR_INFO, 'previous label was here');
      
      // just for error reporting
      $this->walk_some($node->comp);
      
    } else {
      if (isset ($this->gotos[$lid]))
        foreach ($this->gotos[$lid] as $goto)
          $goto->resolved = true;
      
      $label = new Label($lid, $node->loc);
      $label->reachable = true;
      
      $this->labels[$lid] = $label;
      $this->lframe[$lid] = $label;
      
      $label->breakable = true;
      $this->walk_some($node->comp);
      $label->breakable = false;
    }
  }
  
  protected function visit_do_stmt($node) 
  {
    $this->enter_loop();
    
    // no branching needed here
    $this->walk_some($node->stmt);    
    $this->handle_expr($node->expr);
    
    $this->leave_loop();
  }
  
  protected function visit_if_stmt($node) 
  {
    $this->handle_expr($node->expr);    
    $this->walk_branch($node->stmt);
    
    if ($node->elsifs !== null) {
      foreach ($node->elsifs as $elsif) {
        $this->handle_expr($elsif->expr);
        $this->walk_branch($elsif->stmt);
      }
    }
    
    if ($node->els !== null)
      $this->walk_branch($node->els->stmt);
  }
  
  protected function visit_for_stmt($node) 
  {
    $lexical = false;
    
    if ($node->init !== null) {
      $kind = $node->init->kind();
      
      if ($kind === 'let_decl' || $kind === 'var_decl')
        $lexical = true;
      
      if ($lexical === true) {
        # print "creating lexical scope\n";
        array_push($this->sstack, $this->scope);
        $this->scope = new Scope($this->scope);
              }
      
      $this->walk_some($node->init);
    }
    
    $this->allow_const_assign = false;
    
    if ($node->test !== null)
      $this->handle_expr($node->test);
    
    if ($node->each !== null)
      $this->handle_expr($node->each);
    
    $this->allow_const_assign = true;
    
    $this->enter_loop();
     
    if ($node->stmt->kind() === 'block' && $lexical === true) {
      // no need to create a extra scope
      # print "using lexical scope\n";
      $node->stmt->scope = $this->scope;
      $this->walk_some($node->stmt->body);
      $this->scope = array_pop($this->sstack);
    } else
      $this->walk_some($node->stmt);
      
    $this->leave_loop();
  }
  
  protected function visit_for_in_stmt($node) 
  {
    $lexical = false;
    
    $kind = $node->lhs->kind();
      
    if ($kind === 'let_decl' || $kind === 'var_decl')
      $lexical = true;
    
    if ($kind === 'var_decl' && $node->lhs->mods !== null)
      if (mods_to_symflags($node->lhs->mods) & SYM_FLAG_CONST) {
        $this->error_at($node->lhs->loc, ERR_ERROR, 'const-modifier not allowed here');
        return;
      }
    
    if ($lexical === true) {
      # print "creating lexical scope\n";
      array_push($this->sstack, $this->scope);
      $this->scope = new Scope($this->scope);
          }
    
    $this->walk_some($node->lhs);    
    $this->handle_expr($node->rhs);
    
    $this->enter_loop();
    
    if ($node->stmt->kind() === 'block' && $lexical === true) {
      // no need to create a extra scope
      # print "using lexical scope\n";
      $node->stmt->scope = $this->scope;
      $this->walk_some($node->stmt->body);
      $this->scope = array_pop($this->sstack);
    } else
      $this->walk_some($node->stmt);
      
    $this->leave_loop();
  }
  
  protected function visit_try_stmt($node) 
  {    
    // no branch needed
    $this->walk_some($node->stmt);
    
    if ($node->catches !== null) {
      foreach ($node->catches as $catch) {
        if ($catch->name !== null)
          $this->handle_param_hint($catch->name);
        
        array_push($this->sstack, $this->scope);
        $this->scope = new Scope($this->scope);
        $catch->body->scope = $this->scope;
        
        if ($catch->id !== null) {
          $sid = ident_to_str($catch->id);
          $sym = new VarSym($sid, new Value(VAL_KIND_UNKNOWN), SYM_FLAG_NONE, $catch->id->loc);
          
          if (!$this->add_symbol($sid, $sym)) {
            $this->scope = array_pop($this->sstack);
            continue;
          }
        }
        
        // branch needed
        $this->walk_branch($catch->body->body);
        $this->scope = array_pop($this->sstack);
      }
    }  
    
    if ($node->finalizer !== null)
      // branch needed
      $this->walk_scoped_branch($node->finalizer->body); 
  }
  
  protected function visit_php_stmt($node) 
  {
    if ($node->usage !== null)
      foreach ($node->usage as $use)
        foreach ($use->items as $item) {
          $sid = ident_to_str($item->id);
          $sym = $this->scope->get($sid, false, null, true);
          
          if (!$sym)
            $this->error_at($item->id->loc, ERR_ERROR, 'access to undefined symbol `%s`', $sid);
          else
            $sym->reads++;
        }  
  }
  
  protected function visit_goto_stmt($node) 
  {
    $lid = ident_to_str($node->id);
    
    if (isset($this->labels[$lid]) && $this->labels[$lid]->reachable)
      return; // already resolved, no need to add it
    
    $goto = new LGoto($lid, $node->loc);
    $goto->resolved = false;
    
    if (!isset ($this->gotos[$lid]))
      $this->gotos[$lid] = [];
    
    $this->gotos[$lid][] = $goto;
  }
  
  protected function visit_test_stmt($node) 
  {
    $this->walk_some($node->body);
  }
  
  protected function visit_break_stmt($node) 
  {
    if ($node->id !== null) {
      $lid = ident_to_str($node->id);
      
      if (!isset ($this->labels[$lid]))
        $this->error_at($node->loc, ERR_ERROR, 'can not break undefined label `%s`', $lid);
      else {
        $label = $this->labels[$lid];
        
        if (!$label->breakable) {
          $this->error_at($node->loc, ERR_ERROR, 'can not break label `%s` from this position', $lid);
          $this->error_at($label->loc, ERR_INFO, 'label was defined here');
        }
      }
    } else {
      if ($this->inloop === 0)
        $this->error_at($node->loc, ERR_ERROR, 'break outside of loop/switch');
    }
  } 
  
  protected function visit_continue_stmt($node) 
  {
    if ($node->id !== null) {
      $lid = ident_to_str($node->id);
      
      if (!isset ($this->labels[$lid]))
        $this->error_at($node->loc, ERR_ERROR, 'can not continue undefined label `%s`', $lid);
      else {
        $label = $this->labels[$lid];
        
        if (!$label->breakable) {
          $this->error_at($node->loc, ERR_ERROR, 'can not continue label `%s` from this position', $lid);
          $this->error_at($label->loc, ERR_INFO, 'label was defined here');
        }
      }
    } else {
      if ($this->inloop === 0)
        $this->error_at($node->loc, ERR_ERROR, 'continue outside of loop/switch');
    } 
  }
  
  protected function visit_throw_stmt($node) 
  {
    $this->handle_expr($node->block);
  }
  
  protected function visit_while_stmt($node) 
  {
    $this->handle_expr($node->test);
    
    $this->enter_loop();
    $this->walk_branch($node->stmt);
    $this->leave_loop();
  }
  
  protected function visit_assert_stmt($node) 
  {
    $this->handle_expr($node->expr);  
  }
  
  protected function visit_switch_stmt($node) 
  {
    $this->handle_expr($node->test);
    
    // switch is not really a loop, but we can break and continue
    $this->enter_loop();
    
    if ($node->cases !== null) {
      foreach ($node->cases as $citem) {
        foreach ($citem->labels as $clabel)
          if ($clabel->expr !== null)
            $this->handle_expr($clabel->expr);
          
        if ($citem->body !== null)
          $this->walk_branch($citem->body);
      }
    }
    
    $this->leave_loop();
  }
  
  protected function visit_return_stmt($node) 
  {
    $this->handle_expr($node->expr);
        
    if ($this->infn < 1) {
      $this->error_at($node->loc, ERR_ERROR, 'return outside of function');
      return;
    }
  }
  
  /* ------------------------------------ */
    
  /**
   * adds the given parameters to the current scope
   * 
   * @param  FnSym $fnsym
   * @param  array $params
   * @param  ClassSym $csym
   */
  protected function handle_params($fnsym, $params, $csym = null)
  {
    $error = false;
    
    foreach ($params as $param) {
      $kind = $param->kind();
      
      if ($kind === 'param' || $kind === 'rest_param') {        
        $flags = SYM_FLAG_NONE;
        
        if ($kind === 'param' && $param->mods !== null) {
          if (!$this->check_mods($param->mods)) {
            $error = true;
            continue;
          }
          
          $flags = mods_to_symflags($param->mods, $flags);
        }
        
        $pid = ident_to_str($param->id);
        $sym = new ParamSym($pid, new Value(VAL_KIND_UNKNOWN), $flags, $param->loc);
        $sym->rest = $kind === 'rest_param';
        
        if ($param->hint !== null) {
          $hint = $this->handle_param_hint($param->hint);
          
          if ($hint !== null)
            $sym->hint = $hint;
        }
        
        if (!$this->add_symbol($pid, $sym))
          $error = true;
          
        if (!$error)
          $fnsym->params[] = $sym; 
      } else {
        assert($kind === 'this_param');
        assert($csym !== null);
        
        $pid = ident_to_str($param->id);
        $sym = $csym->members->get($pid);
        
        if ($sym === null && $csym->super) {
          $curr = $csym->super;
          
          do {
            $sym = $curr->symbol->members->get($pid);
            
            if ($sym !== null)
              break;
            
            $curr = $curr->super;
          } while ($curr);
        }
        
        if ($sym === null) {
          $this->error_at($param->loc, ERR_ERROR, 'this-parameter refers to a undefined member');
          $error = true;
          continue;
        }
        
        if ($sym->kind !== SYM_KIND_VAR) {
          $this->error_at($param->loc, ERR_ERROR, 'this-parameter refers to a invalid member');
          $error = true;
          continue;
        }
        
        if ($sym->flags & SYM_FLAG_CONST && $sym->value->kind !== VAL_KIND_EMPTY) {
          $this->error_at($param->loc, ERR_ERROR, 'this-parameter performs an implicit re-assigment on a constant member');
          $error = true;
          continue;
        }
        
        if ($param->hint !== null)
          if (!$this->handle_param_hint($param->hint)) {
            $error = true;
            continue;
          }
        
        $sym->value = new Value(VAL_KIND_UNKNOWN);
      }
    }
    
    return !$error;
  }
  
  /**
   * handle a parameter hint
   * 
   * @param  Node $phint
   * @return Symbol
   */
  protected function handle_param_hint($phint)
  {
    $shint = $this->handle_expr($phint);
    
    if ($shint->kind !== VAL_KIND_TYPE) {
      switch ($shint->kind) {
        case VAL_KIND_CLASS:
        case VAL_KIND_IFACE:
          if ($shint->symbol->flags & SYM_FLAG_INCOMPLETE) {
            $this->error_at($phint->loc, ERR_ERROR, '`%s` must be fully defined before it can be used', $shint->symbol->name);  
            return null;
          }
          
          break;
        default:
          if ($shint->symbol !== null) {
            $this->error_at($phint->loc, ERR_ERROR, '`%s` can not be a type-hint', $shint->symbol->name);
            $this->error_at($hsint->symbol->loc, ERR_INFO, 'declaration was here');
            $this->error_at($phint->loc, ERR_INFO, 'only type-ids, classes and interfaces can be used as hints');
          } else
            $this->error_at($phint->loc, ERR_ERROR, 'invalid type-hint');
          
          return null;
      }
      
      $hint = $shint->symbol;
    } else
      $hint = $shint->value; // use type-id
      
    return $hint;
  }
  
  /**
   * check params
   * 
   * @param  array $params
   * @param  bool $athis allow this-params
   * @return boolean
   */
  protected function check_params($params, $athis)
  {
    $seen_rest = false;
    $rest_loc = null;
    $seen_error = false;
    
    foreach ($params as $param) {
      $kind = $param->kind();
      
      if ($kind === 'rest_param') {
        if ($seen_rest) {
          $seen_error = true;
          $this->error_at($param->loc, ERR_ERROR, 'duplicate rest-parameter');
          $this->error_at($rest_loc, ERR_INFO, 'previous rest-parameter was here');
        } else {
          $seen_rest = true;
          $rest_loc = $param->loc;
        }
      } 
      
      elseif ($kind === 'this_param' && !$athis) {
        $seen_error = true;
        $this->error_at($param->loc, ERR_ERROR, 'this-parameter not allowed here');
        $this->error_at($param->loc, ERR_INFO, 'only contructor-methods can handle this-parameters');
      }
      
      elseif ($seen_rest) {
        $seen_error = true;
        $this->error_at($param->loc, 'parameter after rest-parameter');
        $this->error_at($rest_loc, ERR_INIO, 'rest-parameter was here');
      }
    }
    
    return !$seen_error;
  }
  
  /**
   * handles a variable declaration
   * 
   * @param  Ident|ArrDestr|ObjDestr $var
   * @param  Node $init
   * @param  int $flags
   * @param  Value $val
   * @return boolean
   */
  protected function handle_var($var, $init, $flags, Value $val = null)
  {        
    switch ($var->kind()) {
      case 'ident':
        $vid = ident_to_str($var);
        
        if ($this->pass > 0 && $this->pass !== 3)
          // do not handle expressions
          $val = new Value(VAL_KIND_EMPTY); 
        elseif (!$val)
          $val = $this->handle_expr($init);
        
        if ($val->kind === VAL_KIND_UNKNOWN && $flags & SYM_FLAG_CONST) {
          $this->error_at($var->loc, ERR_ERROR, 'constant variables must be reducible at compile-time');
          return false;
        }
        
        $sym = new VarSym($vid, $val, $flags, $var->loc);       
        return $this->add_symbol($vid, $sym);
      case 'obj_destr':
        return $this->handle_var_obj($var, $flags);
      case 'arr_destr':
        return $this->handle_var_arr($var, $flags);
      default:
        print "what? " . $var->kind() . "\n";
        assert(0);
    }
  }
  
  /**
   * handles a variable-object-destructor
   * 
   * @param  ObjDestr $dest
   * @param  int $flags
   * @return boolean
   */
  protected function handle_var_obj($dest, $flags)
  {
    foreach ($dest->items as $item) {
      $kind = $item->kind();
      
      if ($kind !== 'ident') {
        $this->error_at($item->loc, ERR_ERROR, 'invalid property id');
        continue;   
      }
      
      $this->handle_var($item, null, $flags, new Value(VAL_KIND_UNKNOWN));
    }
    
    return true;
  }
  
  /**
   * handles a variable-array-destructor
   * 
   * @param  ArrDestr $dest
   * @param  int $flags
   * @return boolean
   */
  protected function handle_var_arr($dest, $flags)
  {
    foreach ($dest->items as $item)
      $this->handle_var($item, null, $flags, new Value(VAL_KIND_UNKNOWN));
    
    return true;
  }
  
  /**
   * check interface-members
   * 
   * @param  array $members
   * @return boolean
   */
  protected function check_iface_members($members)
  {
    $error = false;
    
    foreach ($members as $mem) {
      $kind = $mem->kind();
      
      if ($kind === 'fn_decl') {
        // TODO: allow static-methods with body here?
        if ($mem->body !== null) {
          $this->error_at($mem->body->loc, ERR_ERROR, 'interface-method can not have a body');
          $error = true;
        }        
      } 
      
      elseif ($kind === 'nested_mods') {
        if (!$this->check_iface_members($mem->members))
          $error = true;
      }
       
      elseif ($kind === 'let_decl' || $kind === 'var_decl') {
        foreach ($mem->vars as $var)
          if ($var->init !== null) {
            // TODO: allow static-varaiables with initializers here?
            $this->error_at($var->loc, ERR_ERROR, 'interface-variables can not be initialized');
            $error = true;
          }
      }
      
    }
    
    return !$error;
  }
  
  /**
   * check a class and its members
   * 
   * @param  Node $node 
   * @param  int $flags
   * @return boolean
   */
  protected function check_class($node, &$flags)
  {
    $ext = !!($flags & SYM_FLAG_EXTERN);
    return $this->check_class_members($node->members, $flags, $ext);
  }
  
  /**
   * checks a class in its final state (the class is not abstract)
   * 
   * @param  ClassSym $sym
   * @return bool
   */
  protected function check_class_final($sym)
  {
    assert(!($sym->flags & SYM_FLAG_ABSTRACT));
    
    $need = new SymTable; // members which need a implementation
    $nloc = new SymTable; // where incomplete members have been defined
    $done = new SymTable; // members which are implemented
    
    // 1. collect abstract members from interfaces
    if ($sym->impls !== null) 
      $this->collect_iface_impls($sym->impls, $need, $nloc);
   
    // 2. collect members from super class(es)
    $this->collect_class_impls($sym->super, $need, $nloc, $done);
    
    // 3. collect members from current class
    foreach ($sym->members as $mem) 
      $done->add($mem->name, $mem);
    
    // 4. validate
    $okay = true;
    
    foreach ($need as $nam) {
      $inh = $nloc->get($nam->name);
      
      if (!$done->has($nam->name)) {
        $this->error_at($sym->loc, ERR_ERROR, 'method `%s` derived from `%s` needs an implementation', $nam->name, $inh->name);
        $this->error_at($nam->loc, ERR_INFO, 'defined abstract here');
        $okay = false;
      } else {
        $imp = $done->get($nam->name);
        
        if (!$this->check_member_impl($imp, $nam)) {
          $this->error_at($imp->loc, ERR_ERROR, 'implemention of method `%s` must match the declaration in `%s`', $imp->name, $inh->name);
          $this->error_at($nam->loc, ERR_INFO, 'declaration was here');
          $okay = false;
        }
      }
    }
    
    return $okay;
  }
  
  /**
   * ensures a complete symbol, performs a lookup if necessary
   * 
   * @param  SymbolRef $ref
   * @return Symbol
   */
  protected function ensure_complete_symbol($ref)
  {
    $sym = $ref->symbol;
    
    if (!($sym->flags & SYM_FLAG_INCOMPLETE))
      return $sym;
    
    $mod = $this->ctx->get_module();
    $nam = name_to_stra($ref->path);
    $lst = array_pop($nam);
    $cnt = $mod->fetch($nam, false);
    
    // the symbol can not be null here
    assert($cnt !== null);
    
    if ($cnt->has($lst)) {
      $sym = $cnt->get($lst, false, null);
      
      if (!($sym->flags & SYM_FLAG_INCOMPLETE))
        return $sym;
    }
    
    return null;
  }
  
  /**
   * collects absract members from a class
   * 
   * @param  SymbolRef $base
   * @param  SymTable $need [description]
   * @param  SymTable $nloc [description]
   * @param  SymTable $done [description]
   */
  protected function collect_class_impls($base, $need, $nloc, $done)
  {
    while ($base !== null) {
      $csym = $this->ensure_complete_symbol($base);
      
      if ($csym === null) {
        $this->error_at($sym->loc, ERR_ERROR, '`%s` must be fully defined before it can be used', $csym->name);
        $this->error_at($csym->loc, ERR_INFO, 'declaration was here');
        continue;
      }
      
      foreach ($csym->members as $mem)
        // which means abstract in this context
        if ($mem->flags & SYM_FLAG_INCOMPLETE) {
          $need->set($mem->name, $mem);
          $nloc->set($mem->name, $csym);
        } else
          $done->add($mem->name, $mem);
      
      if ($csym->impls !== null)
        $this->collect_iface_impls($csym->impls, $need, $nloc, $csym);
      
      $base = $csym->super;
    }
  }
  
  /**
   * collects abstract members from an interface
   * 
   * @param  array $impls
   * @param  SymTable $need
   * @param  SymTable $nloc
   * @param  Symbol $deri
   */
  protected function collect_iface_impls($impls, $need, $nloc, $deri = null)
  {
    foreach ($impls as $idx => $impl) {
      $isym = $this->ensure_complete_symbol($impl);
      
      if ($isym === null) {
        $this->error_at($impl->loc, ERR_ERROR, '`%s` must be fully defined before it can be used', $impl->name);
          
        if ($deri !== null)
          $this->error_at($deri->loc, ERR_INFO, 'derived from `%s`', $deri->name);
        
        $this->error_at($impl->symbol->loc, ERR_INFO, 'declaration was here');
        continue;
      }
      
      foreach ($isym->members as $memb) {
        $need->set($memb->name, $memb);
        $nloc->set($memb->name, $isym);
      }
      
      if ($isym->exts !== null)
        $this->collect_iface_impls($isym->exts, $need, $nloc, $isym);
    }
  }
  
  /**
   * checks if both given members are compatible to each other
   * 
   * @param  Symbol $imp the implemented member
   * @param  Symbol $inh the abstract member
   * @return boolean
   */
  protected function check_member_impl($imp, $inh) 
  {
    // 1. check related flags
    static $rflags = [
      SYM_FLAG_CONST,
      SYM_FLAG_FINAL,
      SYM_FLAG_PUBLIC,
      SYM_FLAG_PRIVATE,
      SYM_FLAG_PROTECTED,
      SYM_FLAG_SEALED,
      SYM_FLAG_INLINE
    ];
    
    foreach ($rflags as $flag)
      if (($inh->flags & $flag && !($imp->flags & $flag)) ||
          ($imp->flags & $flag && !($inh->flags & $flag))) {
        #$this->error_at($imp->loc, ERR_ERROR, 'wrong modifiers');
        return false;
      }
      
    // 2. check parameter count
    if (count($imp->params) !== count($inh->params)) {
      #$this->error_at($imp->loc, ERR_ERROR, 'wrong parameter count %d <-> %d', count($imp->params), count($inh->params));
      return false;
    }
    
    // 3. check parameter hint/flags        
    foreach ($imp->params as $idx => $imp_param) {
      $inh_param = $inh->params[$idx];
      
      if ($imp_param->hint !== null) {
        if ($inh_param->hint === null) {
          #$this->error_at($imp_param->loc, ERR_ERROR, 'missing type-hint');
          return false;
        }
        
        // note: the hint is the same reference
        if ($imp_param->hint !== $inh_param->hint) {
          // TODO: check if the hints share the same base-class or interface
          #$this->error_at($imp_param->loc, ERR_ERROR, 'different type-hint');
          return false;
        }
      }
      
      if ($imp_param->flags !== $inh_param->flags) {
        #$this->error_at($imp_param->loc, ERR_ERROR, 'different flags');
        return false;
      }
      
      if ($imp_param->rest !== $inh_param->rest) {
        #$this->error_at($imp_param->loc, ERR_ERROR, 'rest-parameter mismatch');
        return false;
      }
      
      // the name does not matter
    }
    
    return true;
  }
  
  /**
   * checks a class-extend
   * 
   * @param  Name $ext
   * @return Symbol
   */
  protected function handle_class_ext($name)
  {    
    $val = $this->handle_expr($name);
    
    if ($val->kind !== VAL_KIND_CLASS) {
      $this->error_at($name->loc, ERR_ERROR, '`%s` can not be extended (used as class)', name_to_str($name));
      return null;
    }
    
    return SymbolRef::from(null, $val->symbol, $name, $name->loc);
  }
  
  /**
   * checks a class-implement
   * 
   * @param  array $impl
   * @return array
   */
  protected function handle_class_impl($impl)
  {
    $ifaces = [];
    $fail = false;
    
    foreach ($impl as $imp) {
      $val = $this->handle_expr($imp);
      
      if ($val->kind !== VAL_KIND_IFACE) {
        $this->error_at($imp->loc, ERR_ERROR, '`%s` can not be implemented (used as interface)', name_to_str($imp));
        $fail = true;
        continue;
      }  
      
      $ifaces[] = SymbolRef::from(null, $val->symbol, $imp, $imp->loc);
    }
    
    return $fail ? null : $ifaces;
  }
  
  /**
   * check class-members
   * 
   * @param  array $members
   * @param  int $flags
   * @param  boolean $ext
   * @return boolean
   */
  protected function check_class_members($members, &$flags, $ext)
  {
    $error = false;
    
    foreach ($members as $mem) {
      $kind = $mem->kind();
      
      if ($kind === 'fn_decl') {
        // extern + function-body is a no-no
        if ($ext && $mem->body !== null) {
          $this->error_at($mem->body->loc, ERR_ERROR, 'extern class-method can not have a body');
          $error = true;
        }
        
        // mark the class itself as abstract
        if (!$ext && $mem->body === null)
          $flags |= SYM_FLAG_ABSTRACT;
        
      } 
      
      elseif ($kind === 'nested_mods')
        if (!$this->check_class_members($mem->members, $flags, $ext))
          $error = true;
      
      elseif ($ext) {
        $this->error_at($mem->loc, ERR_ERROR, 'invalid member in extern class');
        $this->error_at($mem->loc, ERR_INFO, 'only abstract functions are allowed');
        $error = true;
      }
    }
    
    return !$error;
  }
  
  protected function handle_class_members($members)
  {
    array_push($this->fstack, $this->flags);
    $this->flags |= SYM_FLAG_WEAK;
    
    $this->pass = 1; // vars without initializers
    $this->walk_some($members);
    
    $this->flags = array_pop($this->fstack);
    
    $this->pass = 2; // methods without body
    $this->walk_some($members);
    
    $this->pass = 3; // vars with initializers
    $this->walk_some($members);
    
    $this->pass = 4; // ctor/dtor
    $this->walk_some($members); 
    
    $this->pass = 5; // methods with body
    // walk_some() performed by the walker itself
  }
  
  /**
   * checks mods
   * 
   * @param  array  $mods
   * @param  boolean $ppp  allow p(ublic|rivate|rotected)
   * @param  boolean $ext  allow extern
   * @return boolean
   */
  protected function check_mods($mods, $ppp = false, $ext = true) 
  {
    $seen = [];
    static $pppa = [ T_PUBLIC, T_PRIVATE, T_PROTECTED ];
    
    foreach ($mods as $mod) {
      $type = $mod->type;
      
      if (isset ($seen[$type])) {
        $this->error_at($mod->loc, ERR_WARN, 'duplicate modifier `%s`', $mod->value);
        $this->error_at($seen[$type], ERR_INFO, 'previous modifier was here');
      }
      
      if ((!$ext && $type === T_EXTERN) || 
          (!$ppp && in_array($type, $pppa))) {
        $this->error_at($mod->loc, ERR_ERROR, 'modifier `%s` is not allowed here', $mod->value);
        return false;
      }
      
      $seen[$type] = $mod->loc;
    }  
    
    return true;
  }
  
  /* ------------------------------------ */
  
  protected function handle_expr($expr)
  {
    if ($expr === null)
      $this->value = new Value(VAL_KIND_EMPTY);  
    else    
      $this->walk_some($expr);
    
    return $this->value;
  }
  
  /* ------------------------------------ */
  
  protected function visit_expr_stmt($node)
  {
    $this->handle_expr($node->expr);
  }
  
  protected function visit_fn_expr($node)
  {    
    if ($node->params !== null)
      if (!$this->check_params($node->params, false))
        return $this->drop();
        
    if ($node->id !== null)
      $fid = ident_to_str($node->id);
    else
      $fid = '#anonymus~' . (self::$anon_uid++);
    
    $sym = new FnSym($fid, SYM_FLAG_NONE, $node->loc);  
    $this->enter_fn($sym, $node);
    
    // the symbol gets added after a new scope was entered
    if (!$this->add_symbol($fid, $sym)) {
      $this->leave_fn();
      return $this->drop();
    }
    
    // just for debugging
    $sym->fn_scope = $this->scope;
           
    if ($node->params !== null)
      $this->handle_params($node->params);
    
    $this->walk_some($node->body);
    $this->leave_fn();
    
    $this->value = new FnValue($sym);
  }
  
  protected function visit_bin_expr($node) 
  {
    $lhs = $this->handle_expr($node->left);
    $rhs = $this->handle_expr($node->right);
    
    if ($lhs->kind !== VAL_KIND_UNKNOWN) {
      // optimize for the translator
      $node->left->value = $lhs;
      
      if ($rhs->kind !== VAL_KIND_UNKNOWN) {
        // store the value anyway
        $node->right->value = $rhs;
        // reduce
        $this->reduce_bin_expr($lhs, $node->op, $rhs, $node->loc);
        goto out;
      }
    }
    
    $this->value = new Value(VAL_KIND_UNKNOWN);
    out:
  }
  
  protected function reduce_bin_expr($lhs, $op, $rhs, $loc) 
  {
    // 1. string-concat
    if ($op->type === T_CONCAT)
      // string-concat
      $this->value = new Value(VAL_KIND_STR, $lhs->value . $rhs->value);
    
    // 2. arithmetic operators
    elseif ($op->type === T_PLUS ||
            $op->type === T_MINUS ||
            $op->type === T_MUL ||
            $op->type === T_DIV ||
            $op->type === T_MOD ||
            $op->type === T_POW) 
    {
      $lval = $rval = $cval = 0;
    
      // cast left-hand-side to INT if necessary
      if ($lhs->kind !== VAL_KIND_DNUM &&
          $lhs->kind !== VAL_KIND_LNUM)
        $lval = (int) $lhs->value;
      else
        $lval = $lhs->value;
      
      // cast right-hand-side to INT if necessary
      if ($rhs->kind !== VAL_KIND_DNUM &&
          $rhs->kind !== VAL_KIND_LNUM)
        $rval = (int) $rhs->value;
      else
        $rval = $rhs->value;
      
      // result is a dnum, otherwise lnum (ignored if T_MOD -> php "bug")
      $kind = $op->type !== T_MOD && (
                $lhs->kind === VAL_KIND_DNUM ||
                $rhs->kind === VAL_KIND_DNUM
              ) ? VAL_KIND_DNUM : VAL_KIND_LNUM;
      
      switch ($op->type) {
        case T_PLUS:
          $cval = $lval + $rval;
          break;
        case T_MINUS:
          $cval = $lval - $rval;
          break;
        case T_MUL:
          $cval = $lval * $rval;
          break;
        case T_DIV:
        case T_MOD:
          if ($rval == 0) { // compare by value-only
            $this->error_at($loc, ERR_ERROR, 'division by zero');  
            // this is php-thing
            $kind = VAL_KIND_BOOL;
            $cval = false;
          } else
            if ($op->type === T_MOD)
              $cval = $lval % $rval;
            else
              $cval = $lcal / $rval;
          
          break;
        case T_POW:
          $cval = pow($lval, $rval);
      }
      
      $this->value = new Value($kind, $cval);
    }
          
    // 3. bitwise operators
    elseif ($op->type === T_BIT_AND ||
            $op->type === T_BIT_OR ||
            $op->type === T_BIT_XOR)
    {
      $lval = $rval = $cval = 0;
      
      // cast left-hand-side to INT
      if ($lhs->kind !== VAL_KIND_LNUM)
        $lval = (int) $lhs->value;
      else
        $lval = $lhs->value;
      
      // cast right-hand-side to INT
      if ($rhs->kind !== VAL_KIND_LNUM)
        $rval = (int) $rhs->value;
      else
        $rval = $rhs->value;
      
      // result is always a lnum
      switch ($op->type) {
        case T_BIT_AND:
          $cval = $lval & $rval;
          break;
        case T_BIT_OR:
          $cval = $lval | $rval;
          break;
        case T_BIT_XOR:
          $cval = $lval ^ $rval;
          break;
      }
      
      $this->value = new Value(VAL_KIND_LNUM, $cval);
    }
    
    // 4. boolean/equal operators
    elseif ($op->type === T_BOOL_AND ||
            $op->type === T_BOOL_OR ||
            $op->type === T_BOOL_XOR ||
            $op->type === T_EQ ||
            $op->type === T_NEQ) 
    {
      $lval = $lhs->value;
      $rval = $rhs->value;
      $cval = false;
      
      switch ($op->type) {
        case T_BOOL_AND:
          $cval = $lval && $rval;
          break;
        case T_BOOL_OR:
          $cval = $lval || $rval;
          break;
        case T_BOOL_XOR:
          $cval = $lval xor $rval;
          break;
        case T_EQ:
          $cval = $lval === $rval;
          break;
        case T_NEQ:
          $cval = $lval !== $rval;
          break;
      }
      
      $this->value = new Value(VAL_KIND_BOOL, $cval);
    }
    
    // 5. conditional operators
    elseif ($op->type === T_LT ||
            $op->type === T_GT ||
            $op->type === T_LTE ||
            $op->type === T_GTE)
    {
      $lval = $rval = $cval = 0;
      
      // cast left-hand-side to INT if necessary
      if ($lhs->kind !== VAL_KIND_DNUM &&
          $lhs->kind !== VAL_KIND_LNUM)
        $lval = (int) $lhs->value;
      else
        $lval = $lhs->value;
      
      // cast right-hand-side to INT if necessary
      if ($rhs->kind !== VAL_KIND_DNUM &&
          $rhs->kind !== VAL_KIND_LNUM)
        $rval = (int) $rhs->value;
      else
        $rval = $rhs->value;
      
      switch ($op->type) {
        case T_LT:
          $cval = $lval < $rval;
          break;
        case T_GT:
          $cval = $lval > $rval;
          break;
        case T_LTE:
          $cval = $lval <= $rval;
          break;
        case T_GTE:
          $cval = $lval >= $rval;
          break;
      }
      
      $this->value = new Value(VAL_KIND_BOOL, $cval);
    }
    
    // 6. shift-operators
    elseif ($op->type === T_SL ||
            $op->type === T_SR) 
    {
      $lval = $rval = $cval = 0;
      
      // cast left-hand-side to INT
      if ($lhs->kind !== VAL_KIND_LNUM)
        $lval = (int) $lhs->value;
      else
        $lval = $lhs->value;
      
      // cast right-hand-side to INT
      if ($rhs->kind !== VAL_KIND_LNUM)
        $rval = (int) $rhs->value;
      else
        $rval = $rhs->value;
      
      switch ($op->type) {
        case T_SL:
          $cval = $lval << $rval;
          break;
        case T_SR:
          $cval = $lval >> $rval;
          break;
      }
      
      $this->value = new Value(VAL_KIND_LNUM, $cval);
    }
    
    // 7. range operator
    elseif ($op->type === T_RANGE) {
      $lval = $rval = 0;
      $step = 1;
      
      // cast left-hand-side to INT
      if ($lhs->kind !== VAL_KIND_LNUM &&
          $lhs->kind !== VAL_KIND_DNUM)
        $lval = (int) $lhs->value;
      else {
        $lval = $lhs->value;
        
        if ($lhs->kind === VAL_KIND_DNUM)
          $step = .1;
      }
      
      // cast right-hand-side to INT
      if ($rhs->kind !== VAL_KIND_LNUM &&
          $rhs->kind !== VAL_KIND_DNUM)
        $rval = (int) $rhs->value;
      else {
        $rval = $rhs->value;
        
        if ($rhs->kind === VAL_KIND_DNUM)
          $step = .1;
      }
      
      $kind = $step === .1 ? VAL_KIND_DNUM : VAL_KIND_LNUM;
      
      $this->value = new Value(VAL_KIND_ARR, 
        array_map(function($item) use($kind) {
          return new Value($kind, $item);
        }, range($lval, $rval, $step))
      );
    }
    
    // 8. unknown
    else {
      print "unknown binary operator {$op->value} ({$op->type})";
      assert(0);
    }
  }
  
  protected function visit_check_expr($node) 
  {
    $lhs = $this->handle_expr($node->left);
    $rhs = $this->handle_expr($node->right);
    
    if ($lhs->kind !== VAL_KIND_UNKNOWN) {
      // optimize for the translator
      $node->left->value = $lhs;
      
      if ($rhs->kind !== VAL_KIND_UNKNOWN) {
        // store value anyway
        $node->right->value = $rhs;
        // reduce
        $this->reduce_check_expr($lhs, $node->op, $rhs, $node->loc);
        goto out;
      }
    }
    
    $this->value = new Value(VAL_KIND_UNKNOWN);
    out:
  }
  
  protected function reduce_check_expr($lhs, $op, $rhs, $loc)
  {
    if ($rhs->kind === VAL_KIND_TYPE) {
      $res = false;
      
      switch ($rhs->value) {
        case T_TINT:
          $res = $lhs->kind === VAL_KIND_LNUM;
          break;
        case T_TBOOL:
          $res = $lhs->kind === VAL_KIND_BOOL;
          break;
        case T_TFLOAT:
          $res = $lhs->kind === VAL_KIND_DNUM;
          break;
        case T_TSTRING:
          $res = $lhs->kind === VAL_KIND_STR;
          break;
        case T_TREGEXP:
          $res = $lhs->kind === VAL_KIND_REGEXP;
          break;
      }
      
      $this->value = new Value(VAL_KIND_BOOL, $res);
    } else {
      // TODO: implement instanceof?
      $this->value = new Value(VAL_KIND_UNKNOWN);
    }
  }
  
  protected function visit_cast_expr($node) 
  {
    $lhs = $this->handle_expr($node->expr);
    $rhs = $this->handle_expr($node->type);
    
    if ($lhs->kind !== VAL_KIND_UNKNOWN) {
      // store value
      $node->expr->value = $lhs;
      
      if ($rhs->kind !== VAL_KIND_UNKNOWN) {
        // store value
        $node->type->value = $rhs;
        // reduce
        $this->reduce_cast_expr($lhs, $rhs, $node->loc);
        goto out;
      }
    }  
    
    $this->value = new Value(VAL_KIND_UNKNOWN);
    out:
  }
  
  protected function reduce_cast_expr($lhs, $rhs, $loc)
  {
    if ($rhs->kind === VAL_KIND_TYPE) {
      $kind = $cast = 0;
      
      switch ($rhs->value) {
        case T_TINT:
          $kind = VAL_KIND_LNUM;
          $cast = (int) $lhs->value;
          break;
        case T_TBOOL:
          $kind = VAL_KIND_BOOL;
          $cast = (bool) $lhs->value;
          break;
        case T_TFLOAT:
          $kind = VAL_KIND_DNUM;
          $cast = (float) $lhs->value;
          break;
        case T_TSTRING:
          $kind = VAL_KIND_STR;
          $cast = (string) $lhs->value;
          break;
        case T_TREGEXP:
          $kind = VAL_KIND_REGEXP;
          $cast = (string) $lhs->value;
          break;
      }
      
      $this->value = new Value(VAL_KIND_BOOL, $cast);
    } else {
      $sym = $rhs->symbol;
      
      if ($sym->kind !== SYM_KIND_CLASS) {
        $this->error_at($loc, ERR_ERROR, 'can not cast to incomplete type `%s`', $sym->name);
        $this->error_at($sym->loc, ERR_INFO, 'definition was here');
      } else {
        $cst = $sym->members->get('from');
        $stc = false;
        
        if (!$cst || $stc = !($cst->flags & SYM_FLAG_STATIC)) {
          $this->error_at($loc, ERR_ERROR, 'symbol `%s` does not allow casts', $sym->name);
          
          if ($stc === true)
            $this->error_at($cst->loc, ERR_INFO, '^ due to missing `static` modifier here');
        }
        
        // a cast does not necessary yield a instance of the given type.
        // it depends on the return-value of Type.from() 
        // 
        // TODO: revisit after function-inlining?
        $this->value = new Value(VAL_KIND_UNKNOWN);
      }
    }
  }
  
  protected function visit_update_expr($node) 
  {
    $kind = $node->expr->kind();
    $fail = false;
    
    if ($kind !== 'name' && $kind !== 'member_expr') {
      $this->error_at($node->loc, ERR_ERROR, 'invalid increment/decrement operand');
      $fail = true;
    }
    
    $lhs = $this->handle_expr($node->expr);
    
    if (!$fail && $lhs->kind !== VAL_KIND_UNKNOWN) {
      // store value
      $this->reduce_update_expr($lhs, $node->op, $node->loc);
      
      $lhs->symbol->value = $this->value;
      $this->value->symbol = $lhs->symbol;
      
      if ($node->prefix === false)
        $this->value = $lhs;
      
      $node->expr->value = $this->value;
      goto out;
    }  
    
    $this->value = new Value(VAL_KIND_UNKNOWN);
    out:
  }
  
  protected function reduce_update_expr($lhs, $op, $loc)
  {
    $cval = 0;
    $kind = $lhs->kind;
    
    if ($kind !== VAL_KIND_DNUM &&
        $kind !== VAL_KIND_LNUM) {
      $cval = (int) $lhs->value;
      $kind = VAL_KIND_LNUM;
    } else
      $cval = $lhs->value;
      
    if ($op->type === T_INC)
      $cval += 1;
    else
      $cval -= 1;
    
    $this->value = new Value($kind, $cval);
  }
  
  protected function visit_assign_expr($node) 
  {
    $kind = $node->left->kind();
    $fail = false;
    
    if ($kind !== 'name' && $kind !== 'member_expr') {
      $this->error_at($node->loc, ERR_ERROR, 'invalid assigment left-hand-side');
      $fail = true;
    }
    
    array_push($this->astack, [ $this->access, $this->accloc ]);
    $this->access = self::ACC_WRITE;
    $this->accloc = $node->loc;
    
    $lhs = $this->handle_expr($node->left);
    $rhs = $this->handle_expr($node->right);
    
    if (!$fail && $lhs->kind !== VAL_KIND_UNKNOWN) {
      if ($lhs->symbol !== null) {
        $sym = $lhs->symbol;
        
        if ($sym->flags & SYM_FLAG_CONST) {
          if (!$this->allow_const_assign) {
            $this->error_at($node->loc, ERR_ERROR, 'assigment of constant `%s` is not allowed here', $sym->name);
            goto unk;  
          }
          
          if ($sym->value->kind !== VAL_KIND_EMPTY) {
            $this->error_at($node->loc, ERR_ERROR, 're-assignment of read-only symbol `%s`', $sym->name);
            goto unk;  
          }
          
          if ($sym->branch !== $this->branch) {
            $this->error_at($node->loc, ERR_ERROR, 'assigment of constant symbol `%s` must be in the same branch', $sym->name);
            $this->error_at($sym->loc, ERR_INFO, 'declaration was here');
            goto unk;
          }
          
          if ($rhs->kind === VAL_KIND_UNKNOWN) {
            $this->error_at($node->loc, ERR_ERROR, 'assingments to constant symbols must be computable at compile-time');
            goto unk;
          }
        }
        
        // assign it!
        $this->value = $sym->value = $rhs;
        $rhs->symbol = $sym;
        $sym->writes++;       
        goto out;
      } else {
        // the left-hand-side gets thrown-away anyway
        // possible something like { foo: 1 }.foo = 2 
        $this->value = $node->value = $rhs;
        goto out;
      }
    } elseif ($lhs && $lhs->symbol) {
      // avoid "maybe-uninitialized" warning
      $lhs->symbol->value = $this->value = $rhs;
      $rhs->symbol = $lhs->symbol;
      $lhs->symbol->writes++;
      goto out;
    }
    
    unk:
    $this->value = new Value(VAL_KIND_UNKNOWN);
    
    out:
    list ($this->access, $this->accloc) = array_pop($this->astack);
  }
  
  protected function visit_member_expr($node) 
  {
    array_push($this->astack, [ $this->access, $this->accloc ]);
    $this->access = self::ACC_READ;
    $this->accloc = $node->loc;
    
    $lhs = $this->handle_expr($node->obj);
    $rhs = null;
    
    list ($this->access, $this->accloc) = array_pop($this->astack);
    
    if ($node->computed) {
      $rhs = $this->handle_expr($node->member);
      
      if ($rhs->kind === VAL_KIND_UNKNOWN)
        goto unk;
    }
    
    /*  
      member expressions can only be reduced if:
        - the value is not bound to a symbol 
        - or the symbol is declared as constant 
    */
    if ((!$lhs->symbol || ($lhs->symbol->flags & SYM_FLAG_CONST)) && $lhs->kind !== VAL_KIND_UNKNOWN) {
      // try to reduce it
      $this->reduce_member_expr($lhs, $rhs, $node->member, $node->prop, $node->loc);
      goto out;    
    }
    
    unk:
    $this->value = new Value(VAL_KIND_UNKNOWN);
    
    out:
  }
  
  protected function reduce_member_expr($lhs, $rhs, $member, $prop, $loc)
  {       
    if ($rhs !== null) {
      // the right-hand-side was handled before
      // accessing it direclty
      $key = $rhs->value;  
    } else
      // use the member
      $key = $member->value;
      
    if ($lhs->kind === VAL_KIND_EMPTY || $lhs->kind === VAL_KIND_NULL) {
      $ref = $lhs->symbol->name;
      $this->error_at($loc, ERR_WARN, 'access to (maybe) uninitialized symbol `%s`', $ref);      
      goto unk;
    }
    
    if ($lhs->symbol && $lhs->symbol->flags & SYM_FLAG_CONST && $this->access === self::ACC_WRITE) {
      $this->error_at($this->accloc, ERR_ERROR, 'write-access to constant member `%s`', $key);
      goto unk;
    }
    
    if ($prop) {
      // property-access
      $sym = null;
      
      if ($lhs->kind === VAL_KIND_CLASS || 
          $lhs->kind === VAL_KIND_TRAIT ||
          $lhs->kind === VAL_KIND_IFACE) {
        // values like this must be bound to a symbol
        assert($lhs->symbol !== null);
        
        // fetch member
        $sym = $lhs->symbol->mst->get((string) $key, false);
        
        if (!$sym) {
          // symbol not found
          $this->error_at($loc, ERR_ERROR, 'access to undefined property `%s` of `%s`', $key, $lhs->symbol->name);
          goto unk;  
        }
        
        // if symbol is constant and it has a value
        // the value can be unknown though
        // TODO: implement static access
        if (($sym->flags & SYM_FLAG_CONST) && $sym->value->kind !== VAL_KIND_EMPTY) {
          $this->value = Value::from($sym);
          $sym->reads++;
        } else {
          // accessing non-static/non-const or empty property
          if (!($sym->flags & SYM_FLAG_STATIC))
            $this->error_at($loc, ERR_ERROR, 'access to non-static/const property `%s` of `%s` in invalid context', $key, $lhs->symbol->name);
          goto unk;
        }        
      } else {
        $key = (string) $key;
        
        if ($lhs->kind !== VAL_KIND_OBJ) {
          $ref = $lhs->symbol ? "symbol `{$lhs->symbol->name}`" : '(unknown)';
          $this->error_at($loc, ERR_ERROR, 'trying to get property "%s" of non-object %s', $key, $ref);
          $this->error_at($loc, ERR_INFO, 'left-hand-side is %s', $lhs);          
          goto unk;
        }
        
        if (!isset ($lhs->value[$key])) {
          $this->error_at($loc, ERR_WARN, 'access to undefined property "%s"', $key);
          $lhs->value[$key] = new Value(VAL_KIND_UNKNOWN);
        }
        
        $this->value = $lhs->value[$key];
      }
    } else {
      // array-access
      if (!is_int($key) && !ctype_digit($key)) {
        $this->error_at($loc, ERR_ERROR, 'invalid array-index %s', $key);
        goto unk;
      }
      
      $key = (int) $key;
      
      if ($lhs->kind !== VAL_KIND_ARR) {
        $this->error_at($loc, ERR_ERROR, 'trying to access index %s of non-array', $key);
        goto unk;
      }
            
      if (!isset ($lhs->value[$key])) {
        $this->error_at($loc, ERR_ERROR, 'access to undefined index %s', $key);
        goto unk;
      }
        
      $this->value = $lhs->value[$key];     
    }
    
    goto out;
    
    unk:
    $this->value = new Value(VAL_KIND_UNKNOWN);
    
    out:
  }
  
  protected function visit_cond_expr($node) 
  {
    $cnd = $this->handle_expr($node->test);
    $lhs = $node->then !== null ? $this->handle_expr($node->then) : null;
    $rhs = $this->handle_expr($node->els);
    
    if ($cnd->kind !== VAL_KIND_UNKNOWN) {
      $node->test->value = $cnd;
      
      if ($lhs === null || $lhs->kind !== VAL_KIND_UNKNOWN) {
        if ($lhs !== null) $node->then->value = $lhs;
        
        if ($rhs->kind !== VAL_KIND_UNKNOWN) {
          $node->els->value = $rhs;
          
          $this->reduce_cond_expr($cnd, $lhs, $rhs, $node->loc);
          goto out;
        }
      }
    }
    
    $this->value = new Value(VAL_KIND_UNKNOWN);
    
    out:
    $node->value = $this->value;
  }
  
  protected function reduce_cond_expr($cnd, $lhs, $rhs, $loc)
  {
    if ((bool) $cnd->value)
      $cval = $lhs === null ? $cnd : $lhs;
    else
      $cval = $rhs;
    
    $this->value = $cval;
  }
  
  protected function visit_call_expr($node) 
  {
    array_push($this->astack, [ $this->access, $this->accloc ]);
    $this->access = self::ACC_READ;
    $this->accloc = $node->loc;
    
    $lhs = $this->handle_expr($node->callee);
    
    list ($this->access, $this->accloc) = array_pop($this->astack);
    
    if ($lhs->kind !== VAL_KIND_UNKNOWN) {
      // indirect call
      if ($lhs->kind !== VAL_KIND_FN)
        if ($lhs->symbol !== null)
          $this->error_at($node->callee->loc, ERR_ERROR, 'symbol `%s` is not callable', $lhs->symbol->name);
        else
          $this->error_at($node->callee->loc, ERR_ERROR, 'value is not callable');
            
      $node->callee->value = $lhs;
      
    } elseif ($lhs->symbol !== null) {
      // direct call
      $sym = $lhs->symbol;
      
      while ($sym->kind > SYM_REF_DIVIDER)
        $sym = $sym->symbol;
      
      if ($sym->kind !== SYM_KIND_FN && $sym->value->kind !== VAL_KIND_FN)
        $this->error_at($node->callee->loc, ERR_ERROR, 'symbol `%s` is not callable', $lhs->symbol->name);
      else
        $sym->calls++;
    }
    
    if ($node->args !== null)
      foreach ($node->args as $arg)
        if ($arg->kind() === 'rest_arg')
          $this->handle_expr($arg->expr); 
        else
          $this->handle_expr($arg);
    
    $this->value = new Value(VAL_KIND_UNKNOWN);
  }
  
  protected function visit_yield_expr($node) 
  {
    if ($this->infn < 1)
      $this->error_at($node->loc, ERR_ERROR, 'yield outside of function');
    
    $this->handle_expr($node->expr);
  }
  
  protected function visit_unary_expr($node) 
  {
    $rhs = $this->handle_expr($node->expr);
    
    if ($rhs->kind !== VAL_KIND_UNKNOWN) {
      $node->expr->value = $rhs;
      $this->reduce_unary_expr($rhs, $node->op, $node->loc);
      goto out;
    }  
    
    $this->value = new Value(VAL_KIND_UNKNOWN);
    out:
  }
  
  protected function reduce_unary_expr($rhs, $op, $loc)
  {
    if ($op->type === T_PLUS ||
        $op->type === T_MINUS) 
    {
      $rval = $cval = 0;
      $kind = 0;
      
      // cast to float
      if ($rhs->kind !== VAL_KIND_DNUM && 
          $rhs->kind !== VAL_KIND_LNUM) {
        $rval = (float) $rhs->value;
        $kind = VAL_KIND_DNUM;
      } else {
        $rval = $rhs->value;
        $kind = $rhs->kind;
      }
      
      switch ($op->type) {
        case T_PLUS:
          $cval = +$rval;
          break;
        case T_MINUS:
          $cval = -$rval;
          break;
      }
      
      $this->value = new Value($kind, $cval);
    }
    
    elseif ($op->type === T_BIT_NOT) {
      $rval = $cval = 0;
      
      if ($rhs->kind !== VAL_KIND_LNUM)
        $rval = (int) $rhs->value;
      else
        $rval = $rhs->value;
      
      $cval = ~$rval;
      $this->value = new Value(VAL_KIND_LNUM, $cval);
    }
    
    elseif ($op->type === T_EXCL) {
      $rval = $cval = false;
      
      if ($rhs->kind !== VAL_KIND_BOOL)
        $rval = (bool) $rhs->value;
      else
        $rval = $rhs->value;
      
      $cval = !$rval;
      $this->value = new Value(VAL_KIND_BOOL, $cval);
    }
    
    else {
      print "unknown unary operator {$op->value} ({$op->type})";
      assert(0);
    }
  }
  
  protected function visit_new_expr($node) 
  {
    // TODO: implement reduce (value -> VAL_KIND_NEW)
    $this->handle_expr($node->name);
    
    if ($node->args !== null)
      $this->handle_expr($node->args);
    
    $this->value = new Value(VAL_KIND_UNKNOWN);
  }
  
  protected function visit_del_expr($node) 
  {
    // TODO: what does del .. return? int? depends on the php-version
    $this->handle_expr($node->id);
    
    $this->value = new Value(VAL_KIND_UNKNOWN);
  }
  
  protected function visit_print_expr($node) 
  {
    $this->handle_expr($node->expr);
    
    // the return-valud of <print> is always 1
    $this->value = new Value(VAL_KIND_LNUM, 1);
  }
  
  protected function visit_lnum_lit($node) 
  {
    $this->value = new Value(VAL_KIND_LNUM, $node->value);  
  }
  
  protected function visit_dnum_lit($node) 
  {
    $this->value = new Value(VAL_KIND_DNUM, $node->value);  
  }
  
  protected function visit_snum_lit($node) 
  {
    // TODO: numbers with suffix MUST be reduced at compile-time!
    $this->value = new Value(VAL_KIND_UNKNOWN);
  }
  
  protected function visit_regexp_lit($node) 
  {
    $this->value = new Value(VAL_KIND_REGEXP, $node->value);
  }
  
  protected function visit_arr_lit($node) 
  {
    // arrays only get a value if all items are constant
    $arr = [];
    $unk = false;
    
    if ($node->items !== null) {
      foreach ($node->items as $item) {
        $val = $this->handle_expr($item);
        
        if ($val->kind === VAL_KIND_UNKNOWN)
          $unk = true;
        
        if (!$unk) $arr[] = $val;
      }  
    }
    
    if ($unk) 
      $this->value = new Value(VAL_KIND_UNKNOWN);
    else
      $this->value = new Value(VAL_KIND_ARR, $arr);
  }
  
  protected function visit_obj_lit($node) 
  {
    // objects only get a value of all pairs are constant
    $obj = []; // jep, assoc
    $unk = false;
    
    if ($node->pairs !== null) {
      foreach ($node->pairs as $pair) {
        $kind = $pair->key->kind();
              
        if ($kind === 'ident' || $kind === 'str_lit')
          $key = $pair->key->value;
        else {
          $val = $this->handle_expr($pair->key);

          if ($val->kind === VAL_KIND_UNKNOWN)
            $unk = true;
          else
            $key = (string) $val->value;
        }
        
        $val = $this->handle_expr($pair->value);
        
        if ($val->kind === VAL_KIND_UNKNOWN)
          $unk = true;
        
        if (!$unk) $res[$key] = $val;
      }
    }
    
    if ($unk)
      $this->value = new Value(VAL_KIND_UNKNOWN);
    else
      $this->value = new Value(VAL_KIND_OBJ, $res);
  }
  
  /* TODO: needs refactoring */
  protected function visit_name($name) 
  {    
    $bid = ident_to_str($name->base);    
    $scp = $name->root ? $this->ctx->get_root() : $this->scope; 
    $sym = $scp->get($bid, false, null, true);
    $mod = null;
    
    // its not a symbol in the current scope
    if ($sym === null)
      goto err;
    
    switch ($sym->kind) {
      // symbols   
      case SYM_KIND_CLASS:
      case SYM_KIND_TRAIT:
      case SYM_KIND_IFACE:
      case SYM_KIND_VAR:
      case SYM_KIND_FN:
      case REF_KIND_CLASS:
      case REF_KIND_TRAIT:
      case REF_KIND_IFACE:
      case REF_KIND_VAR:
      case REF_KIND_FN:
        break;
      
      // references
      case SYM_KIND_MODULE:
      case REF_KIND_MODULE:
        if (empty ($name->parts))
          // a module can not be referenced
          goto mod;
        
        $mod = $sym->module;
        goto lcm;
        
      default:
        print 'what? ' . $sym->kind;
        exit;
    }
        
    // best case: no more parts
    if (empty ($name->parts))     
      goto sym;
    
    /* ------------------------------------ */
    /* symbol lookup */
    
    if ($sym->kind !== REF_KIND_MODULE &&
        $sym->kind !== SYM_KIND_MODULE) {
      $this->error_at($name->loc, ERR_ERROR, 'symbol `%s` used as module', $sym->name);
      goto unk;
    }
    
    $mod = $sym->module;
    
    /* ------------------------------------ */
    /* symbol lookup in module */
    
    lcm:
    $arr = name_to_stra($name);
    $lst = array_pop($arr);
    
    // ignore base
    array_shift($arr);
    
    if (!empty ($arr)) {
      $res = $mod;
      $trk = [];
      
      foreach ($arr as $item) {
        // do not walk
        $res = $res->get($item, false, null, false);
        array_push($trk, $item);
        
        if ($res === null) {
          $this->error_at($name->loc, ERR_ERROR, 'access to undefined sub-module `%s` of `%s`', implode('::', $trk), $mod->name);
          goto unk;
        }
        
        if ($res->kind !== REF_KIND_MODULE &&
            $res->kind !== SYM_KIND_MODULE) {
          $this->error_at($name->loc, ERR_ERROR, 'symbol `%s` used as module', implode('::', $trk));
          goto unk;
        }
        
        // use the reference path from now on for proper error-messages
        if ($res->kind === REF_KIND_MODULE)
          $trk = $res->module->path(false);
        
        $res = $res->module;
      }
      
      $mod = $res;
    }
    
    if ($mod->has_child($lst))
      // module can not be referenced
      goto mod;
    
    $sym = $mod->get($lst);
    
    if ($sym === null)
      goto err;
      
    if ($sym->kind === REF_KIND_MODULE ||
        $sym->kind === SYM_KIND_MODULE)
        // module can not be a referenced
        goto mod;
    
    if ($sym->kind > SYM_REF_DIVIDER) 
      $sym = $sym->symbol;
    
    sym:    
    /* allow NULL here */
    if ($this->access === self::ACC_READ && 
        $sym->kind === SYM_KIND_VAR && $sym->value->kind === VAL_KIND_EMPTY)
      $this->error_at($name->loc, ERR_WARN, 'access to (maybe) uninitialized symbol `%s`', name_to_str($name));
    
    $sym->reads++;
    if ($sym->flags & SYM_FLAG_CONST) {      
      // do not use values from non-const symbols
      $this->value = Value::from($sym);
      goto out;
    }
    
    goto unk;
    
    mod:
    $path = name_to_str($name);
    if (isset ($sym) && $sym->kind > SYM_REF_DIVIDER) {
      $this->error_at($name->loc, ERR_ERROR, 'module-ref `%s` used as value', path_to_str($sym->path));
      $this->error_at($name->loc, ERR_ERROR, 'used via `%s`', $path);
      $this->error_at($sym->loc, ERR_INFO, 'declared as reference here');
    } else
      $this->error_at($name->loc, ERR_ERROR, 'module `%s` used as value', $path);  

    goto unk;
    
    err:    
    $this->error_at($name->loc, ERR_ERROR, 'access to undefined symbol `%s`', name_to_str($name));
    
    unk:
    $this->value = new Value(VAL_KIND_UNKNOWN);
    $this->value->symbol = $sym;
    
    out:
  }
  
  /* same rules as visit_name, but without the fancy lookup */
  protected function visit_ident($node) 
  {
    $sid = ident_to_str($node);
    $sym = $this->scope->get($sid, false, null, true);
    
    if ($sym && ($sym->kind === REF_KIND_MODULE ||
                 $sym->kind === SYM_KIND_MODULE)) {
      $this->error_at($name->loc, ERR_ERROR, 'module `%s` used as value', $sid); 
      goto unk;
    }
    
    if (!$sym) {
      $this->error_at($node->loc, ERR_ERROR, 'access to undefined symbol `%s`', $sid);
      goto unk;
    }
    
    /* allow NULL here */
    if ($this->access === self::ACC_READ && 
        $sym->kind === SYM_KIND_VAR && $sym->value->kind === VAL_KIND_EMPTY)
      $this->error_at($node->loc, ERR_WARN, 'access to (maybe) uninitialized symbol `%s`', $sid);
    
    $sym->reads++;
    if ($sym->flags & SYM_FLAG_CONST) {      
      // do not use values from non-const symbols
      $this->value = Value::from($sym);
      goto out;
    }
    
    unk:
    $this->value = new Value(VAL_KIND_UNKNOWN);
    $this->value->symbol = $sym;
    
    out:
  }
  
  protected function visit_this_expr($node) 
  {
    // TODO: this must be reducible! check in wich class we are atm
    $this->value = new Value(VAL_KIND_UNKNOWN);
  }
  
  protected function visit_super_expr($n) 
  {
    // TODO: see <this>
    $this->value = new Value(VAL_KIND_UNKNOWN);
  }
  
  protected function visit_null_lit($node) 
  {
    $this->value = new Value(VAL_KIND_NULL);
  }
  
  protected function visit_true_lit($node) 
  {
    $this->value = new Value(VAL_KIND_BOOL, true);  
  }
  
  protected function visit_false_lit($node) 
  {
    $this->value = new Value(VAL_KIND_BOOL, false);  
  }
  
  protected function visit_engine_const($node) 
  {
    // TODO: reduce if possible
    $this->value = new Value(VAL_KIND_UNKNOWN);
  }
  
  protected function visit_str_lit($node) 
  {
    $this->value = new Value(VAL_KIND_STR, $node->value);
  }
  
  protected function visit_type_id($node)
  {
    $this->value = new Value(VAL_KIND_TYPE, $node->type); 
  }
    
  /* ------------------------------------ */
    
  public function walk_branch($node)
  {
    array_push($this->sstack, $this->scope);
    $this->scope = new Branch($this->scope);
        
    array_push($this->bstack, $this->branch);
    $this->branch = ++self::$branch_uid;
    
    $this->walk_some($node);
    
    $this->scope = array_pop($this->sstack);
    $this->branch = array_pop($this->bstack);
  }
    
  public function walk_scoped_branch($node)
  {
    array_push($this->sstack, $this->scope);
    $this->scope = new Scope($this->scope);
        
    $this->walk_branch($node);
    
    $this->scope = array_pop($this->sstack);
  } 
  
  /* ------------------------------------ */
  
  public function enter_fn($sym, $node)
  {
    ++$this->infn;
    
    array_push($this->sstack, $this->scope);
    $node->scope = new FnScope($sym, $this->scope);
    $this->scope = $node->scope;
        
    array_push($this->fstack, $this->flags);
    $this->flags = SYM_FLAG_NONE;
    
    array_push($this->lstack, $this->labels);
    $this->labels = [];
    
    array_push($this->gstack, $this->gotos);
    $this->gotos = [];
  }
  
  public function leave_fn()
  {
    --$this->infn;
    
    $this->scope = array_pop($this->sstack);
    $this->flags = array_pop($this->fstack);
    
    $this->check_jumps();
    
    $this->labels = array_pop($this->lstack);
    $this->gotos = array_pop($this->gstack);
  }
  
  /* ------------------------------------ */
  
  protected function check_jumps()
  {
    static $seen_loop_info = false;
    
    foreach ($this->gotos as $lid => $gotos) {      
      foreach ($gotos as $goto) {
        if ($goto->resolved === true)
          continue;
          
        $label = isset ($this->labels[$lid]) ? $this->labels[$lid] : null;
        
        if ($label === null)
          $this->error_at($goto->loc, ERR_ERROR, 'goto to undefined label `%s`', $goto->id);
        else {
          $this->error_at($goto->loc, ERR_ERROR, 'goto to unreachable label `%s`', $goto->id);
          $this->error_at($label->loc, ERR_INFO, 'label was defined here');
          
          if (!$seen_loop_info) {
            $seen_loop_info = true;
            $this->error_at($goto->loc, ERR_INFO, 'it is not possible to jump inside a loop');
          }
        }
      }
    }
  }
  
  /* ------------------------------------ */
  
  public function enter_loop()
  {
    ++$this->inloop;
    
    // labels inside a loop can not resolve gotos outside
    array_push($this->gstack, $this->gotos);
    $this->gotos = [];
    
    // hide outside labels
    array_push($this->lstack, $this->lframe);
    $this->lframe = [];
  }
  
  public function leave_loop()
  {
    --$this->inloop;
    
    // gotos inside a loop can jump to the outside world
    // merge new unresolved gotos
    $lgotos = $this->gotos;
    $this->gotos = array_pop($this->gstack);
    
    foreach ($lgotos as $lid => $gotos)
      foreach ($gotos as $goto)
        if ($goto->resolved === false)
          $this->gotos[$lid][] = $goto;        
      
    // mark all labels inside the loop as unreachable
    foreach ($this->lframe as $label)
      $label->reachable = false;
    
    // restore label-frame
    $this->lframe = array_pop($this->lstack);
  }
    
  /* ------------------------------------ */
  
  public function reveal_collision($rmod, $name)
  {
    $err  = 'could not enter module `%s` because its name ';
    $err .= 'collides with another symbol in its parent module';
    
    $this->error_at($name->loc, ERR_ERROR, $err, name_to_str($name));
    
    $last = null;
    foreach (name_to_stra($name) as $part) {
      $last = $part;
      
      if (!$rmod->has_child($part)) 
        break;
      
      $rmod = $rmod->get_child($part);
    }
    
    if ($last)
      $this->error_at($rmod->get($last)->loc, ERR_INFO, 'declaration was here');
  }
  
  /* ------------------------------------ */    
    
  /**
   * error handler
   * 
   */
  public function error_at()
  {
    $args = func_get_args();    
    $loc = array_shift($args);
    $lvl = array_shift($args);
    $msg = array_shift($args);
    
    $this->ctx->verror_at($loc, COM_ANL, $lvl, $msg, $args);
  }
}
