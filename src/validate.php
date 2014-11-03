<?php

namespace phs;

require_once 'glob.php';
require_once 'utils.php';
require_once 'visitor.php';

use phs\ast\Unit;
use phs\ast\Name;
use phs\ast\Ident;
use phs\ast\ClassDecl;
use phs\ast\TraitDecl;
use phs\ast\NestedMods;
use phs\ast\FnDecl;
use phs\ast\CtorDecl;
use phs\ast\DtorDecl;
use phs\ast\ThisParam;
use phs\ast\Param;
use phs\ast\StrLit;
use phs\ast\LNumLit;
use phs\ast\SNumLit;
use phs\ast\DNumLit;
use phs\ast\TrueLit;
use phs\ast\FalseLit;
use phs\ast\NullLit;
use phs\ast\ObjKey;
use phs\ast\NamedArg;
use phs\ast\RestArg;
use phs\ast\SelfExpr;
use phs\ast\SuperExpr;
use phs\ast\MemberExpr;
use phs\ast\OffsetExpr;
use phs\ast\CallExpr;
use phs\ast\ExprStmt;

// label
class Label 
{
  public $id;
  public $loc;
  public $breakable;
  public $reachable;
  
  public function __construct($id, Location $loc)
  {
    $this->id = $id;
    $this->loc = $loc;
    $this->breakable = true; // default
    $this->reachable = true; // default
  }
}

// goto
class LGoto
{
  public $id;
  public $loc;
  public $resolved;
  
  public function __construct($id, Location $loc)
  {
    $this->id = $id;
    $this->loc = $loc;
    $this->resolved = false; // default
  }
}

class ValidateTask extends Visitor implements Task
{
  // @var Session
  private $sess;
  
  // @var bool
  private $dict;
  
  // @var array
  private $stack;
  
  // @var bool  
  private $super;
  
  // @var int
  private $inmod = 0;
  
  // @var array
  private $nmods;
  
  // @var array
  private $gotos = [];
  
  // @var array
  private $gstack = [];
  
  // @var array
  private $labels = [];
  
  // @var array
  private $lstack = [];
  
  // @var array
  private $lframe = [];
  
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
   * validates a unit
   *
   * @param  Unit   $unit
   * @return void
   */
  public function run(Unit $unit)
  {
    $this->stack = [ 'unit' ];
    $this->nmods = [];
    $this->super = false;
    $this->visit($unit);
    $this->check_jumps();
  }
  
  /**
   * checks if the current stack includes the given type
   *
   * @param  string $type
   * @param  array $stop
   * @return boolean
   */
  private function within($type, $stop = [])
  {   
    $wild = !empty($stop) && $stop[0] === '*';
    
    for ($i = count($this->stack) -1; $i >= 0; --$i) {
      $c = $this->stack[$i];
      
      if ($c === $type) 
        return true;
      
      if ($wild || in_array($c, $stop)) 
        break;
    }
    
    return false;
  }
  
  /**
   * enters a branch
   *
   * @param  string $type
   * @return void
   */
  private function enter($type) 
  {
    array_push($this->stack, $type);
    
    if ($type === 'loop' || $type === 'switch') {
      // labels inside a loop can not resolve gotos outside
      array_push($this->gstack, $this->gotos);
      $this->gotos = [];
      
      // hide outside labels
      array_push($this->lstack, $this->lframe);
      $this->lframe = [];
    } elseif ($type === 'fn') {
      array_push($this->lstack, $this->labels);
      $this->labels = [];
      
      array_push($this->gstack, $this->gotos);
      $this->gotos = [];
    }
  }
  
  /**
   * leaves a branch
   *
   * @param  string $type
   * @return void
   */
  private function leave($type)
  {
    $last = array_pop($this->stack);
    assert($last === $type);
    
    if ($type === 'loop' || $type === 'switch') {
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
    } elseif ($type === 'fn') {
      $this->check_jumps();
      
      $this->labels = array_pop($this->lstack);
      $this->gotos = array_pop($this->gstack);
    }
  }
  
  /**
   * checks modifiers 
   *
   * @param  array  $mods
   * @param  boolean $fn
   * @return void
   */
  private function check_mods($mods, $fn = false, $nst = true)
  {
    if (!$mods) return;
    
    $nmo = [];
    $cmo = [];
    $ppp = null;
    
    foreach ($mods as $mod) {
      switch ($mod->type) {
        case T_PROTECTED:
          if (!$this->within('class', [ 'fn' ]) &&
              !$this->within('trait', [ 'fn' ]) &&
              !$this->within('iface', [ 'fn' ]))
            break; // will be reported as error anyway
          
          // no break
          
        case T_PUBLIC:
        case T_PRIVATE:
          if ($ppp !== null && $ppp->type !== $mod->type) {
            Logger::error_at($mod->loc, 'ambiguous modifier `%s`', $mod->value);
            Logger::info_at($ppp->loc, 'already seen modifier `%s` here', $ppp->value);
          } elseif ($ppp === null)
            $ppp = $mod;
            
          break;  
          
        default:
          // pass
      } 
      
      switch ($mod->type) {          
        case T_STATIC:
          if ($this->within('fn', [ '*' ]))
            break;
          
          // no break
          
        case T_PROTECTED:
          if (!$this->within('class', [ 'fn' ]) &&
              !$this->within('trait', [ 'fn' ]) &&
              !$this->within('iface', [ 'fn' ]))
            goto err;
          
          break;
        
        case T_PUBLIC:
        case T_PRIVATE:
          if (!$this->within('unit', [ '*' ]) &&
              !$this->within('class', [ 'fn' ]) &&
              !$this->within('trait', [ 'fn' ]) &&
              !$this->within('iface', [ 'fn' ]))
            goto err;
          
          break;
        
        case T_EXTERN:
          if ($this->within('class', [ 'fn' ]) ||
              $this->within('trait', [ 'fn' ]) ||
              $this->within('iface', [ 'fn' ]))
            goto err;
          
          // TODO: remove extern parameters?
          break;
          
        case T_GLOBAL:
          if (!$this->within('unit', [ '*' ]) &&
              !$this->within('fn', [ '*' ]))
            goto err;
          
          break;
        
        case T_INLINE:
        case T_SEALED:
          if (!$fn)
            goto err;
          
          break;
         
        case T_FINAL:
        case T_CONST:
          break; // always allowed
          
        default:
          assert(0);
      }
      
      goto nxt;
      
      err:
      Logger::error_at($mod->loc, 'illegal modifier `%s`', $mod->value);
      Logger::debug('stack = %s', json_encode($this->stack));
      
      nxt:
      if (isset ($nmo[$mod->type])) {
        Logger::warn_at($mod->loc, 'duplicate modifier `%s`', $mod->value);
        Logger::info_at($nmo[$mod->type], 'previous modifier was here');
      } else {
        $nmo[$mod->type] = $mod->loc;
        $cmo[] = $mod;
      }
    }
    
    if ($nst === true && ($this->within('class', [ 'fn' ]) ||
                          $this->within('trait', [ 'fn' ]) ||
                          $this->within('iface', [ 'fn' ])))
      $this->check_nested_mods($cmo);
  }
  
  /**
   * checks if the given mods can be used without collision
   *
   * @param  array $mods
   * @return void
   */
  private function check_nested_mods($mods)
  {
    foreach ($this->nmods as $nmo) {
      foreach ($mods as $mod) {
        if (isset ($nmo[$mod->type])) {
          Logger::warn_at($mod->loc, 'duplicate modifier `%s`', $mod->value);
          Logger::info_at($nmo[$mod->type], 'previous modifier was here');
        }
      }
    }
  }
  
  /**
   * checks if given modifiers + modifers from a nested-mods decl can be merged
   *
   * @param  array $mods
   * @return void
   */
  private function push_mods($mods) 
  {
    if (!$mods) return;
    
    $skip = [];
    
    foreach ($this->nmods as $nmo) {
      foreach ($mods as $mod) {
        if (in_array($mod, $skip))
          continue;
        
        if (isset ($nmo[$mod->type])) {
          $skip[] = $mod;
          continue;
        }
      }
    }
    
    $marr = [];
    
    foreach ($mods as $mod)
      if (!in_array($mod, $skip))
        $marr[$mod->type] = $mod->loc;
    
    array_push($this->nmods, $marr);
  }
  
  /**
   * pops off nested mods
   *
   * @return void
   */
  private function pop_mods() 
  {
    array_pop($this->nmods);
  }
  
  /**
   * checks if <extern> modifier is set
   *
   * @param  array  $mods
   * @return boolean
   */
  private function has_extern_mod($mods)
  {
    if (!$mods) return false;
    
    foreach ($mods as $mod)
      if ($mod->type === T_EXTERN) 
        return true;
    
    return false;
  }
  
  /**
   * checks if <const> modifier is set
   *
   * @param  array  $mods
   * @return boolean
   */
  private function has_const_mod($mods)
  {
    if (!$mods) return false;
    
    foreach ($mods as $mod)
      if ($mod->type === T_CONST) 
        return true;
    
    return false;
  }
  
  /**
   * checks if <static> modifier is set
   *
   * @param  array  $mods
   * @return boolean
   */
  private function has_static_mod($mods)
  {
    if (!$mods) return false;
    
    foreach ($mods as $mod)
      if ($mod->type === T_STATIC) 
        return true;
    
    return false;
  }
  
  /**
   * checks if <final> modifier is set
   *
   * @param  array  $mods
   * @return boolean
   */
  private function has_final_mod($mods)
  {
    if (!$mods) return false;
    
    foreach ($mods as $mod)
      if ($mod->type === T_FINAL) 
        return true;
    
    return false;
  }
  
  /**
   * checks if <private> modifier is set
   *
   * @param  array  $mods
   * @return boolean
   */
  private function has_private_mod($mods)
  {
    // private by default
    if (!$mods) return true;
    
    $other = false;
    
    foreach ($mods as $mod) {
      if ($mod->type === T_PRIVATE) 
        return true;
      
      if ($mod->type === T_PUBLIC ||
          $mod->type === T_PROTECTED) {
        $other = true;
        break;
      }
    }
    
    return !$other;
  }
  
  /**
   * checks if <protected> modifier is set
   *
   * @param  array  $mods
   * @return boolean
   */
  private function has_protected_mod($mods)
  {
    if (!$mods) return false;
    
    foreach ($mods as $mod)
      if ($mod->type === T_PROTECTED) 
        return true;
    
    return false;
  }
  
  /**
   * checks members of a <extern> class/trait or iface declaration
   *
   * @param  Node $decl
   * @return void
   */
  private function check_extern_members($decl)
  {
    if (($decl instanceof ClassDecl || 
         $decl instanceof TraitDecl) && 
        $decl->traits !== null) {
      $peek = $decl->traits[0];
      Logger::error_at($peek->loc, 'extern class/trait must not have traits');
    }
    
    if ($decl->members !== null) {
      // all members must be 'abstract'
      $stack = [ $decl->members ];
      
      while (null !== $members = array_pop($stack)) {
        foreach ($members as $member) {
          if ($member instanceof NestedMods) {
            array_push($stack, $member->members);
            continue;
          }
          
          if (!($member instanceof FnDecl ||
                $member instanceof CtorDecl ||
                $member instanceof DtorDecl)) {
            Logger::error_at($member->loc, 'invalid symbol in extern class');
            continue;
          }
          
          if ($this->has_extern_mod($member->mods)) {
            Logger::info_at($member->loc, '`extern` modifier inside \\');
            Logger::info('extern classes/traits/ifaces is optional');
          }
          
          if ($member->body !== null)
            Logger::error_at($member->loc, 'extern function must not have a body');
        }
      }
    }
  }
  
  /**
   * checks parameters
   *
   * @param  array $params
   * @return void
   */
  private function check_params($params)
  {
    if (!$params) return;
    
    foreach ($params as $param) {
      if ($param instanceof ThisParam && !$this->within('ctor', [ '*'] ))
        Logger::error_at($param->loc, '<this-parameter> not allowed here');
      
      elseif ($param instanceof Param)
        $this->check_mods($param->mods);
    }
  }
  
  /**
   * checks if there a unreachable/undefined jumps (gotos)
   *
   * @return void
   */
  private function check_jumps()
  {
    static $seen_loop_info = false;
    
    assert(is_array($this->gotos));
    
    foreach ($this->gotos as $lid => $gotos) {      
      foreach ($gotos as $goto) {
        if ($goto->resolved === true)
          continue;
                  
        if (!isset ($this->labels[$lid]))
          Logger::error_at($goto->loc, 'goto to undefined label `%s`', $goto->id);
        else {
          Logger::error_at($goto->loc, 'goto to unreachable label `%s`', $goto->id);
          Logger::info_at($this->labels[$lid]->loc, 'label was defined here');
          
          if (!$seen_loop_info) {
            $seen_loop_info = true;
            Logger::info_at($goto->loc, 'it is not possible to jump into a loop or switch statement');
          }
        }
      }
    }
  }
  
  /**
   * checks if a label can be break/continue'ed
   *
   * @param  Node $node
   * @return void
   */
  private function check_label_break($node)
  {
    $lid = ident_to_str($node->id);
    
    if (!isset ($this->labels[$lid]))
      Logger::error_at($node->loc, 'can not break/continue undefined label `%s`', $lid);
    else {
      $label = $this->labels[$lid];
      
      if (!$label->breakable) {
        Logger::error_at($node->loc, 'can not break/continue label `%s` from this position', $lid);
        Logger::info_at($label->loc, 'label was defined here');
      }
    }
  }
  
  private function check_args($args) 
  {
    if (!$args) return;
    
    foreach ($args as $arg)
      if ($arg instanceof NamedArg ||
          $arg instanceof RestArg)
        $this->visit($arg->expr);
      else
        $this->visit($arg);
  }
  
  private function check_super($body)
  {    
    if (!$body || !($body = $body->body)) 
      return;
    
    foreach ($body as $sidx => $stmt)
      if ($stmt instanceof ExprStmt) 
        foreach ($stmt->expr as $eidx => $expr)
          if ($expr instanceof CallExpr &&
              $expr->callee instanceof SuperExpr &&
              ($sidx !== 0 || $eidx !== 0)) {
            Logger::error_at($expr->loc, 'explicit super-call must \\');
            Logger::error('be the very first statement in a constructor');
            Logger::info('why? because the object must be initialized by \\');
            Logger::info('the internal runtime-environment first to work properly');
            Logger::info('if you really need to initialize your object \\');
            Logger::info('somewhere later in the code, consider a \\');
            Logger::info('separate "init" method.');
          }
  }
  
  /* ------------------------------------ */
  
  public function visit_module($node)
  {
    $this->inmod++;
    $this->visit($node->body);
    $this->inmod--;
  }
  
  /**
   * Visitor#visit_enum_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_enum_decl($node) 
  {
    // FIXME: implement enums correctly
    Logger::error_at($node->loc, 'enums are currently not supported');
    return;
    
    $this->check_mods($node->mods);
    
    if ($this->has_const_mod($node->mods)) {
      Logger::info_at($node->loc, '`const` modifier has no effect here');
      Logger::info_at($node->loc, 'enum items are constant by default');
    }
    
    if ($this->within('iface'))
      Logger::error_at($node->loc, 'enum is not allowed inside of iface');
  }
  
  /**
   * Visitor#visit_class_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_class_decl($node) 
  {
    $this->check_mods($node->mods);
    $this->enter('class');
    
    if ($this->has_const_mod($node->mods))
      Logger::info_at($node->loc, '`const` modifier has no effect here');
    
    if ($this->has_extern_mod($node->mods))
      $this->check_extern_members($node);
    else
      $this->visit($node->members);
    
    $this->leave('class');
  }
  
  /**
   * Visitor#visit_nested_mods()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_nested_mods($node) 
  {
    $this->check_mods($node->mods);
    $this->push_mods($node->mods);
    $this->visit($node->members);  
    $this->pop_mods();
  }
  
  /**
   * Visitor#visit_ctor_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_ctor_decl($node) 
  {
    if (!$this->within('class', [ '*' ]))
      Logger::error_at($node->loc, 'illegal constructor declaration');
    
    $this->enter('ctor');
    $this->check_mods($node->mods);
    $this->check_params($node->params);
    
    if ($this->has_static_mod($node->mods))
      Logger::error_at($node->loc, 'constructor cannot be static');
    
    if ($node->body) {
      $this->check_super($node->body);
      $this->visit($node->body);
    }
    
    $this->leave('ctor');
  }
  
  /**
   * Visitor#visit_dtor_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_dtor_decl($node) 
  {
    if (!$this->within('class', [ '*' ]))
      Logger::error_at($node->loc, 'illegal destructor declaration');
    
    $this->check_mods($node->mods); 
    $this->check_params($node->params);
    
    if ($this->has_static_mod($node->mods))
      Logger::error_at($node->loc, 'destructor can not be static'); 
    
    if ($node->body) {
      $this->check_super($node->body);
      $this->enter('dtor');
      $this->visit($node->body);
      $this->leave('dtor');
    }
  }
  
  /**
   * Visitor#visit_getter_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_getter_decl($node) 
  {
    $this->check_params($node->params);
    $this->enter('fn');
    $this->visit($node->body);
    $this->leave('fn'); 
  }
  
  /**
   * Visitor#visit_setter_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_setter_decl($node) 
  {
    $this->check_params($node->params);
    $this->enter('fn');
    $this->visit($node->body);
    $this->leave('fn'); 
  }
    
  /**
   * Visitor#visit_trait_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_trait_decl($node) 
  {
    $this->check_mods($node->mods);
    $this->enter('trait');  
    
    if ($this->has_const_mod($node->mods))
      Logger::info_at($node->loc, '`const` modifier has no effect here');
    
    if ($this->has_extern_mod($node->mods))
      $this->check_extern_members($node);
    else
      $this->visit($node->members);
    
    $this->leave('trait');
  }
  
  /**
   * Visitor#visit_iface_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_iface_decl($node) 
  {
    $this->check_mods($node->mods);
    $this->enter('iface');
    
    if ($this->has_const_mod($node->mods))
      Logger::info_at($node->loc, '`const` modifier has no effect here');
    
    if ($this->has_extern_mod($node->mods))
      $this->check_extern_members($node);
    else
      $this->visit($node->members);
    
    $this->leave('iface');
  }
  
  /**
   * Visitor#visit_fn_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_fn_decl($node) 
  {
    $this->check_mods($node->mods, true);
    $this->check_params($node->params);
    
    $id = ident_to_str($node->id);
        
    // #1 iface-method-body check
    if ($this->within('iface', [ '*' ]) && $node->body !== null) {
      Logger::error_at($node->loc, 'iface method `%s` \\', $id);
      Logger::error('must not have a body');
    }
    
    // #2 iface-static-method-check
    if ($this->within('iface', [ '*' ]) && 
        $this->has_static_mod($node->mods)) {
      // TODO: php allows this
      Logger::error_at($node->loc, 'static members inside interfaces \\ ');
      Logger::error('are currently not supported (`%s`)', $id);
    }
    
    // #3 iface-non-public-method-check
    if ($this->within('iface', [ '*' ]) &&
        ($this->has_private_mod($node->mods) ||
         $this->has_protected_mod($node->mods))) {
      Logger::error_at($node->loc, 'iface method `%s` \\ ', $id);
      Logger::error('must be declared public');
    }
    
    // #4 extern-fn-body check
    if ($this->has_extern_mod($node->mods) && $node->body !== null) {
      Logger::error_at($node->loc, 'extern function \\');
      Logger::error('`%s` must not have a body', $id);
    }
    
    // #5 non-extern-fn-body check
    if (!$this->within('class', [ '*' ]) && 
        !$this->within('trait', [ '*' ]) && 
        !$this->within('iface', [ '*' ]) && 
        !$this->has_extern_mod($node->mods) && $node->body === null) {
      Logger::error_at($node->loc, 'non-extern function `%s` \\', $id);
      Logger::error('must have a body');
    }
    
    // #6 static-fn-abstract check
    if (($this->within('class', [ '*' ]) || 
         $this->within('trait', [ '*' ])) &&
        $this->has_static_mod($node->mods) && $node->body === null) {
      // TODO: php allows this
      Logger::error_at($node->loc, 'static method `%s` \\', $id);
      Logger::error('can not be abstract');
    }
    
    // #7 final-method-abstract check
    if (($this->within('class', [ '*' ]) || 
         $this->within('trait', [ '*' ]) || 
         $this->within('iface', [ '*' ])) && 
        $this->has_final_mod($node->mods) && $node->body === null) {
      Logger::error_at($node->loc, 'final method `%s` \\', $id);
      Logger::error('can not be abstract');
    }
    
    // #8 class-const-method check
    if (($this->within('class', [ '*' ]) || 
         $this->within('trait', [ '*' ]) || 
         $this->within('iface', [ '*' ])) && 
        $this->has_const_mod($node->mods)) {
      Logger::info_at($node->loc, '`const` modifier has no effect here');
    }
    
    // #9 abstract-private check
    if (($this->within('class', [ '*' ]) || 
         $this->within('iface', [ '*' ])) &&
         $this->has_private_mod($node->mods) && $node->body === null) {
      Logger::error_at($node->loc, 'abstract method can not be private');
    }
    
    if ($node->body !== null) {
      $this->enter('fn');
      $this->visit($node->body);
      $this->leave('fn');
    }
  }
  
  /**
   * Visitor#visit_var_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_var_decl($node) 
  {
    $this->check_mods($node->mods);
    
    if ($this->within('iface'))
      Logger::error_at($node->loc, 'variables are not allowed inside of iface');
    
    foreach ($node->vars as $var)
      if ($var->init !== null) {
        $dict = $this->dict;
        $this->dict = true;
        $this->visit($var->init);
        $this->dict = $dict;
      }
  }
  
  /**
   * Visitor#visit_var_list()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_var_list($node) 
  {
    if ($this->within('iface', [ '*' ]) || 
        $this->within('trait', [ '*' ]) || 
        $this->within('class', [ '*' ])) {
      Logger::error_at($node->loc, 'variable list (unpacking) is not \\');
      Logger::error('allowed directly in classes, interfaces or traits');
    }
    
    $this->visit($node->expr);
  }
  
  /**
   * Visitor#visit_use_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_use_decl($node) 
  {
    // noop
  }
  
  /**
   * Visitor#visit_require_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_require_decl($node) 
  {
    $expr = $node->expr;
    
    if (!($expr instanceof StrLit)) {
      // this is only a problem if constant-propagation fails to
      // reduce the given expression to a string.
      // this gets checked in the resolve-pass (Analyzer->resolve_unit)
      Logger::warn_at($node->loc, 'require path should be \\');
      Logger::warn('a constant string value');
    }
  }
  
  /**
   * Visitor#visit_label_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_label_decl($node) 
  {
    $lid = ident_to_str($node->id);
    
    if (isset ($this->labels[$lid])) {
      Logger::error_at($node->loc, 'there is already a label with name \\'); 
      Logger::error('`%s` in this scope', $lid);
      Logger::info_at($this->labels[$lid]->loc, 'previous label was here');    
    }
    
    if (isset ($this->gotos[$lid]))
      foreach ($this->gotos[$lid] as $goto)
        $goto->resolved = true;
    
    $label = new Label($lid, $node->loc);
    $label->reachable = true;
    
    $this->labels[$lid] = $label;
    $this->lframe[$lid] = $label;
    
    $label->breakable = true;
    $this->visit($node->stmt);
    $label->breakable = false;
  }
  
  /**
   * Visitor#visit_do_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_do_stmt($node) 
  {
    $this->enter('loop');
    $this->visit($node->stmt);
    $this->leave('loop');
    $this->visit($node->test);
  }
  
  /**
   * Visitor#visit_if_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_if_stmt($node) 
  {
    $this->visit($node->test);
    $this->visit($node->stmt);
    
    if ($node->elsifs) {
      foreach ($node->elsifs as $elsif) {
        $this->visit($elsif->test);
        $this->visit($elsif->stmt);
      }
    }
    
    if ($node->els)
      $this->visit($node->els->stmt);  
  }
  
  /**
   * Visitor#visit_for_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_for_stmt($node) 
  {
    $this->visit($node->init);
    $this->visit($node->test);
    $this->visit($node->each);
    $this->enter('loop');
    $this->visit($node->stmt); 
    $this->leave('loop');
  }
  
  /**
   * Visitor#visit_for_in_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_for_in_stmt($node) 
  {
    $this->visit($node->rhs);
    $this->enter('loop');
    $this->visit($node->stmt);
    $this->leave('loop');
  }
  
  /**
   * Visitor#visit_try_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_try_stmt($node) 
  {
    $this->visit($node->body);
    
    if ($node->catches)
      foreach ($node->catches as $catch)
        $this->visit($catch->body);  
    
    if ($node->finalizer)
      $this->visit($node->finalizer->body);  
  }
  
  /**
   * Visitor#visit_php_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_php_stmt($node) 
  {
    // noop  
  }
  
  /**
   * Visitor#visit_goto_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_goto_stmt($node) 
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
  
  /**
   * Visitor#visit_test_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_test_stmt($node) 
  {
    $this->visit($node->block);  
  }
  
  /**
   * Visitor#visit_break_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_break_stmt($node) 
  {
    if (!$this->within('loop') && !$this->within('switch'))
      Logger::error_at($node->loc, 'break outside of loop/switch');  
    
    if ($node->id !== null)
      $this->check_label_break($node);
  }
  
  /**
   * Visitor#visit_continue_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_continue_stmt($node) 
  {
    if (!$this->within('loop') && !$this->within('switch'))
      Logger::error_at($node->loc, 'continue outside of loop/switch');   
    
    if ($node->id !== null)
      $this->check_label_break($node);
  }
  
  /**
   * Visitor#visit_print_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_print_stmt($node) 
  {
    $this->visit($node->expr);  
  }
  
  /**
   * Visitor#visit_throw_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_throw_stmt($node) 
  {
    $this->visit($node->expr);  
  }
  
  /**
   * Visitor#visit_while_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_while_stmt($node) 
  {
    $this->visit($node->test);
    $this->enter('loop');
    $this->visit($node->stmt); 
    $this->leave('loop');  
  }
  
  /**
   * Visitor#visit_assert_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_assert_stmt($node) 
  {
    $this->visit($node->expr);
    
    if ($node->message)
      $this->visit($node->message);  
  }
  
  /**
   * Visitor#visit_switch_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_switch_stmt($node) 
  {
    $this->visit($node->test);
    $this->enter('switch');
        
    foreach ($node->cases as $case) {
      foreach ($case->labels as $idx => $label)
        if ($label->expr)
          $this->visit($label->expr);
      
      $this->visit($case->body);
    }  
    
    $this->leave('switch');  
  }
  
  /**
   * Visitor#visit_return_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_return_stmt($node) 
  {
    if (!$this->within('fn'))
      Logger::error_at($node->loc, 'return outside of function');
      
    if ($node->expr)
      $this->visit($node->expr);  
  }
  
  /**
   * Visitor#visit_expr_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_expr_stmt($node) 
  {
    $dict = $this->dict;
    $this->dict = true;
    
    $this->visit($node->expr);  
    
    $this->dict = $dict;
  }
  
  /**
   * Visitor#visit_paren_expr()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_paren_expr($node)
  {
    $this->dict = false;
    $this->visit($node->expr);
  }
  
  /**
   * Visitor#visit_tuple_expr()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_tuple_expr($node)
  {
    $this->dict = false;
    foreach ($node->seq as $expr)
      $this->visit($expr);
  }
  
  /**
   * Visitor#visit_fn_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_fn_expr($node) 
  {
    $this->dict = false;
    $this->check_params($node->params);
    $this->enter('fn');
    $this->visit($node->body);
    $this->leave('fn');
  }
  
  /**
   * Visitor#visit_bin_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_bin_expr($node) 
  {
    $this->dict = false;
    $this->visit($node->left);
    $this->visit($node->right);
  }
  
  /**
   * Visitor#visit_check_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_check_expr($node) 
  {
    $this->dict = false;
    $this->visit($node->left);
    $this->visit($node->right); 
  }
  
  /**
   * Visitor#visit_cast_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_cast_expr($node) 
  {
    $this->dict = false;
    $this->visit($node->expr);
    $this->visit($node->type); 
  }
  
  /**
   * Visitor#visit_update_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_update_expr($node) 
  {
    $this->dict = false;
    $this->visit($node->expr);  
  }
  
  /**
   * Visitor#visit_assign_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_assign_expr($node) 
  {
    $left = $node->left;
    
    if (!($left instanceof Name ||
          $left instanceof Ident ||
          $left instanceof MemberExpr ||
          $left instanceof OffsetExpr))
      Logger::error_at($left->loc, 'invalid assignment left-hand-side');
    
    $this->visit($node->left);
    $this->visit($node->right); 
  }
  
  /**
   * Visitor#visit_member_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_member_expr($node) 
  {
    $this->visit($node->object);
    
    // only visit member on computed expressions
    if ($node->computed)
      $this->visit($node->member);
  }
  
  /**
   * Visitor#visit_offset_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_offset_expr($node) 
  {
    if ($node->object instanceof SelfExpr) {
      Logger::warn_at($node->loc, '`self` used as offset-object might \\');
      Logger::warn('not do what you expect');
    }
    
    $this->visit($node->object);
    $this->visit($node->offset);
  }
  
  /**
   * Visitor#visit_cond_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_cond_expr($node) 
  {
    $this->dict = false;
    $this->visit($node->test);
    
    if ($node->then)
      $this->visit($node->then);
    
    $this->visit($node->els);  
  }
  
  /**
   * Visitor#visit_call_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_call_expr($node) 
  {
    // this is just a typo-prevention.
    // outside of member-expressions `self` resolves 
    // to the fully qualified class/trait name (as string).
    if ($node->callee instanceof SelfExpr) {
      Logger::warn_at($node->loc, '`self` used as function might \\');
      Logger::warn('not do what you expect');
      Logger::info_at($node->loc, ' did you mean `new self(...)` ?');
    }
    
    if ($node->callee instanceof SuperExpr) {
      if (!$this->within('ctor'))
        Logger::error_at($node->loc, 'explicit super-call outside of constructor');
      
      $this->super = true;
    }
    
    $this->visit($node->callee);
    $this->check_args($node->args);
    
    $this->super = false;
  }
  
  /**
   * Visitor#visit_yield_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_yield_expr($node) 
  {
    if (!$this->within('fn'))
      Logger::error_at($node->loc, 'yield outside of function');
    
    if ($node->key)
      $this->visit($node->key);
      
    $this->visit($node->value);  
  }
  
  /**
   * Visitor#visit_unary_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_unary_expr($node) 
  {
    $this->dict = false;
    $this->visit($node->expr);  
  }
  
  /**
   * Visitor#visit_new_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_new_expr($node) 
  {
    $this->visit($node->name);
    $this->check_args($node->args);
  }
  
  /**
   * Visitor#visit_del_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_del_expr($node) 
  {
    $this->visit($node->expr);
  }
  
  /**
   * Visitor#visit_lnum_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_lnum_lit($node) 
  {
    // noop  
  }
  
  /**
   * Visitor#visit_dnum_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_dnum_lit($node) 
  {
    // noop  
  }
  
  /**
   * Visitor#visit_snum_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_snum_lit($node) 
  {
    // noop  
  }
  
  /**
   * Visitor#visit_regexp_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_regexp_lit($node) 
  {
    // noop  
  }
  
  /**
   * Visitor#visit_arr_gen()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_arr_gen($node)
  {
    $this->visit($node->expr);
    $this->visit($node->each);
  }
  
  /**
   * Visitor#visit_arr_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_arr_lit($node) 
  {
    if (!$node->items)
      return;
    
    foreach ($node->items as $item)
      $this->visit($item);  
  }
  
  /**
   * Visitor#visit_obj_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_obj_lit($node) 
  {
    if (!$this->dict) {
      Logger::warn_at($node->loc, 'dict-literals are currently not well \\');
      Logger::warn('supported inside expressions');
      Logger::info('try to hoist the literal to one of the following \\');
      Logger::info('statements:');
      Logger::info('variable-declaration: let a = { ... }');
      Logger::info('return-statement:     return { ... }');
      Logger::info('yield-statement:      yield { ... }');
      Logger::info('or directly inside an expression-statement:');
      Logger::info('variable-assignment:  a = { ... }');
      Logger::info('call-expression:      a({ ... })');
    }
    
    if (!$node->pairs)
      return;
    
    foreach ($node->pairs as $pair) {
      if ($pair->key instanceof ObjKey)
        $this->visit($pair->key->expr);
      
      $this->visit($pair->arg);
    }
  }
  
  /**
   * Visitor#visit_name()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_name($node) 
  {
    // noop  
  }
  
  /**
   * Visitor#visit_ident()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_ident($node) 
  {
    // noop  
  }
  
  /**
   * Visitor#visit_this_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_this_expr($node) 
  {
    if ($this->super) {
      Logger::error_at($node->loc, 'access to `this` is not allowed \\');
      Logger::error('inside a super-call');
      Logger::info('why? because `this` must be initialized during the \\');
      Logger::info('constructor call-chain first to work properly');
      Logger::info('therefore access to `this` is only allowed directly \\');
      Logger::info('within the constructor-bodies or after all \\');
      Logger::info('constructors where called');
    }
  }
  
  /**
   * Visitor#visit_super_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_super_expr($node) 
  {
    // noop  
  }
  
  /**
   * Visitor#visit_null_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_null_lit($node) 
  {
    // noop  
  }
  
  /**
   * Visitor#visit_true_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_true_lit($node) 
  {
    // noop  
  }
  
  /**
   * Visitor#visit_false_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_false_lit($node) 
  {
    // noop  
  }
  
  /**
   * Visitor#visit_engine_const()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_engine_const($node) 
  {
    // noop  
  }
  
  /**
   * Visitor#visit_str_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_str_lit($node) 
  {
    // noop  
  }
  
  /**
   * Visitor#visit_kstr_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_kstr_lit($node) 
  {
    // noop  
  }
  
  /**
   * Visitor#visit_type_id()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_type_id($node) 
  {
    // noop  
  }
  }
