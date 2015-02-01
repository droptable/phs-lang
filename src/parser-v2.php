<?php

namespace phs;

/**
 * the grammar got too complex and context-sensitive (to look reasonable)
 * so i decided to implement the grammar in a recursive-descent way.
 * 
 * this parser does the following things:
 * 
 *   - parse the grammar
 *   - build scopes
 *   - collect symbols
 *   - collect usage
 */

// exception-classes used to break-out of recursion
class ParseError extends \Exception {}
class SyntaxError extends ParseError {}

require_once 'ast.php';
require_once 'lexer.php';
require_once 'scope.php';
require_once 'logger.php';
require_once 'symbols.php';

use phs\ast;
use phs\ast\Node;
use phs\ast\Decl;
use phs\ast\Stmt;
use phs\ast\Expr;

// operator associativity flags
const 
  OP_ASSOC_NONE  = 1,
  OP_ASSOC_LEFT  = 2,
  OP_ASSOC_RIGHT = 3
;

class Parser 
{
  // @var Lexer
  private $lex;
  
  // @var Session
  private $sess;
  
  // @var Scope  current scope
  private $scope;
  
  // @var array  scope stack
  private $stack;
  
  // @var array  operator precedence table
  private static $op_table = [
    // note: only unary, binary and ternary operators are listed here.
    // other operators (like T_INC or T_DOT) are bound to primary expressions 
    // and therefore no precedence / associativity is required to parse them.
    // ---------------------------------------
    // token    => [ arity, associativity, precedence ]
    T_APLUS     => [ 2, OP_ASSOC_RIGHT,  1 ],
    T_AMINUS    => [ 2, OP_ASSOC_RIGHT,  1 ],
    T_AMUL      => [ 2, OP_ASSOC_RIGHT,  1 ],
    T_AMOD      => [ 2, OP_ASSOC_RIGHT,  1 ],
    T_APOW      => [ 2, OP_ASSOC_RIGHT,  1 ],
    T_ACONCAT   => [ 2, OP_ASSOC_RIGHT,  1 ],
    T_ABIT_OR   => [ 2, OP_ASSOC_RIGHT,  1 ],
    T_ABIT_AND  => [ 2, OP_ASSOC_RIGHT,  1 ],
    T_ABIT_XOR  => [ 2, OP_ASSOC_RIGHT,  1 ],
    T_ABOOL_OR  => [ 2, OP_ASSOC_RIGHT,  1 ],
    T_ABOOL_AND => [ 2, OP_ASSOC_RIGHT,  1 ],
    T_ABOOL_XOR => [ 2, OP_ASSOC_RIGHT,  1 ],
    T_ASHIFT_L  => [ 2, OP_ASSOC_RIGHT,  1 ],
    T_ASHIFT_R  => [ 2, OP_ASSOC_RIGHT,  1 ],
    T_AREF      => [ 2, OP_ASSOC_RIGHT,  1 ],
    T_ASSIGN    => [ 2, OP_ASSOC_RIGHT,  1 ],
    T_RANGE     => [ 2, OP_ASSOC_LEFT,   2 ],
    T_QM        => [ 3, OP_ASSOC_RIGHT,  3 ],
    T_BOOL_OR   => [ 2, OP_ASSOC_LEFT,   4 ],
    T_BOOL_XOR  => [ 2, OP_ASSOC_LEFT,   5 ],
    T_BOOL_AND  => [ 2, OP_ASSOC_LEFT,   6 ],
    T_BIT_OR    => [ 2, OP_ASSOC_LEFT,   7 ],
    T_BIT_XOR   => [ 2, OP_ASSOC_LEFT,   8 ],
    T_BIT_AND   => [ 2, OP_ASSOC_LEFT,   9 ],
    T_EQ        => [ 2, OP_ASSOC_LEFT,  10 ],
    T_NEQ       => [ 2, OP_ASSOC_LEFT,  10 ],
    T_IN        => [ 2, OP_ASSOC_NONE,  11 ],
    T_NIN       => [ 2, OP_ASSOC_NONE,  11 ],
    T_IS        => [ 2, OP_ASSOC_NONE,  11 ],
    T_NIS       => [ 2, OP_ASSOC_NONE,  11 ],
    T_GTE       => [ 2, OP_ASSOC_NONE,  11 ],
    T_LTE       => [ 2, OP_ASSOC_NONE,  11 ],
    T_GT        => [ 2, OP_ASSOC_NONE,  11 ],
    T_LT        => [ 2, OP_ASSOC_NONE,  11 ],
    T_SL        => [ 2, OP_ASSOC_LEFT,  12 ],
    T_SR        => [ 2, OP_ASSOC_LEFT,  12 ],
    T_PLUS      => [ 2, OP_ASSOC_LEFT,  13 ],
    T_MINUS     => [ 2, OP_ASSOC_LEFT,  13 ],
    T_CONCAT    => [ 2, OP_ASSOC_LEFT,  13 ],
    T_MUL       => [ 2, OP_ASSOC_LEFT,  14 ],
    T_DIV       => [ 2, OP_ASSOC_LEFT,  14 ],
    T_MOD       => [ 2, OP_ASSOC_LEFT,  14 ],
    T_AS        => [ 2, OP_ASSOC_RIGHT, 15 ],
    T_REST      => [ 2, OP_ASSOC_RIGHT, 16 ],
    T_EXCL      => [ 1, OP_ASSOC_RIGHT, 17 ], // used for all unary ops
    T_POW       => [ 2, OP_ASSOC_RIGHT, 18 ],
  ];
    
  /**
   * constructor
   *
   * @param Session $sess
   */
  public function __construct(Session $sess)
  {
    $this->sess = $sess;
  }
  
  /**
   * parse entry-point
   *
   * @param  Source $src
   * @return ast\Unit
   */
  public function parse(Source $src)
  {
    $this->lex = new Lexer($src);
    $this->scope = $this->sess->scope;
    $this->stack = [];
    $unit = null;
    // main try/catch parse block
    try {
      $unit = $this->parse_unit();
    } catch (ParseError $e) {
      /* noop */
    }
    unset ($this->lex);
    return $unit;
  }
  
  /* ------------------------------------ */
    
  /**
   * @see Lexer#next()
   */
  protected function next()
  {
    $tok = $this->lex->next();
    return $tok;
  }
  
  /**
   * @see Lexer#peek()
   */
  protected function peek($num = 1)
  {
    $tok = $this->lex->peek($num);
    return $tok;
  }
  
  /* ------------------------------------ */
  
  /**
   * aborts the parser
   *
   * @param  Token|null $tok
   * @throws SyntaxError
   */
  protected function abort(Token $tok = null)
  {
    $msg = 'syntax error';
    $fmt = '';
    
    if ($tok !== null) {
      $msg .= ': unexpected %s';
      $loc = $tok->loc;
      $fmt = $this->lex->lookup($tok);
    } else
      $loc = $this->lex->loc();
      
    Logger::error_at($loc, $msg, $fmt);
    // Logger::error('%s', $this->lex->span($loc));
    // abort recursion
    throw new SyntaxError;  
  }
  
  /* ------------------------------------ */
  
  /**
   * checks if the next token-type is $tid
   *
   * @param  int $tid
   * @return Token
   * @throws SyntaxError
   */
  protected function expect($tid)
  {
    $tok = $this->next();
    
    if ($tok->type !== $tid) {
      Logger::error_at($tok->loc, 
        'syntax error: unexpected %s, expected %s', 
        $this->lex->lookup($tok),
        $this->lex->lookup($tid)
      );
      
      // Logger::error('%s', $this->lex->span($loc));
      // abort recursion
      throw new SyntaxError;
    }
    
    return $tok;
  }
  
  /**
   * consumes tokens
   *
   * @param  array $tids
   * @return bool
   */
  protected function consume(... $tids)
  {
    $peek = $this->peek();
      
    if (!in_array($peek->type, $tids, true))
      return null;
    
    return $this->next();
  }
  
  /**
   * consumes semicolons
   *
   */
  protected function consume_semis()
  {
    for (;;) {
      $peek = $this->peek();
      
      if ($peek->type !== T_SEMI)
        break;
      
      $this->next();
    }
  }
  
  /* ------------------------------------ */
  
  /**
   * enters a new scope
   *
   * @param  Scope  $scope
   */
  protected function enter(Scope $scope)
  {
    array_push($this->stack, $this->scope);
    $this->scope = $scope;
  }
  
  /**
   * leaves a scope
   *
   * @return Scope  the previous scope
   */
  protected function leave()
  {
    $prev = $this->scope;
    $this->scope = array_pop($this->stack);
    return $prev;
  }
  
  
  /* ------------------------------------ */
  
  /**
   * checks class modifiers
   *
   * @param  array $mods
   */
  protected function check_class_mods($mods)
  {
    foreach ($mods as $mod) 
      switch ($mod->type) {
        case T_PUBLIC: 
        case T_FINAL: 
        case T_STATIC:
          // @TODO: add "abstract" ?
          break;
        default:
          Logger::warn_at($mod->loc, 'illegal class-modifier \\');
          Logger::warn('`%s`', $mod->value);
      }
  }
  
  /* ------------------------------------ */
  
  /**
   * @return ast\Unit
   */
  protected function parse_unit() 
  {
    $this->enter(new UnitScope($this->scope));
    $nloc = $this->peek()->loc;
    $body = $this->parse_unit_decls();
    $node = new ast\Unit($nloc, $body);
    $node->scope = $this->leave();
    return $node;
  }
  
  /**
   * @return ast\Module|array
   */
  protected function parse_unit_decls()
  {
    // first module can be "module {name};"
    if ($this->peek()->type === T_MODULE)
      return $this->parse_module_decl(true);
    // otherwise parse normal declarations
    return $this->parse_decls();
  }
  
  /**
   * @return array
   */
  protected function parse_decls()
  {
    $decls = [];
    
    for (;;) {
      $peek = $this->peek();
      // abort early
      if ($peek->type === T_EOF ||
          $peek->type === T_END)
        break;
      // expect declaration
      $decls[] = $this->parse_decl();
    }
    
    return $decls; 
  }
  
  /**
   * @return ast\Decl|ast\Stmt
   */
  protected function parse_decl()
  {
    $peek = $this->peek();
    $decl = null;
    // module-declaration
    if ($peek->type === T_MODULE)
      $decl = $this->parse_module_decl();
    // variable-declaration without modifiers
    elseif ($peek->type === T_LET)
      $decl = $this->parse_var_decl();
    // everything other
    else {
      $mods = $this->parse_maybe_mods();
      $peek = $this->peek();
      
      switch ($peek->type) {
        case T_USE:
          $decl = $this->parse_use_decl($mods);
          break;
        case T_CLASS:
          $decl = $this->parse_class_decl($mods);
          break;
        case T_IFACE:
          $decl = $this->parse_iface_decl($mods);
          break;
        case T_TRAIT:
          $decl = $this->parse_trait_decl($mods);
          break;
        default:
          $decl = $mods ?
            $this->parse_value_decl($mods) :
            $this->parse_label_or_stmt();
      }
    }
    
    return $decl;
  }
  
  /**
   * @return array
   */
  protected function parse_maybe_mods()
  {
    $mods = [];
    
    for (;;) {
      $peek = $this->peek();
      switch ($peek->type) {
        case T_CONST:     case T_FINAL: 
        case T_GLOBAL:    case T_STATIC:
        case T_PUBLIC:    case T_PRIVATE: 
        case T_PROTECTED: case T_SEALED: 
        case T_INLINE:    case T_EXTERN:
        case T_UNSAFE:    case T_NATIVE: 
        case T_HIDDEN:
          $mods[] = $this->next();
          break;
        default:
          // if no mods where found
          // return null instead of an empty-array
          if (empty ($mods)) return null;
          return $mods;
      }
    }
  }
  
  /**
   * @param  boolean $glob  allow module-document
   * @return ast\Module
   */
  protected function parse_module_decl($glob = false)
  {
    $nloc = $this->expect(T_MODULE)->loc;
    $name = null;
    $this->enter(new ModuleScope($this->scope));
    
    // optional name
    if (!$this->consume(T_LBRACE))
      $name = $this->parse_name();    
    // module-document allowed?
    if ($glob && $name && $this->consume(T_SEMI))
      $body = $this->parse_decls();
    else {
      if (!$name)
        $this->expect(T_LBRACE);
      $body = [];
      while (!$this->consume(T_RBRACE))
        $body[] = $this->parse_decl();
    }
    
    $node = new ast\Module($nloc, $name, $body);
    $node->scope = $this->leave();
    return $node;
  }
  
  /**
   * @param  array $mods
   * @return ast\UseDecl
   */
  protected function parse_use_decl(array $mods = null)
  {
    $nloc = null;
    
    if ($mods !== null) {
      $this->check_use_decl_mods($mods);
      $nloc = $mods[0]->loc;
    }
    
    $tok = $this->expect(T_USE);
    // use "use" token location
    if ($nloc === null) $nloc = $tok->loc;
    
    $item = $this->parse_use_decl_item();
    $this->expect(T_SEMI);
    $this->consume_semis();
    $node = new ast\UseDecl($nloc, $mods, $item);
    return $node;
  }
  
  /**
   * @return Name|UseAlias|UseUnpack
   */
  protected function parse_use_decl_item()
  {
    $name = $this->parse_name();
    // use_name T_AS ident
    if ($this->consume(T_AS)) {
      $asid = $this->parse_ident();
      $node = new ast\UseAlias($name->loc, $name, $asid);
      return $node;
    }
    // use_name '{' use_items '}'
    if ($this->consume(T_LBRACE)) {
      $list = $this->parse_use_decl_items();
      $node = new ast\UseUnpack($name->loc, $name, $list);
      return $node;
    }
    // name
    return $name;
  }
  
  /**
   * @return array<use_item>
   */
  protected function parse_use_decl_items()
  {
    $list = [];
    
    for (;;) {
      $list[] = $this->parse_use_decl_item();
      $peek = $this->peek();
      // halt if no comma
      if ($peek->type !== T_COMMA)
        break;
    }
    
    return $list;
  }
  
  /**
   * @param  array  $mods
   * @return ast\ClassDecl
   */
  protected function parse_class_decl(array $mods = null)
  {
    $nloc = null;
    
    if ($mods !== null) {
      $this->check_class_mods($mods);
      $nloc = $mods[0]->loc;
    }
    
    $tok = $this->expect(T_CLASS);
    // use token location
    if ($nloc === null) $nloc = $tok->loc;
    
    $node = null;
    $name = $this->parse_ident();
    $gdef = $this->parse_maybe_generic_def();
    $cext = $this->parse_maybe_class_ext();
    $cimp = $this->parse_maybe_class_impl();
    
    $this->add_class_sym($name, $gdef, $cext, $cimp);
    
    $uses = null;
    $body = null;
    $this->enter(new MemberScope($this->scope));
    
    if (!$this->consume(T_SEMI)) {
      $this->expect(T_LBRACE);
      $uses = $this->parse_maybe_trait_uses();
      $body = $this->parse_maybe_class_members();
      $this->expect(T_RBRACE);
    }
    
    $this->consume_semis();
    $node = new ast\ClassDecl($nloc, $mods, $name, $gdef, 
                              $cext, $cimp, $uses, $body);
    $node->scope = $this->leave();
    return $node;
  }
  
  /**
   * @param  array|null $mods
   * @return ast\TraitDecl
   */
  protected function parse_trait_decl(array $mods = null)
  {
    $nloc = null;
    
    if ($mods !== null) {
      $this->check_trait_mods($mods);
      $nloc = $mods[0]->loc;
    }
    
    $tok = $this->expect(T_TRAIT);
    // use token location
    if ($nloc === null) $nloc = $tok->loc;
    
    $node = null;
    
    $name = $this->parse_ident();
    $gdef = $this->parse_maybe_generic_def();
    
    $this->add_trait_sym($name, $gdef);
    
    $uses = null;
    $body = null;
    $this->enter(new MemberScope($this->scope));
    
    if (!$this->consume(T_SEMI)) {
      $this->expect(T_LBRACE);
      $uses = $this->parse_maybe_trait_uses();
      $body = $this->parse_maybe_trait_members();
      $this->expect(T_RBRACE);
    }
    
    $this->consume_semis();
    $node = new ast\TraitDecl($nloc, $mods, $name, $gdef, 
                              $uses, $body);
    $node->scope = $this->leave();
    return $node;
  }
  
  /**
   * @param  array|null $mods
   * @return ast\IfaceDecl
   */
  protected function parse_iface_decl(array $mods = null)
  {
    $nloc = null;
    
    if ($mods !== null) {
      $this->check_iface_mods($mods);
      $loc = $mods[0]->loc;
    }
    
    $tok = $this->expect(T_IFACE);
    // use token location
    if ($nloc === null) $nloc = $tok->loc;
    
    $name = $this->parse_ident();
    $gdef = $this->parse_maybe_generic_def();
    $iext = $this->parse_maybe_iface_ext();
    
    $this->add_iface_sym($name, $gdef, $iext);
    
    $body = null;
    $this->enter(new MemberScope($this->scope));
    
    if (!$this->consume(T_SEMI)) {
      $this->expect(T_LBRACE);
      $body = $this->parse_maybe_iface_members();
      $this->expect(T_RBRACE);
    }
    
    $this->consume_semis();
    $node = new ast\IfaceDecl($mods, $name, $gdef, 
                              $iext, $body);
    $node->scope = $this->leave();
    return $node;
  }
  
  /**
   * @return ast\TypeName|null
   */
  protected function parse_maybe_generic_def()
  {
    if ($this->consume(T_LT)) {
      $type = $this->parse_type_name();
      $this->expect(T_GT);
      return $type;
    }
    // nothing
    return null;
  }
  
  /**
   * @return ast\Name|null
   */
  protected function parse_maybe_class_ext()
  {
    if ($this->consume(T_DDOT))
      return $this->parse_name();
    // nothing
    return null;
  }
  
  /**
   * @return array<ast\Name>|null
   */
  protected function parse_maybe_class_impl()
  {
    if ($this->consume(T_BIT_NOT)) {
      $impls = [];
      for (;;) {
        $impls[] = $this->parse_name();
        if (!$this->consume(T_COMMA))
          break; 
      }
      return $impls;
    }
    // nothing
    return null;
  }
  
  /**
   * @return array<...>  
   * @see parse_class_member()
   */
  protected function parse_maybe_class_members()
  {
    $mems = [];
    for (;;) {
      $peek = $this->peek();
      // halt on '}'
      if ($peek->type === T_RBRACE)
        break;
      // nested-mods / variable / function ...
      $mems[] = $this->parse_class_member(); 
    }
    return $mems;
  }
  
  /**
   * @return ast\NestedMods|ast\VarDecl|ast\FnDecl
   */
  protected function parse_class_member()
  {
    $mods = $this->parse_maybe_mods();
    // check mods
    if ($mods !== null) {
      $this->check_class_member_mods($mods);
      // nested modifiers
      if ($this->consume(T_LBRACE)) {
        $nloc = $mods[0]->loc;
        $body = $this->parse_maybe_class_members();
        $this->expect(T_RBACE);
        $node = new ast\NestedMods($nloc, $mods, $body);
        return $node;
      }
    }
    // variable / function declaration
    return $this->parse_value_decl($mods);
  }
  
  /**
   * @return array<ast\TraitUse>
   */
  protected function parse_maybe_trait_uses()
  {
    $uses = [];
    for (;;) {
      // halt if no more "use"
      if ($this->peek()->type !== T_USE)
        break;
      // handle usage
      $uses = $this->parse_trait_use();
    }
    return $uses;
  }
  
  /**
   * @return ast\TraitUse
   */
  protected function parse_trait_use()
  {
    $nloc = $this->expect(T_USE)->loc;
    $name = $this->parse_name();
    // formal definition (items)
    if ($this->consume(T_LBRACE)) {
      $items = [];
      for (;;) {
        $items[] = $this->parse_trait_use_item();
        // halt on '}'
        if ($this->consume(T_RBRACE))
          break;
      }
      $node = new ast\TraitUse($nloc, $name, $items);
      return $node;
    }
    // no items
    $node = new ast\TraitUse($nloc, $name, null);
    return $node;
  }
  
  /**
   * @return ast\TraitUseItem
   */
  protected function parse_trait_use_item()
  {
    $base = $this->parse_ident();
    $mods = null;
    $alias = null;
    // alias definition
    if ($this->consume(T_AS)) {
      $mods = $this->parse_maybe_mods();
      // handle alias
      if ($mods === null /* alias is required */ || 
          $this->peek()->type === T_IDENT)
        $alias = $this->parse_ident();
    }
    $this->expect(T_SEMI);
    $this->consume_semis();
    $node = new ast\TraitUseItem($base->loc, $base, $mods, $alias);
    return $node;
  }
  
  /**
   * @return array<ast\Name>
   */
  protected function parse_maybe_iface_ext()
  {
    if ($this->consume(T_DDOT)) {
      $exts = [];
      for (;;) {
        $exts[] = $this->parse_name();
        // halt if no more comma is available
        if (!$this->consume(T_COMMA))
          break;
      }
      return $exts;
    }
    return null;
  }
  
  /**
   * @see parse_maybe_class_members()
   */
  protected function parse_maybe_iface_members()
  {
    $mems = $this->parse_maybe_class_members();
    $this->check_iface_members($mems);
    return $mems;
  }
    
  /**
   * @param  array|null $mods
   * @return ast\FnDecl|ast\VarDecl
   */
  protected function parse_value_decl(array $mods = null)
  {
    // function
    if ($this->peek()->type === T_FN)
      return $this->parse_fn_decl($mods);
    // variable
    return $this->parse_var_decl($mods);
  }
  
  /**
   * @param  array|null $mods
   * @return ast\FnDecl
   */
  protected function parse_fn_decl(array $mods = null)
  {
    $nloc = null;
    
    if ($mods !== null) {
      $this->check_fn_mods($mods);
      $nloc = $mods[0]->loc;
    }
    
    $tok = $this->expect(T_FN);
    // use token location
    if ($nloc === null) $nloc = $tok->loc;
    
    $fnrw = $this->parse_maybe_rewrite();
    $name = $this->parse_ident();
    $gdef = $this->parse_maybe_generic_def();
    $params = $this->parse_fn_params();
    $hint = $this->parse_maybe_hint();
    
    $this->add_fn_sym($name, $gdef);
    
    $body = null;
    $this->enter(new FnScope);
    
    if (!$this->consume(T_SEMI))
      $body = $this->parse_fn_body();
    
    $this->consume_semis();
    $node = new ast\FnDecl($loc, $fnrw, $name, $gdef, 
                           $params, $hint, $body);
    $node->scope = $this->leave();
    return $node;
  }
  
  /**
   * @param  array|null $mods
   * @return ast\VarDecl
   */
  protected function parse_var_decl(array $mods = null)
  {
    $node = $this->parse_var_decl_no_semi($mods);
    $this->expect(T_SEMI);
    $this->consume_semis();
    return $node;    
  }
  
  /**
   * @param  array|null $mods
   * @param  boolean    $allow_in
   * @return ast\VarDecl|ast\VarList
   */
  protected function parse_var_decl_no_semi(array $mods = null, 
                                            $allow_in = true)
  {
    $loc = null;
    
    if ($mods !== null) {
      $this->check_var_mods($mods);
      $loc = $mods[0]->loc;
    } else
      $loc = $this->expect(T_LET)->loc;
    
    $peek = $this->peek();
    
    if ($peek->type === T_LPAREN)
      return $this->parse_var_list_decl($loc, $mods, $allow_in);
    
    $vars = [];
        
    for (;;) {
      $vars[] = $this->parse_var_item($allow_in);
      // halt if no more ',' are available
      if (!$this->consume(T_COMMA))
        break;
    }
    
    $node = new ast\VarDecl($loc, $mods, $vars);
    return $node;
  }
  
  /**
   * @param  Location $loc
   * @param  array    $mods
   * @param  boolean  $allow_in
   * @return ast\VarList
   */
  protected function parse_var_list_decl(Location $loc, 
                                         array $mods = null,
                                         $allow_in = true)
  {
    $this->expect(T_LPAREN);
    $list = [];
    
    for (;;) {
      $list[] = $this->parse_ident();
      // halt if no more ',' are available
      if (!$this->consume(T_COMMA))
        break;
    }
    
    $this->expect(T_RPAREN);
    $this->expect(T_ASSIGN);
    $expr = $this->parse_expr($allow_in);
    $node = new ast\VarList($loc, $mods, $vars, $expr);
    return $node;
  }
  
  /**
   * @param  boolean $allow_in
   * @return ast\VarItem
   */
  protected function parse_var_item($allow_in = true)
  {
    $name = $this->parse_ident();
    $hint = $this->parse_maybe_hint();
    
    $init = null;
    $aref = false;
    $peek = $this->peek();
    
    if ($peek->type === T_ASSIGN ||
        $peek->type === T_AREF) {
      $this->consume($peek->type);
      $init = $this->parse_expr($allow_in);
      $aref = $peek->type === T_AREF;
    }    
    
    $node = new ast\VarItem($name->loc, $name, $hint, $init, $aref);
    return $node;
  }
  
  /**
   * @return ast\TypeName|null
   */
  protected function parse_maybe_hint() 
  {
    if ($this->consume(T_DDOT))
      return $this->parse_type_name();
    // no type, implicit "any"
    return null;
  }
  
  /**
   * @return ast\RequireDecl
   */
  protected function parse_require_decl()
  {
    $nloc = $this->expect(T_REQUIRE)->loc;
    $expr = $this->parse_expr();
    $this->expect(T_SEMI);
    $node = new ast\RequireDecl($loc, $expr);
    return $node;
  }
  
  /**
   * @return ast\Stmt
   */
  protected function parse_stmt()
  {
    $peek = $this->peek();
    
    switch ($peek->type) {
      case T_LBRACE:
        return $this->parse_block();
      case T_DO:
        return $this->parse_do_stmt();
      case T_IF:
        return $this->parse_if_stmt();
      case T_FOR:
        return $this->parse_for_stmt();
      case T_TRY:
        return $this->parse_try_stmt();
      case T_GOTO:
        return $this->parse_goto_stmt();
      case T_TEST:
        return $this->parse_test_stmt();
      case T_BREAK:
        return $this->parse_break_stmt();
      case T_CONTINUE:
        return $this->parse_continue_stmt();
      case T_THROW:
        return $this->parse_throw_stmt();
      case T_WHILE:
        return $this->parse_while_stmt();
      case T_ASSERT:
        return $this->parse_assert_stmt();
      case T_SWITCH:
        return $this->parse_switch_stmt();
      case T_RETURN:
        return $this->parse_return_stmt();
      default:
        return $this->parse_expr_stmt();
    }
  }
  
  /**
   * @return ast\Block
   */
  protected function parse_block()
  {
    $nloc = $this->expect(T_LBRACE)->loc;
    $body = [];
    
    while (!$this->consume(T_RBRACE))
      $body[] = $this->parse_comp();
    
    $node = new ast\Block($nloc, $body);
    return $node;
  }
  
  /**
   * @return Decl|Stmt
   */
  protected function parse_comp()
  {
    $peek = $this->peek();
    // variable-declaration without modifiers
    if ($peek->type === T_LET)
      return $this->parse_var_decl();
    
    $mods = $this->parse_maybe_mods();
    // function
    if ($peek->type === T_FN)
      return $this->parse_fn_decl($mods);
    // variable
    if ($mods !== null)
      return $this->parse_var_decl($mods);
    // label or statement
    return $this->parse_label_or_stmt();
  }
  
  /**
   * @return LabelDecl|Stmt
   */
  protected function parse_label_or_stmt()
  {
    if ($this->peek(1)->type === T_IDENT &&
        $this->peek(2)->type === T_DDOT) {
      $id = $this->parse_ident();
      $this->consume(T_DDOT);
      $stmt = $this->parse_stmt();
      $node = new ast\LabelDecl($id->loc, $id, $stmt);
      return $node;
    }
    
    return $this->parse_stmt();
  }
    
  /**
   * @return DoStmt
   */
  protected function parse_do_stmt()
  {
    $nloc = $this->expect(T_DO)->loc;
    $stmt = $this->parse_stmt();
    $this->expect(T_WHILE);
    $expr = $this->parse_paren_expr();
    $node = new ast\DoStmt($nloc, $stmt, $expr);
    return $node;
  }
  
  /**
   * @return ast\IfStmt
   */
  protected function parse_if_stmt()
  {
    $nloc = $this->expect(T_IF)->loc;
    $expr = $this->parse_paren_expr();
    $stmt = $this->parse_stmt();
    
    $elif_ = [];
    $else_ = null;
    
    // elsif ...
    while ($tok = $this->consume(T_ELIF)) {      
      $elif_expr = $this->parse_paren_expr();
      $elif_stmt = $this->parse_stmt();
      $elif_[] = new ast\ElifStmt($tok->loc, $elif_expr, $elif_stmt);
    }
    
    // else
    if ($tok = $this->consume(T_ELSE)) {
      $else_stmt = $this->parse_stmt();
      $else_ = new ast\ElseStmt($tok->loc, $else_stmt);
    }
    
    $node = new ast\IfStmt($nloc, $expr, $stmt, $elif_, $else_);
    return $node;
  }
  
  /**
   * @return ForStmt|ForInStmt
   */
  protected function parse_for_stmt()
  {
    $nloc = $this->expect(T_FOR)->loc;
    
    $init = null;
    $test = null;
    $each = null;
    
    if ($this->consume(T_LPAREN)) {
      $peek = $this->peek();
            
      if ($peek->type === T_LET)
        $init = $this->parse_var_decl_no_semi(null, false);
      else {        
        $mods = $this->parse_maybe_mods();
        
        if ($mods !== null)
          $init = $this->parse_var_decl_no_semi($mods, false);
        else {
          if ($peek->type === T_IDENT) {
            // could be a for in loop
            $peek = $this->peek(2);
            
            if ($peek->type === T_IN) {
              $init = $this->parse_ident();
              $this->consume(T_IN);
              goto _forin;
            }  
          }
          
          if ($peek->type !== T_SEMI)
            $init = $this->parse_expr(false);
          
          goto _forsq;
        }
        
        if ($this->consume(T_IN)) {
          _forin:
          $expr = $this->parse_expr();
          $this->expect(T_RPAREN);
          $stmt = $this->parse_stmt();
          $node = new ast\ForInStmt($nloc, $init, $expr, $stmt);
          return $node;
        }
      }
      
      _forsq:      
      $this->expect(T_SEMI);
      $test = $this->parse_expr_stmt();
      $each = $this->parse_expr();
      $this->expect(T_RPAREN);
      $stmt = $this->parse_stmt();
    } else
      $stmt = $this->parse_block();
      
    $node = new ast\ForStmt($nloc, $init, $test, $each, $stmt);
    return $node;
  }
  
  /**
   * @return ast\TryStmt
   */
  protected function parse_try_stmt()
  {
    $nloc = $this->expect(T_TRY)->loc;
    $stmt = $this->parse_block();
    $catches = [];
    $finally = null;
    
    while ($tok = $this->consume(T_CATCH)) {
      $id = null;
      $mods = null;
      $type = null;
      
      if ($this->consume(T_LPAREN)) {
        $peek = $this->peek();
                
        if ($peek->type !== T_DDOT) {
          $mods = $this->parse_maybe_mods();
          $id = $this->parse_ident();
        }
        
        if ($this->consume(T_DDOT))
          $type = $this->parse_type_name();
        
        $this->expect(T_RPAREN);
      }
      
      $cbody = $this->parse_block();
      $catch = new ast\CatchItem($tok->loc, $mods, $id, 
                                 $type, $cbody);
      $catches[] = $catch;
    }
    
    if ($tok = $this->consume(T_FINALLY)) {
      $fbody = $this->parse_block();
      $finally = new ast\FinallyItem($tok->loc, $fbody);
    }
    
    $node = new ast\TryStmt($nloc, $stmt, $catches, $finally);
    return $node;
  }
  
  /**
   * @return ast\GotoStmt
   */
  protected function parse_goto_stmt()
  {
    $nloc = $this->expect(T_GOTO)->loc;
    $id = $this->parse_ident();
    $node = new ast\GotoStmt($nloc, $id);
    return $node;
  }
  
  /**
   * @return ast\TestStmt
   */
  protected function parse_test_stmt()
  {
    $nloc = $this->expect(T_TEST)->loc;
    $peek = $this->peek();
    
    $head = null;
    if ($peek->type !== T_LBRACE)
      $head = $this->parse_str_lit();
    
    $stmt = $this->parse_block();
    $node = new ast\TestStmt($nloc, $head, $stmt);
    return $node;
  }
  
  /**
   * @return ast\BreakStmt
   */
  protected function parse_break_stmt()
  {
    $nloc = $this->expect(T_BREAK)->loc;
    $peek = $this->peek();
    
    $id = null;
    if ($peek->type === T_IDENT)
      $id = $this->parse_ident();
    
    $this->expect(T_SEMI);
    
    $node = new ast\BreakStmt($nloc, $id);
    return $node;
  }
  
  /**
   * @return ast\ContinueStmt
   */
  protected function parse_continue_stmt()
  {
    $nloc = $this->expect(T_CONTINUE)->loc;
    $peek = $this->peek();
    
    $id = null;
    if ($peek->type === T_IDENT)
      $id = $this->parse_ident();
    
    $this->expect(T_SEMI);
    
    $node = new ast\ContinueStmt($nloc, $id);
    return $node;
  }
  
  /**
   * @return ast\ThrowStmt
   */
  protected function parse_throw_stmt()
  {
    $nloc = $this->expect(T_THROW)->loc;
    $expr = $this->parse_expr();
    $node = new ast\ThrowStmt($nloc, $expr);
    return $node;
  }
  
  /**
   * @return ast\WhileStmt
   */
  protected function parse_while_stmt()
  {
    $nloc = $this->expect(T_WHILE)->loc;
    $expr = $this->parse_paren_expr();
    $stmt = $this->parse_stmt();
    $node = new ast\WhileStmt($nloc, $expr, $stmt);
    return $node;
  }
  
  /**
   * @return ast\AssertStmt
   */
  protected function parse_assert_stmt()
  {
    $nloc = $this->expect(T_ASSERT)->loc;
    $expr = $this->parse_expr();
    
    $msg = null;
    if ($this->consume(T_DDOT))
      $msg = $this->parse_str_lit();
    
    $node = new ast\AssertStmt($nloc, $expr, $msg);
    return $node;
  }
  
  /**
   * @return ast\SwitchStmt
   */
  protected function parse_switch_stmt()
  {
    $nloc = $this->expect(T_SWITCH)->loc;
    $expr = $this->parse_paren_expr();
    $this->expect(T_LBRACE);
    
    $cases = [];
    
    for (;;) {
      $cases[] = $this->parse_switch_case();
      
      $peek = $this->peek();
      if ($peek->type !== T_CASE &&
          $peek->type !== T_DEFAULT)
        break;
    }
    
    $this->expect(T_RBRACE);
    $node = new ast\SwitchStmt($nloc, $expr, $cases);
    return $node;
  }
  
  /**
   * @return ast\CaseItem
   */
  protected function parse_switch_case()
  {
    $fold = [];
    $body = [];
    
    for (;;) {
      $peek = $this->peek();
      $type = T_CASE;
      $expr = null;
      
      if ($peek->type === T_DEFAULT)
        $type = T_DEFAULT;
      
      $loc = $this->expect($type)->loc;
      
      if ($type === T_CASE)
        $expr = $this->parse_expr();
      
      $this->expect(T_DDOT);
      
      $fold[] = new ast\CaseLabel($loc, $expr);
      
      $peek = $this->peek();
      
      if ($peek->type !== T_CASE &&
          $peek->type !== T_DEFAULT)
        break;
    }
    
    for (;;) {
      $body[] = $this->parse_stmt();
      
      $peek = $this->peek();
      
      if ($peek->type === T_CASE ||
          $peek->type === T_DEFAULT ||
          $peek->type === T_RBRACE)
        break;
    }
    
    $node = new ast\CaseItem($fold[0]->loc, $fold, $body);
    return $node;
  }
  
  /**
   * @return ast\ReturnStmt
   */
  protected function parse_return_stmt()
  {
    $nloc = $this->expect(T_RETURN)->loc;
    $expr = null;
    
    if (!$this->consume(T_SEMI)) {
      $expr = $this->parse_expr();
      $this->expect(T_SEMI);
      $this->consume_semis();
    }
    
    $node = new ast\ReturnStmt($nloc, $expr);
    return $node;
  }
  
  /**
   * @return ast\ExprStmt
   */
  protected function parse_expr_stmt()
  {
    if ($tok = $this->consume(T_SEMI)) {
      $this->consume_semis();
      $node = new ast\ExprStmt($tok->loc, null);
      return $node; 
    }
    
    $list = [];
    for (;;) {
      $list[] = $this->parse_expr();
      // halt of no more ',' are available
      if (!$this->consume(T_COMMA))
        break;
    }
    
    $this->expect(T_SEMI);
    $this->consume_semis();
    $node = new ast\ExprStmt($list[0]->loc, $list);
    return $node;
  }
  
  /**
   * parses an expression
   * 
   * @param  bool $allow_in
   * @param  bool $allow_call
   * @return Expr
   */
  protected function parse_expr($allow_in = true, 
                                $allow_call = true)
  {
    // start precedence climbing
    $expr = $this->parse_expr_ops(0, $allow_in, $allow_call);
    return $expr;
  }
  
  /**
   * parses an expression using the precedence climbing algorithm
   * 
   * informations:
   *   http://www.engr.mun.ca/~theo/Misc/exp_parsing.htm#climbing
   *
   * @param  int     $prec
   * @param  boolean $allow_in
   * @param  boolean $allow_call
   * @return Expr
   */
  protected function parse_expr_ops($prec, $allow_in = true, 
                                           $allow_call = true)
  {
    Logger::debug('%s', 'parse_expr_ops');

    $lhs = $this->parse_primary_expr($allow_in, $allow_call);
    $loc = $lhs->loc;
    $nas = false;
    
    for (;;) {
      $peek = $this->peek();
      
      if (!isset (self::$op_table[$peek->type]) ||
          ($peek->type === T_IN && !$allow_in))
        break;
      
      $op = self::$op_table[$peek->type];      
      if ($op[2] <= $prec) break;
      
      // operator arity check ||
      // previous and current operator have no associativity
      if ($op[0] < 2 || ($nas && $op[1] === OP_ASSOC_NONE))
        $this->abort($peek);
      
      $nas = $op[1] === OP_ASSOC_NONE;
      $this->next();
      
      $subp = $op[2];
      
      if ($op[1] === OP_ASSOC_LEFT ||
          $op[1] === OP_ASSOC_NONE)
        $subp += 1;
            
      // expr '?' expr ':' expr
      // expr '?' ':' expr
      if ($peek->type === T_QM) {
        $then = null;
        $altn = null;
                
        if ($this->consume(T_DDOT)) {
          $altn = $this->parse_expr_ops($subp, $allow_in, true);
        } else {
          $then = $this->parse_expr_ops($subp, true, true);
          $this->expect(T_DDOT);
          $altn = $this->parse_expr_ops($subp, $allow_in, true);
        }
        
        $lhs = new ast\CondExpr($loc, $lhs, $then, $altn);
      }
              
      // expr T_IS type_name
      // expr T_NIS type_name
      elseif ($peek->type === T_IS ||
              $peek->type === T_NIS) {
        $rhs = $this->parse_type_name();
        $lhs = new ast\CheckExpr($loc, $peek->type, $lhs, $rhs);
      } 
      
      // expr T_AS type_name
      elseif ($peek->type === T_AS) {
        $rhs = $this->parse_type_name();
        $lhs = new ast\CastExpr($loc, $lhs, $rhs);
      }
      
      // expr binop expr 
      else {           
        $rhs = $this->parse_expr_ops($subp, $allow_in, $allow_call);
        
        switch ($peek->type) {
          case T_ASSIGN:   case T_AREF:      case T_APLUS:    
          case T_AMINUS:   case T_ACONCAT:   case T_AMUL:     
          case T_ADIV:     case T_AMOD:      case T_APOW:
          case T_ABIT_OR:  case T_ABIT_AND:  case T_ABIT_XOR:
          case T_ABOOL_OR: case T_ABOOL_AND: case T_ABOOL_XOR:
          case T_ASHIFT_L: case T_ASHIFT_R:
            $this->check_lval($lhs);
            $lhs = new ast\AssignExpr($loc, $peek->type, $lhs, $rhs);
            break;
          default:
            $lhs = new ast\BinaryExpr($loc, $peek->type, $lhs, $rhs);
        }
      }
    }
    
    return $lhs;
  }
  
  /**
   * @return ast\Expr
   */
  protected function parse_primary_expr($allow_in = true, 
                                        $allow_call = true)
  {
    $peek = $this->peek();
    $expr = null;
    
    // prefix
    switch ($peek->type) {
      case T_INC:
      case T_DEC:
        $this->next();
        $expr = $this->parse_primary_expr($allow_in, $allow_call);
        $this->check_lval($expr);
        return new ast\UpdateExpr($peek->loc, true, $peek->type, $expr);
        
      case T_EXCL:
      case T_PLUS:
      case T_MINUS:
      case T_BIT_NOT:
      case T_BIT_AND:
        $this->next();           
        // note: do not recur in parse_primary_expr() here,
        // because there are operators with higher precedence 
        // than unary (e.g. T_POW)
        $prec = self::$op_table[T_EXCL]; // %prec '!'
        $expr = $this->parse_expr_ops($prec[2], $allow_in, $allow_call);
        return new ast\UnaryExpr($peek->loc, $peek->type, $expr);
    }
    
    // atom / subscripts
    $expr = $this->parse_member_expr($allow_in, $allow_call);
    
    // postfix
    for (;;) {
      $peek = $this->peek();
      
      if ($peek->type === T_INC ||
          $peek->type === T_DEC) {
        $this->next();
        $this->check_lval($expr);
        $expr = new ast\UpdateExpr($peek->loc, false, $peek->type, $expr);
      } else
        break;
    }
    
    return $expr;
  }
  
  /**
   * @param  bool $allow_in
   * @param  bool $allow_call
   * @return ast\MemberExpr|ast\OffsetExpr|ast\CallExpr
   */
  protected function parse_member_expr($allow_in = true,
                                       $allow_call = true)
  {
    $expr = $this->parse_expr_atom($allow_in, $allow_call);
    $loc = $expr->loc;
    
    for (;;) {
      // | member_expr '.' aid
      // | member_expr '.' '{' expr '}'
      if ($this->consume(T_DOT)) {
        if ($this->consume(T_LBRACE)) {
          $mkey = $this->parse_expr();
          $this->expect(T_RBRACE);
          $expr = new ast\MemberExpr($loc, true, $expr, $memx);
        } else {
          $mkey = $this->parse_ident(true);
          $expr = new ast\MemberExpr($loc, false, $expr, $mkey);
        }
      }
      
      // | member_expr '[' expr ']'
      elseif ($this->consume(T_LBRACKET)) {
        $moff = $this->parse_expr();
        $expr = new ast\OffsetExpr($loc, $expr, $moff);
      }
      
      // | member_expr pargs
      elseif ($allow_call && $this->consume(T_LPAREN)) {
        $args = $this->parse_arg_list(false);
        $expr = new ast\CallExpr($loc, $expr, $args);
      }
      
      else
        break;
    }
    
    return $expr;
  }
  
  /**   
   * @param  bool $allow_in
   * @param  bool $allow_call
   * @return Expr
   */
  protected function parse_expr_atom($allow_in = true,
                                     $allow_call = true)
  {
    $peek = $this->peek();
    
    $lex = $this->lex;
    $loc = $peek->loc;    
    $type = $peek->type;
    $value = $peek->value;
    
    switch ($type) {
      // literals #1 (some parsing needed)
      case T_DIV:      return $this->parse_rxp_lit();
      case T_STRING:   return $this->parse_str_lit();
      case T_LBRACKET: return $this->parse_arr_lit();
      case T_LPAREN:   return $this->parse_tup_lit();
      case T_LBRACE:   return $this->parse_obj_lit();
      
      // atomic expressions
      case T_FN:    return $this->parse_fn_expr();
      case T_NEW:   return $this->parse_new_expr($allow_in, $allow_call);
      case T_DEL:   return $this->parse_del_expr($allow_in, $allow_call);
      case T_YIELD: return $this->parse_yield_expr($allow_in, $allow_call);
      
      // names
      case T_SELF:
        $peek = $this->peek(2);
        if ($peek->type !== T_DDDOT) {
          $this->next();
          return new ast\SelfExpr($loc);
        }
        
        // fall through
        
      case T_IDENT:
      case T_DDDOT: return $this->parse_name();
      
      default:
        $this->next();
        
        switch ($type) {
          // literals #2 (no parsing needed)
          case T_LNUM:  return new ast\LnumLit($loc, $value);
          case T_DNUM:  return new ast\DnumLit($loc, $value);
          case T_TRUE:  return new ast\TrueLit($loc);
          case T_FALSE: return new ast\FalseLit($loc);
          case T_NULL:  return new ast\NullLit($loc);
          case T_THIS:  return new ast\ThisExpr($loc);
          case T_SUPER: return new ast\SuperExpr($loc);
          
          // special constants
          case T_CDIR:  return new ast\StrLit($loc, $lex->dir);
          case T_CFILE: return new ast\StrLit($loc, $lex->file);
          case T_CLINE: return new ast\LNumLit($loc, $loc->line);
          case T_CCOLN: return new ast\LNumLit($loc, $loc->coln);
          
          // engine constants
          case T_CFN:
          case T_CCLASS:
          case T_CTRAIT:
          case T_CMETHOD:
          case T_CMODULE: return new ast\EngineConst($loc, $type);
          
          // error
          default:
            $this->abort($peek);
        }
    }
  }
  
  /**
   * note: regular expressions are implemented with a lexer-hack.
   * 
   * @see Lexer#scan_regexp() 
   * @return ast\RegexpLit
   */
  protected function parse_rxp_lit()
  {
    $div = $this->expect(T_DIV);
    $tok = $this->lex->scan_regexp($div);
    return new ast\RegexpLit($div->loc, $tok->value);
  }
  
  /**
   * @return ast\StrLit
   */
  protected function parse_str_lit()
  {
    $tok = $this->expect(T_STRING);
    $node = new ast\StrLit($tok->loc, $tok->value);
    
    while ($this->consume(T_SUBST)) {
      if ($this->consume(T_LBRACE)) {
        $sub = $this->parse_expr();
        $this->expect(T_RBRACE);
      } else {
        $tok = $this->expect(T_STRING);
        $sub = new ast\StrLit($tok->loc, $tok->value);
      }
      
      $node->add($sub);
    }
    
    return $node;
  }
  
  /**
   * @return ast\ArrLit|ast\ArrGen
   */
  protected function parse_arr_lit()
  {
    $tok = $this->expect(T_LBRACKET);
    
    if ($this->consume(T_RBRACKET))
      return new ast\ArrLit($tok->loc, []);
    
    $item = $this->parse_expr();
    
    if ($this->consume(T_FOR)) {
      $this->expect(T_LPAREN);
      $id = $this->parse_ident();
      $this->expect(T_IN);
      $expr = $this->parse_expr();
      $this->expect(T_RPAREN);
      $this->expect(T_RBRACKET);
      return new ast\ArrGen($tok->loc, $item, $id, $expr);
    }
    
    $items = [ $item ];
    
    while ($this->consume(T_COMMA)) {
      $peek = $this->peek();
      
      // allow trailing comma
      if ($peek->type === T_RBACKET)
        break;
      
      $items[] = $this->parse_expr();
    }
    
    $this->expect(T_RBRACKET);
    return new ast\ArrLit($tok->loc, $items);
  }
  
  /**
   * @return ast\ParenExpr|ast\TupleExpr
   */
  protected function parse_tup_lit()
  {
    $tok = $this->expect(T_LPAREN);
    $item = $this->parse_expr();
    
    if ($this->consume(T_RPAREN))
      return new ast\ParenExpr($tok->loc, $item);
    
    $items = [ $item ];
    
    while ($this->consume(T_COMMA)) {
      $peek = $this->peek();
      
      // allow trailing comma
      if ($peek->type === T_RPAREN)
        break;  
      
      $items[] = $this->parse_expr();
    }
    
    $this->expect(T_RPAREN);
    return new ast\TupleExpr($tok->loc, $items);
  }
  
  /**
   * @return ast\ObjLit
   */
  protected function parse_obj_lit()
  {
    $tok = $this->expect(T_LBRACE);
    
    if ($this->consume(T_RBRACE))
      return new ast\ObjLit($tok->loc, []);
    
    $pair = $this->parse_obj_pair();
    $pairs = [ $pair ];
    
    while ($this->consume(T_COMMA)) {
      $peek = $this->peek();
      
      // allow trailing comma
      if ($peek->type === T_RBRACE)
        break;
      
      $pairs[] = $this->parse_obj_pair();
    }
    
    $this->expect(T_RBRACE);
    return new ast\ObjLit($tok->loc, $pairs);
  }
  
  /**
   * obj_pair
   *   : obj_key ':' expr
   *   ;
   *   
   * obj_key
   *   : ident
   *   | T_STRING
   *   | '(' expr ')'
   *   ;
   *
   * @return ast\ObjPair
   */
  protected function parse_obj_pair()
  {
    $peek = $this->peek();
    $key = null;
    
    if ($peek->type === T_STRING)
      $key = $this->parse_str_lit();
    elseif ($peek->type === T_LPAREN)
      $key = $this->parse_paren_expr();
    else
      $key = $this->parse_ident(true);
    
    $this->expect(T_DDOT);
    
    $val = $this->parse_expr();
    return new ast\ObjPair($peek->loc, $key, $val);
  }
  
  /**
   * @param  bool $allow_in
   * @param  bool $allow_call
   * @return ast\FnExpr
   */
  protected function parse_fn_expr($allow_in = true, 
                                   $allow_call = true)
  {
    $tok = $this->expect(T_FN);
    $peek = $this->peek();
    $name = null;
    
    if ($peek->type === T_IDENT)
      $name = $this->parse_ident();
    
    $param = $this->parse_fn_params();
    $hint = $this->parse_maybe_fn_hint();
    
    $this->enter(new FnScope);
    $body = $this->parse_fn_body();
    
    $node = new ast\FnExpr($name, $params, $hint, $body);
    $node->scope = $this->leave();
    return $node;
  }
  
  /**
   * @param  boolean $allow_in
   * @param  boolean $allow_call
   * @return ast\NewExpr
   */
  protected function parse_new_expr($allow_in = true,
                                    $allow_call = true)
  {
    $tok = $this->expect(T_NEW);
    $peek = $this->peek();
    $expr = null;
    
    if ($peek->type !== T_LPAREN)
      $expr = $this->parse_type_name();
    
    $args = $this->parse_arg_list();
    return new ast\NewExpr($tok->loc, $expr, $args);
  }
  
  /**
   * @param  boolean $allow_in
   * @param  boolean $allow_call
   * @return ast\DelExpr
   */
  protected function parse_del_expr($allow_in = true,
                                    $allow_call = true)
  {
    $tok = $this->expect(T_DEL);
    $expr = $this->parse_member_expr($allow_in, $allow_call);
    return new ast\DelExpr($tok->loc, $expr);
  }
  
  /**
   * @param  boolean $allow_in
   * @param  boolean $allow_call
   * @return ast\YieldExpr
   */
  protected function parse_yield_expr($allow_in = true,
                                      $allow_call = true)
  {
    $tok = $this->expect(T_YIELD);
    $expr = $this->parse_expr($allow_in, $allow_call);
    return new ast\YieldExpr($tok->loc, $expr);
  }
  
  /**
   * @return ast\Name
   */
  protected function parse_name()
  {
    $self = $this->consume(T_SELF);
    $root = null;
    
    if ($self === null)
      $root = $this->consume(T_DDDOT);
    else
      $this->expect(T_DDDOT);
    
    $brid = $this->peek(2)->type === T_DDDOT;
    $base = $this->parse_ident($brid);
    
    $node = new ast\Name(
      ($self ? $self->loc : ($root ? $root->loc : $base->loc)),
      $base, $root !== null, $self !== null
    );
    
    while ($this->consume(T_DDDOT)) {
      $id = $this->parse_ident(true);
      $node->add($id);
    }
    
    return $node;
  }
  
  /**
   * @return ast\TypeName
   */
  protected function parse_type_name()
  {
    $name = $this->parse_type();
    $gens = [];
    while ($this->consume(T_LBRACE)) {
      $gens[] = $this->parse_type();
      $this->expect(T_RBRACE);
    }
    return new ast\TypeName($name->loc, $name, $gens);
  }
  
  /**
   * @return ast\TypeId|ast\Name
   */
  protected function parse_type()
  {
    $peek = $this->peek();
    switch ($peek->type) {
      case T_TINT: case T_TFLOAT:
      case T_TTUP: case T_TBOOL:
      case T_TSTR: case T_TDEC:
        $tok = $this->next();
        return new ast\TypeId($tok->loc, $tok);
      default:
        return $this->parse_name();
    }
  }
  
  /**
   * @param  boolean $allow_rid
   * @return ast\Ident
   */
  protected function parse_ident($allow_rid = false)  
  {
    $peek = $this->peek();
    
    if (($peek->type === T_IDENT) || 
        ($allow_rid && Lexer::is_rid($peek))) {
      $this->next();
      return new ast\Ident($peek->loc, $peek->value);
    }
    
    $this->expect(T_IDENT);
  }
}
