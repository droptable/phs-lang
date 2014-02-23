<?php

namespace phs;

require_once 'utils.php';
require_once 'walker.php';
require_once 'symbol.php';

use phs\ast\Unit;
use phs\ast\Name;

class Analyzer extends Walker 
{
  // context
  private $ctx;
  
  // current scope
  private $scope;
  
  // scope stack
  private $sstack;
  
  // module (if any)
  private $module;
  
  // module stack
  private $mstack;
  
  /**
   * constructor
   * 
   * @param Context $ctx
   */
  public function __construct(Context $ctx)
  {
    parent::__construct($ctx);
    $this->ctx = $ctx;
  }
  
  /**
   * analyze a unit
   * 
   * @param  Unit   $unit
   */
  public function analyze(Unit $unit)
  {
    $this->scope = $this->ctx->get_scope();
    $this->sstack = [];
    $this->module = null;
    $this->mstack = [];
    
    $this->walk($unit);
  }
  
  /* ------------------------------------ */
  
  /**
   * enter a new scope
   * 
   * @param  boolean $ext extend (make current scope the parent)
   */
  protected function enter_scope($ext = true)
  {
    array_push($this->sstack, $this->scope);
    $new_scope = new Scope($ext ? $this->scope : null);
    $this->scope = $new_scope;
  }
  
  /**
   * leave the current scope
   * 
   */
  protected function leave_scope()
  {
    $this->scope = array_pop($this->sstack);
    assert($this->scope instanceof Scope);
  }
  
  /* ------------------------------------ */
  
  /**
   * handles a import (use)
   * 
   * @param  Node $item
   * @return boolean
   */
  protected function handle_import(Module $base, $item)
  {    
    switch ($item->kind()) {
      case 'name':
        return $this->fetch_import($base, $item, true);
      case 'use_alias':
        return $this->fetch_import($base, $item->name, true, ident_to_str($item->alias));
      case 'use_unpack':
        if (!$this->fetch_import($item->base, false))
          return false;
        
        $base = $base->fetch(name_to_stra($item->base));
        
        foreach ($item->items as $item)
          if (!$this->handle_import($base, $item))
            return false;
          
        return true;
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
    
    if ($parts)
      $base = $base->fetch($parts);
    
    if ($base->has_child($last)) {
      // import a module
      if (!$add) return true; 
      
      // add symbol to scope
      $sym = new ModuleRef($id, SYM_FLAG_NONE, $base, $name, $name->loc);
      return $this->add_symbol($id, $sym);
    }
    
    if (!$base->has($last)) {
      // unknown import, this is a job for the resolver
      $this->error_at($name->loc, ERR_WARN, 'unknown import `%s` from module %s', $last, $base->path());
      
      // import a module
      if (!$add) return true;
      
      $this->error_at($name->loc, ERR_INFO, 'assuming <module> (default assumption)');
      
      // add symbol to scope
      $sym = new ModuleRef($id, SYM_FLAG_WEAK, $base, $name, $name->loc);
      return $this->add_symbol($id, $sym);
    }
    
    if (!$add) return true;
    $sym = $base->get($last);
    return $this->add_symbol($id, $sym);
  }
  
  /* ------------------------------------ */
  
  protected function add_symbol($id, $sym)
  {
    # print "adding symbol $id\n";
    $cur = $this->scope->get($id, false);
    
    if (!$cur) {
      # print "no previous entry, adding it!\n";
      // simply add it
      $this->scope->add($id, $sym);
      return true;
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
      $this->error_at($sym->loc, ERR_ERROR, '%s `%s` hides a final symbol', $sym->kind, $sym->name);
      $this->error_at($cur->loc, ERR_ERROR, 'previous declaration was here');
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
      $this->error_at($sym->loc, ERR_ERROR, '%s `%s` overrides a constant symbol', $sym->kind, $sym->name);
      $this->error_at($cur->loc, ERR_ERROR, 'previous declaration was here');
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
      // switch to global scope and root module
      array_push($this->mstack, $this->module);
      array_push($this->sstack, $this->scope);
      $this->module = $this->ctx->get_module();
      $this->scope = $this->ctx->get_scope();
    } else {
      if ($name->root || !$this->module)
        // use root module
        $rmod = $this->ctx->get_module();
      else
        // use current module
        $rmod = $this->module;
            
      $rmod = $rmod->fetch(name_to_stra($name)); 
      
      array_push($this->mstack, $this->module);
      array_push($this->sstack, $this->scope);
      $this->module = $rmod;
      $this->scope = $rmod;         
    }
  }
  
  protected function leave_module($node) 
  {
    // back to prev module
    $this->module = array_pop($this->mstack);
    
    // back to prev scope
    $this->scope = array_pop($this->sstack);
  }
  
  protected function visit_use_decl($node)
  {
    // this function is recursive
    $base = $this->ctx->get_module();
    return $this->handle_import($base, $node->item);
  }
  
  protected function visit_enum_decl($node) 
  {
    $flags = SYM_FLAG_CONST | SYM_FLAG_FINAL;
    
    if ($node->mods) {
      if (!$this->check_mods($node->mods, false, false))
        return $this->drop();
      
      $flags = mods_to_symflags($node->mods, $flags);
    }
    
    foreach ($node->members as $member) {
      if ($member->dest->kind() !== 'ident') {
        $this->error_at($member->loc, ERR_ERROR, 'destructors are not allowed in enum-declarations');
        continue; 
      }
      
      $id = ident_to_str($member->dest);
      
      if ($member->init !== null) {
        $init = $member->init;
        
        // if its a simple value, create a ValueSym
        // otherwise create a ExprSym
        switch ($init->kind()) {
          case 'lnum_lit':
          case 'dnum_lit':
          case 'snum_lit':
          case 'null_lit':
          case 'true_lit':
          case 'false_lit':
          case 'str_lit':
          case 'engine_const':
            $sym = new ValueSym($id, $flags, $init, $member->loc);
            break;
          default:
            // must be reducible in a later state
            $sym = new ExprSym($id, $flags, $init, $member->loc);
        }
      } else
        $sym = new EmptySym($id, $flags, $member->loc);
      
      $this->add_symbol($id, $sym);
    }
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
      
    $base = null;
    
    if ($node->ext !== null) {
      // the base class can be a name, so use the full blown module-lookup
      exit('not implemented');
    }
        
    $cid = ident_to_str($node->id);
    $sym = new ClassSym($cid, $flags, $node->loc);
    
    if (!$this->add_symbol($cid, $sym))
      return $this->drop();
    
    array_push($this->sstack, $this->scope);
    $node->scope = new ClassScope($sym, $this->scope);
    $this->scope = $node->scope;
  }
  
  protected function leave_class_decl($node)
  {
    // back to prev scope
    $this->scope = array_pop($this->sstack);
  }
  
  protected function enter_fn_decl($node)
  {
    $flags = SYM_FLAG_NONE;
    $apppf = false;
    
    if ($this->scope instanceof ClassScope)
      $apppf = true; // allow public/private/protected flags
    
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
    
    if (!($flags & SYM_FLAG_EXTERN) && $node->body === null)
      $flags |= SYM_FLAG_INCOMPLETE;
    
    $fid = ident_to_str($node->id);
    $sym = new FnSym($fid, $flags, $node->loc);
    
    if (!$this->add_symbol($fid, $sym))
      return $this->drop();
    
    array_push($this->sstack, $this->scope);
    $node->scope = new FnScope($sym, $this->scope);
    $this->scope = $node->scope;
  }
  
  protected function leave_fn_decl($node)
  {
    // back to prev scope
    $this->scope = array_pop($this->sstack);
  }
  
  /* ------------------------------------ */
  
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
   * check class-members
   * 
   * @param  array $members
   * @param  int $flags
   * @param  boolean $ext
   * @return boolean
   */
  protected function check_class_members($members, &$flags, $ext)
  {
    foreach ($members as $mem) {
      $kind = $mem->kind();
      
      if ($kind === 'fn_decl') {
        // extern + function-body is a no-no
        if ($ext && $mem->body !== null) {
          $this->error_at($mem->body->loc, ERR_ERROR, 'extern class-method can not have a body');
          return false;
        }
        
        // mark the class itself as abstract
        if (!$ext && $mem->body === null)
          $flags |= SYM_FLAG_ABSTRACT;
        
      } 
      
      elseif ($kind === 'nested_mods')
        return $this->check_class_members($mem->members, $flags, $ext);
      
      elseif ($ext) {
        $this->error_at($mem->loc, ERR_ERROR, 'invalid member in extern class');
        $this->error_at($mem->loc, ERR_INFO, 'only abstract functions are allowed');
        return false;
      }
    }
    
    return true;
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
