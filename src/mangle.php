<?php

namespace phs;

use phs\ast\Unit;
use phs\ast\Ident;

/**
 *   T: temporary variable (not used here)
 *   L: local variable
 *   M: variable inside a module
 *   U: private symbol
 *   N: separator
 *   Z: general prefix
 */

class MangleTask extends AutoVisitor implements Task
{
  // @var Session
  private $sess;
  
  // PHP reserved words
  // see http://php.net/manual/en/reserved.keywords.php
  private static $rids = [
    '__halt_compiler', 'abstract', 'and', 'array', 'as',
    'break', 'callable', 'case', 'catch', 'class',
    'clone', 'const', 'continue', 'declare', 'default',
    'die', 'do', 'echo', 'else', 'elseif', 'empty',
    'enddeclare', 'endfor', 'endforeach', 'endif',
    'endswitch', 'endwhile', 'eval', 'exit', 'extends',
    'final', 'finally', 'for', 'foreach', 'function',
    'global', 'goto', 'if', 'implements', 'include',
    'include_once', 'instanceof', 'insteadof', 'interface',
    'isset', 'list', 'namespace', 'new', 'or', 'print',
    'private', 'protected', 'public', 'require', 'require_once',
    'return', 'static', 'switch', 'throw', 'trait', 'try',
    'unset', 'use', 'var', 'while', 'xor', 'yield',      
    '__class__', '__dir__', '__file__', '__function__',
    '__line__', '__method__', '__namespace__', '__trait__'   
  ];
  
  // @var int  nesting-level
  private $nest = 0;
  
  // @var int  module-level
  private $imod = 0;
  
  // @var ModuleScope  module
  private $cmod;
  
  // @var int fn-decl-level
  private $ifnd = 0;
  
  /**
   * constructor
   *
   * @param Session $sess
   */
  public function __construct(Session $sess)
  {
    parent::__construct();
    $this->sess = $sess;
  }
  
  /**
   * start
   *
   * @param  Unit   $unit
   */
  public function run(Unit $unit)
  {
    $this->visit($unit);
  }
  
  /* ------------------------------------ */
  
  /**
   * handle a scope
   *
   * @param  Scope  $scope
   */
  public function handle(Scope $scope)
  {    
    foreach ($scope->iter() as $sym)
      $this->mangle($sym);
  }
  
  /**
   * mangles a symbol-name
   *
   * @param  Symbol $sym
   */
  public function mangle(Symbol $sym)
  {    
    $id = $sym->id;
    
    // temporary name or unsafe
    if (substr($id, 0, 1) === '~' ||
        ($sym->flags & SYM_FLAG_UNSAFE))
      goto out;
    
    // external symbol, defuse it but don't mangle
    if ($sym->flags & SYM_FLAG_EXTERN)
      goto upd;
    
    $pf = [];
    
    if (!($sym->flags & SYM_FLAG_UNSAFE)) {
      // handle vars
      if ($sym instanceof VarSymbol &&
       // !is_really_const($sym)
          !(($sym->flags & SYM_FLAG_CONST) && 
             $sym->value->is_const() && !$this->nest)) {
        // M: module variable           
        if ($this->imod && !$this->nest)
          $pf[] = 'M' . path_to_uid($this->cmod->path());
        // L: local variable
        else
          $pf[] = 'L' . $sym->scope->uid;      
      // handle fn-expr or nested fn-decl
      } elseif ($sym instanceof FnSymbol && 
                ($sym->expr || $this->nest))
        // rewrite as local-var
        $pf[] = 'L' . $sym->scope->uid;
      
      // private unit-global symbol
      if ((!$this->imod && !$this->nest) &&
          $sym->flags & SYM_FLAG_PRIVATE)
        $pf[] = 'U' . crc32_str($sym->loc->file);
      
      // join prefix(es) together
      if (!empty ($pf))
        $id = '_' . implode('N', $pf) . 'Z' . strlen($id) . $id;
    }
    
    upd:
    $id = $this->defuse($id);
    $sym->id = $id;
    
    out:
    return;
  }
  
  /**
   * creates a safe-to-use ident
   *
   * @param  string  $id
   */
  public function defuse($id)
  {
    if (in_array(strtolower($id), self::$rids))
      // a single underscore at the end should do the job
      $id .= '_';
    
    return $id;
  }
  /* ------------------------------------ */
  
  /**
   * Visitor#visit_unit()
   *
   * @param  Node $node
   */
  public function visit_unit($node)
  {
    $this->handle($node->scope);
    parent::visit_unit($node);
  }
  
  /**
   * Visitor#visit_module()
   *
   * @param  Node $node
   */
  public function visit_module($node)
  {
    $this->imod++;
    $this->cmod = $node->scope;
    $this->handle($node->scope);
    
    $cmod = $node->scope;
    do {
      $cmod->id = $this->defuse($cmod->id);
      $cmod = $cmod->prev;
    } while ($cmod instanceof ModuleScope);
        
    parent::visit_module($node);
    
    $this->imod--;
  }
  
  /**
   * Visitor#visit_trait_decl()
   *
   * @param  Node $node
   */
  public function visit_trait_decl($node)
  {
    // ignore
  }
  
  /**
   * Visitor#visit_class_decl()
   *
   * @param  Node $node
   */
  public function visit_class_decl($node)
  {
    foreach ($node->scope->iter() as $sym)
      // handle symbols inserted by traits
      if ($sym->origin) $this->visit($sym->node);
        
    parent::visit_class_decl($node);
  }
  
  /**
   * Visitor#visit_block()
   *
   * @param  Node $node
   */
  public function visit_block($node)
  {
    $this->nest++;
    $this->handle($node->scope);
    parent::visit_block($node);
    $this->nest--;
  }
    
  /**
   * Visitor#visit_ctor_decl()
   *
   * @param  Node $node
   */
  public function visit_ctor_decl($node)
  {
    $this->nest++;
    $this->handle($node->scope);
    parent::visit_ctor_decl($node);
    $this->nest--;
  }
  
  /**
   * Visitor#visit_dtor_decl()
   *
   * @param  Node $node
   */
  public function visit_dtor_decl($node)
  {
    $this->nest++;
    $this->handle($node->scope);
    parent::visit_dtor_decl($node);
    $this->nest--;
  }
  
  /**
   * Visitor#visit_getter_decl()
   *
   * @param  Node $node
   */
  public function visit_getter_decl($node)
  {
    $this->handle($node->scope);
    parent::visit_getter_decl($node);
  }
  
  /**
   * Visitor#visit_setter_expr()
   *
   * @param  Node $node
   */
  public function visit_setter_expr($node)
  {
    $this->handle($node->scope);
    parent::visit_setter_decl($node);
  }
  
  /**
   * Visitor#visit_fn_decl()
   *
   * @param  Node $node
   */
  public function visit_fn_decl($node)
  {
    $this->nest++;
    $this->handle($node->scope);
    parent::visit_fn_decl($node);
    $this->nest--;
  }
  
  /**
   * Visitor#visit_fn_expr()
   *
   * @param  Node $node
   */
  public function visit_fn_expr($node)
  {
    $this->nest++;
    $this->handle($node->scope);
    parent::visit_fn_expr($node);
    $this->nest--;
  }
  
  /**
   * Visitor#visit_for_in_stmt()
   *
   * @param  Node $node
   */
  public function visit_for_in_stmt($node)
  {
    $this->nest++;
    $this->handle($node->scope);
    parent::visit_for_in_stmt($node);
    $this->nest--;
  }
  
  /**
   * Visitor#visit_for_stmt()
   *
   * @param  Node $node
   */
  public function visit_for_stmt($node)
  {
    $this->nest++;
    $this->handle($node->scope);
    parent::visit_for_stmt($node);
    $this->nest--;
  }
  
  /**
   * Visitor#visit_member_expr()
   *
   * @param  Node $node
   */
  public function visit_member_expr($node)
  {
    if ($node->member instanceof Ident) {
      $id = ident_to_str($node->member);
      ident_set($node->member, $this->defuse($id));
    }
    
    parent::visit_member_expr($node);
  }
}
