<?php

namespace phs;

require 'writer.php';

use phs\ast\Unit;

/** code generator (for now single target PHP 5.4) */
class Generator extends Walker
{
  // compiler context
  private $ctx;
  
  // output handle
  private $out;
  
  // current scope
  private $scope;
  
  // scope stack
  private $sstack;
  
  // indent
  private $indent;
  
  // temp-id counter
  private $temp_uid = 0;
  
  public function __construct(Context $ctx)
  {
    parent::__construct($ctx);
    $this->ctx = $ctx;
  }
  
  public function generate(Unit $unit)
  {
    $this->scope = new Scope; // todo: is this required?
    $this->sstack = [];
    
    // init writer
    $this->out = new FileWriter($unit->dest);
    $this->walk($unit);
  }
  
  /* ------------------------------------ */
  
  protected function temp()
  {
    return '$_T' . ($this->temp_uid++);
  }
  
  /* ------------------------------------ */
  
  protected function emit()
  {
    foreach (func_get_args() as $data)
      $this->out->write($data);
  }
  
  protected function emitln()
  {
    foreach (func_get_args() as $data)
      $this->out->write($data);
    
    $this->out->write("\n");
    
    $tabs = str_repeat('  ', $this->indent);
    $this->out->write($tabs);
  }
  
  protected function emit_indent()
  {
    ++$this->indent;
    $this->emitln('');
  }
  
  protected function emit_dedent()
  {
    $this->indent = max(0, $this->indent - 1);
    $this->emitln('');
  }
  
  /* ------------------------------------ */
  
  protected function enter_scope(Scope $scope)
  {
    array_push($this->sstack, $this->scope);
    $this->scope = $scope;
  }
  
  protected function leave_scope()
  {
    $this->scope = array_pop($this->sstack);
  }
  
  /* ------------------------------------ */
  
  protected function emit_params($params, $captures = ' ')
  {
    $this->emit('(');
    $rest = null;
    $argc = 0;
    
    if ($params !== null)
      foreach ($params as $param) {
        $kind = $param->kind();
                       
        switch ($kind) {
          case 'rest_param':
            $rest = ident_to_str($param->id);
            break;
          case 'param':
            if ($argc > 0) $this->emit(',');
            $this->emit('$');
            $this->emit(ident_to_str($param->id));
            break;
        }
        
        $argc++;
      }
      
    $this->emit(')', $captures, '{');
    $this->emit_indent();
    
    if ($rest !== null) {
      $T0 = $this->temp();
      $T1 = $this->temp();
      
      $this->emitln('$', $rest, '=[];');
      $this->emit('for (');
      $this->emit($T0, '=', $argc - 1, ',');
      $this->emit($T1, '=func_num_args();');
      $this->emit($T0, '<', $T1, ';');
      $this->emit('++', $T0);
      $this->emit(')');
      $this->emit_indent();
      $this->emitln('$', $rest, '[]=func_get_arg(', $T0, ');');
      $this->emit_dedent();
    }
  }
  
  /* ------------------------------------ */
  
  protected function enter_unit($node)
  {
    $this->enter_scope($node->scope);
    
    $this->emitln('<?php');
    $this->emitln('namespace Z;');
    $this->emitln('/* This is an automatically GENERATED file by the PHS Compiler');
    $this->emitln(' * Source: "' . $node->loc->file . '" */');
  }
  
  protected function leave_unit($node)
  {
    $this->emitln('');
    $this->leave_scope();
  }
  
  protected function enter_fn_decl($node)
  {
    $sym = $node->symbol;
    
    if ($sym->flags & SYM_FLAG_EXTERN)
      return $this->drop();
    
    $this->emitln('#line ', $node->loc->pos->line);
    
    $capt = $node->scope->get_captures();
    
    if ($capt->avail())
      $this->emit('$', $sym->name, '=function');
    else
      $this->emit('function ', $sym->name);
    
    $fuse = [];
    foreach ($capt as $sym)
      $fuse[] = '&$' . $sym->name;
    
    $fuse = ' use(' . implode(',', $fuse) . ') ';
    
    $this->emit_params($node->params, $fuse);
    $this->enter_scope($node->scope); 
  }
  
  protected function leave_fn_decl($node)
  {
    $this->emit_dedent();
    $this->emit('}');
    if ($node->scope->has_captures())
      $this->emit(';');
    $this->emitln('');
    $this->leave_scope();
  }
  
  protected function visit_expr_stmt($node)
  {
    $this->walk_some($node->expr);
    $this->emit(';');
  }
  
  protected function visit_call_expr($node)
  {
    $this->walk_some($node->callee);
    $this->emit('(');
    
    if ($node->args !== null) {
      $first = true;
      foreach ($node->args as $arg) {
        if (!$first) $this->emit(',');
        $this->walk_some($arg);
        $first = false;
      }
    }
    
    $this->emit(')');
  }
  
  protected function visit_name($node)
  {
    $sym = $node->symbol;
    
    if ($sym->kind === SYM_KIND_FN) {
      if ($node->root === true)
        $this->emit('\\Z\\');
      
      $this->emit(ident_to_str($node->base));
      
      if ($node->parts !== null)
        foreach ($node->parts as $part)
          $this->emit('\\', ident_to_str($part));
    } else {
      // symbol is a variable (due to restrictions [see analyzer])
      $this->emit('$', ident_to_str($node->base));
    }
  }
  
  protected function visit_lnum_lit($node)
  {
    $this->emit($node->value);
  }
}
