<?php

/* recursive descent version of the phs-parser
   this file is not longer maintained */
   
/* see: yy-parser.php */

namespace phs;

exit('use yy-parser.php');

const INVALID_NODE = null;

// flag used in expressions
const NO_IN = true;

function UNIMPLEMENTED($kind) {
  throw new \RuntimeException("$kind is not implemented");
}

// node
class Node
{
  public $loc;
  public $kind;
  
  public function __construct($loc, $kind)
  {
    $this->loc = $loc;
    $this->kind = $kind;
  }
}

// operator
class Operator
{
  public $prec;
  public $logical;
  
  public function __construct($prec, $logical)
  {
    $this->prec = $prec;
    $this->logical = $logical;
  }
}

class Parser
{
  // compiler context
  private $ctx;
  
  // lexer
  private $lex;
  
  // a managed location for error-messages
  private $loc;
  
  // stack
  private $stack = [];
  
  // sync tokens
  private static $sync_tok = [
    TOK_SEMI,
    TOK_RBRACE,
    TOK_RPAREN
  ];
  
  // sync keywords
  private static $sync_rid = [
    RID_FN,
    RID_LET,
    RID_ENUM,
    RID_CLASS,
    RID_IFACE,
    RID_TRAIT,
    RID_FOR,
    RID_DO,
    RID_IF,
    RID_FOR,
    RID_TRY,
    RID_PHP,
    RID_GOTO,
    RID_TEST,
    RID_BREAK,
    RID_CONTINUE,
    RID_THROW,
    RID_SUPER,
    RID_WHILE,
    RID_YIELD,
    RID_ASSERT,
    RID_SWITCH,
    RID_FOREACH,
    RID_REQUIRE,
    RID_PUBLIC,
    RID_PRIVATE,
    RID_PROTECTED, 
    RID_STATIC,
    RID_FINAL,
    RID_CONST,
    RID_EXTERN,
    RID_GLOBAL
  ];
  
  // debug mode
  private $debug = false;
  
  // operator table
  private static $optable;
  
  /**
   * initialize parser
   * 
   * @param Context $ctx the context
   */
  public function __construct(Context $ctx)
  {
    $this->ctx = $ctx;
  }
  
  /**
   * parses a file
   * 
   * @param  string $file the file
   * @return Node
   */
  public function process_file($file)
  {
    $path = realpath($file);
    
    if (!is_file($file) || !is_readable($path)) {
      $this->error(ERR_ERROR, 'access denied to read file %s', $file);
      return null;
    }
    
    $data = file_get_contents($path);
    return $this->process_text($data, $path);
  }
  
  /**
   * parses text
   * 
   * @param  string $text the text to be parsed
   * @param  string $file (optional) the name of its source
   * @return Node
   */
  public function process_text(&$text, $file = 'unknown source')
  {
    array_push($this->stack, $this->lex);
    
    $this->lex = new Lexer($this->ctx, $text, $file);
    $root = $this->parse_unit();
    
    unset ($this->lex);
    $this->lex = array_pop($this->stack);
    
    return $root;
  }
  
  /**
   * error handler
   * 
   */
  public function error()
  {
    $args = func_get_args();
    $lvl = array_shift($args);
    $msg = array_shift($args);
    
    $this->ctx->verror_at($this->get_loc(), COM_PSR, $lvl, $msg, $args);
  }
  
  /**
   * error handler with custom location
   * 
   */
  public function error_at()
  {
    $args = func_get_args();    
    $loc = array_shift($args);
    $lvl = array_shift($args);
    $msg = array_shift($args);
    
    $this->ctx->verror_at($loc, COM_PSR, $lvl, $msg, $args);
  }
  
  /**
   * getter for $loc
   * 
   * @return Location
   */
  public function get_loc()
  {
    $loc = $this->loc;
        
    if ($loc === null) {
      $peek = $this->lex->peek();
      
      if ($peek === INVALID_TOKEN)
        $loc = new Location('unknown', new Position(0, 0));
      else
        $loc = $peek->loc;
    }
    
    return $loc;
  }
  
  /**
   * setter for $loc
   * 
   * @param Location $loc
   */
  public function set_loc(Location $loc = null)
  {
    if ($loc === null) {
      $this->loc = null;
      $this->get_loc();
    } else
      $this->loc = $loc;
  }
  
  /* ------------------------------------ */
  
  /**
   *  unit
   *    : maybe_module
   *    ;
   *    
   * @return Node
   */
  protected function parse_unit()
  {
    if ($this->debug) print "in parse_unit()\n";
  
    $unit = $this->node('unit');
    $unit->body = $this->parse_maybe_module();
    
    return $unit;
  }
  
  /**
   *  maybe_module
   *    : LA(RID_MODULE) module
   *    | program
   *    ;
   *    
   *  RID_MODULE = "module"
   *    
   * @return Node
   */
  protected function parse_maybe_module()
  {
    if ($this->debug) print "in parse_maybe_module()\n";
  
    $peek = $this->peek_keyword();
    
    if ($peek === RID_MODULE)
      return $this->parse_module();
  
    return $this->parse_program();
  }
  
  /**
   *  module
   *    : nested_module
   *    | document_module
   *    ;
   *    
   *  nested_module
   *    : RID_MODULE module_name '{' maybe_nested_module '}'
   *    ;
   *   
   *  document_module
   *    : RID_MODULE module_name ';' [ program ]
   *    ;
   *    
   *  RID_MODULE = "module"
   *  
   * @return Node
   */
  protected function parse_module()
  {
    if ($this->debug) print "in parse_module()\n";
  
    if (!$this->expect_keyword(RID_MODULE))
      goto err;
    
    $node = $this->node('module_decl');
    
    // variant 1: "module" '{' [ module_or_program ] '}'
    if ($this->consume_token(TOK_SEMI)) {
      if (!$this->consume_token(TOK_LBRACE))
        goto err;
      
      $node->name = null;
      $node->body = $this->parse_maybe_nested_module();
      
      if (!$this->consume_token(TOK_RBRACE))
        goto err;
      
      goto out;
    }
    
    $node->name = $this->parse_module_name();
    
    // variant 2: "module" module_name ';' [ program ] 
    if ($this->consume_token(TOK_SEMI)) {
      $node->body = $this->parse_program();
      goto out;
    }
    
    // variant 3: "module" module_name '{' [ module_or_program ] '}'
    if (!$this->consume_token(TOK_LBRACE))
      goto err;
    
    $node->body = $this->parse_maybe_nested_module();
    
    if (!$this->consume_token(TOK_RBRACE))
      goto err;
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    $this->skip_to_sync_token(); 
    
    out:
    return $node;
  }
  
  /**
   *  maybe_nested_module
   *    : LA(RID_MODULE) nested_module
   *    | program
   *  ;
   *  
   *  RID_MODULE = "module"
   *    
   * @return Node
   */
  protected function parse_maybe_nested_module()
  {
    if ($this->debug) print "in parse_maybe_nested_module()\n";
  
    $peek = $this->peek_keyword();
    
    if ($peek === RID_MODULE)
      return $this->parse_nested_module();
    
    return $this->parse_program();
  }
  
  /**
   *  nested_module
   *    : RID_MODULE module_name? '{' [ maybe_nested_module ] '}'
   *    ;
   *    
   *  RID_MODULE = "module"
   *    
   * @return Node
   */
  protected function parse_nested_module()
  {
    if ($this->debug) print "in parse_nested_module()\n";
  
    if (!$this->expect_keyword(RID_MODULE))
      goto err;
    
    $node = $this->node('nested_module');
    $node->name = null;
    
    if ($this->peek_token() !== TOK_LBRACE)
      $node->name = $this->parse_module_name();
    
    if (!$this->expect_token(TOK_LBRACE))
      goto err;
    
    $node->body = $this->parse_maybe_nested_module();
    
    if (!$this->expect_token(TOK_BRACE))
      goto err;
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    $this->skip_to_sync_token();
    
    out:
    return $node;
  }
  
  /**
   *  program
   *    : [ maybe_attribute_decl ]
   *    | empty
   *    ;
   *    
   * @return Node
   */
  protected function parse_program()
  {
    if ($this->debug) print "in parse_program()\n";
  
    $node = $this->node('program');
    $node->body = [];
    
    $seen_info = false;
    
    for (;;) {
      if ($this->at_eof_or_aborted())
        goto out;
      
      $decl = $this->parse_maybe_attribute_decl();
      $this->consume_semis();
      
      if ($decl === INVALID_NODE && !$seen_info) {
        $this->error(ERR_INFO, 'parser is now in validation-only mode');
        $seen_info = true;
      }
      
      $node->body[] = $decl;
    }
    
    out:
    return $node;
  }
  
  /**
   *  maybe_attribute_decl
   *    : LA(TOK_AT) attribute_decl
   *    | maybe_modified_decl
   *    ;
   *    
   *  TOK_AT = "@"
   *    
   * @return Node
   */
  protected function parse_maybe_attribute_decl()
  {
    if ($this->debug) print "in parse_maybe_attribute_decl()\n";
  
    $peek = $this->peek_token();
    
    if ($peek === TOK_AT)
      return $this->parse_attribute_decl();
    
    return $this->parse_maybe_modified_decl();
  }
  
  /**
   *  attribute_decl
   *    : '@' !' attributes
   *    | '@' attributes maybe_modified_decl
   *    ;
   *    
   * @return Node
   */
  protected function parse_attribute_decl()
  {
    if ($this->debug) print "in parse_attribute_decl()\n";
  
    if (!$this->expect_token(TOK_AT))
      goto err;
    
    $flag = $this->consume_token(TOK_SEMI);
    $attr = $this->parse_attributes();
    
    if ($attr === INVALID_NODE)
      goto err;
    
    $node = $this->node('attribute_decl');
    $node->attr = $attr;
    
    if ($flag === true)
      goto out;
    
    $node->decl = $this->parse_maybe_modified_decl(false);
    
    if ($node->decl === INVALID_NODE)
      goto err;
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    $this->skip_to_sync_token();
    
    out:
    return $node;
  }
  
  /**
   *  attributes
   *    : attribute
   *    | attributes ',' attribute
   *    ;
   *    
   * @return array
   */
  protected function parse_attributes()
  {
    if ($this->debug) print "in parse_attributes()\n";
  
    $list = [];
    
    for (;;) {
      $attr = $this->parse_attribute();
      
      if ($attr === INVALID_NODE)
        goto err;
      
      $list[] = $attr;
      $peek = $this->peek_token();
      
      if ($peek !== TOK_COMMA)
        goto out;
    }
    
    err:
    $list = INVALID_NODE;
    $this->skip_to_sync_token();
    
    out:
    return $list;
  }
  
  /**
   *  attribute
   *    : ident
   *    | ident '=' literal
   *    | ident '(' attribute? ')'
   *    ;
   *    
   * @return [type] [description]
   */
  protected function parse_attribute()
  {
    if ($this->debug) print "in parse_attribute()\n";
  
    $name = $this->parse_ident();
    
    if ($name === INVALID_NODE)
      goto err;
    
    $node = $this->node('attribute');
    $node->name = $name;
    
    if ($this->consume_token(TOK_LPAREN)) {
      $node->value = null;
      $peek = $this->peek_token();
      
      if ($peek !== TOK_RPAREN)
        $node->value = $this->parse_attribute();
      
      if (!$this->consume_token(TOK_RPAREN))
        goto err;
      
      goto out;
    }
    
    if ($this->consume_token(TOK_ASSIGN))
      $node->value = $this->parse_literal();
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  /**
   *  maybe_modified_decl
   *    : modifiers modified_decl
   *    | decl
   *    ;
   *    
   * @return Node
   */
  protected function parse_maybe_modified_decl()
  {
    if ($this->debug) print "in parse_maybe_modified_decl()\n";
  
    $mods = $this->parse_modifiers();
    
    if ($mods === INVALID_NODE)
      return $this->parse_decl();
    
    $decl = $this->parse_modified_decl();
    $decl->loc = $mods->loc;
    $decl->modifiers = $mods;
    
    return $decl;
  }
  
  /**
   *  modifiers
   *    : modifier
   *    | modifiers modifier
   *    ;
   * 
   * @param  bool  $allow_access allow public|private|protected modifiers
   * @return array
   */
  protected function parse_modifiers($ac = false)
  {
    if ($this->debug) print "in parse_modifiers()\n";
  
    $list = [];
    $test = [];
    $fmod = $this->parse_modifier($ac);
    
    if ($fmod === INVALID_NODE)
      goto err;
    
    $list[] = $fmod;
    $test[] = $fmod->modifier;
    
    for (;;) {
      $nmod = $this->parse_modifier($ac);
            
      if ($nmod === INVALID_NODE)
        goto out;
      
      if (in_array($nmod->modifier, $test))
        goto err;
      
      $list[] = $nmod;
      $test[] = $nmod->modifier;
    }
    
    goto out;
    
    err:
    $list = INVALID_NODE;
    
    out:
    return $list;
  }
  
  /**
   *  modifier
   *    : RID_CONST
   *    | RID_FINAL
   *    | RID_STATIC
   *    | RID_EXTERN
   *    | RID_GLOBAL
   *    | RID_PUBLIC
   *    | RID_PRIVATE
   *    | RID_PROTECTED
   *    ;
   *    
   *  RID_CONST = "const"
   *  RID_FINAL = "final"
   *  RID_STATIC = "static"
   *  RID_EXTERN = "extern"
   *  RID_GLOBAL = "global"
   *  RID_PUBLIC = "public"
   *  RID_PRIVATE = "private"
   *  RID_PROTECTED = "protected"
   *    
   * @return Node
   */
  protected function parse_modifier($ac)
  {
    if ($this->debug) print "in parse_modifier(\$ac = ".($ac?'true':'false').")\n";
  
    $peek = $this->peek_keyword();
    
    switch ($peek) {
      case RID_CONST:
      case RID_FINAL:
      case RID_STATIC:
      case RID_EXTERN:
      case RID_GLOBAL:
        goto out;
      
      case RID_PUBLIC:
      case RID_PRIVATE:
      case RID_PROTECTED:
        if ($ac === false)
          goto err;
        
        goto out;
      
      default:
        goto err;
    }
    
    err:
    return INVALID_NODE;
    
    out:
    $node = $this->node('modifier');
    $node->modifier = $peek;
    $this->skip_token();
    
    return $node;
  }
  
  /**
   *  modified_decl
   *    : var_decl
   *    | decl
   *    ;
   *    
   * @return Node
   */
  protected function parse_modified_decl()
  {
    if ($this->debug) print "in parse_modified_decl()\n";
  
    $peek = $this->peek_token();
    
    if ($peek->type === TOK_NAME) {
      $node = $this->parse_var_decl();
      goto out;
    }
    
    $node = $this->parse_decl();
        
    out:
    return $node;
  }
  
  /**
   *  decl
   *    : fn_decl
   *    | let_decl
   *    | enum_decl
   *    | class_decl
   *    | trait_decl
   *    | iface_decl
   *    | inner_decl
   *    ;
   *  
   * @param boolean $allow_stmt
   * @return Node
   */
  protected function parse_decl($as = true)
  {
    if ($this->debug) print "in parse_decl()\n";
  
    $peek = $this->peek_keyword();
    
    switch ($peek) {
      case RID_USE:
        return $this->parse_use_decl();
      case RID_CLASS:
        return $this->parse_class_decl();
      case RID_TRAIT:
        return $this->parse_trait_decl();
      case RID_IFACE:
        return $this->parse_iface_decl();
    }
    
    return $this->parse_inner_decl($as);
  }
  
  /**
   *  use_decl
   *    : RID_USE simple_usage ';'
   *    | RID_USE nested_usage ';'?
   *    ;
   *    
   *  simple_usage
   *    : module_name
   *    | module_name RID_AS ident
   *    ;
   *    
   *  nested_usage
   *    : module_name '&'? '{' inner_usage ( ',' inner_usage )* '}'
   *    ;
   *    
   *  inner_usage
   *    : simple_usage
   *    | nested_usage
   *    ;
   *    
   *  RID_USE = "use"
   *  RID_AS = "as"    
   *      
   * @return Node
   */
  protected function parse_use_decl()
  {
    if ($this->debug) print "in parse_use_decl()\n";
  
    if (!$this->expect_keyword(RID_USE))
      goto err;
    
    $node = $this->node('use_decl');
    $node->usage = $this->parse_maybe_nested_inner_use();
    
    if ($node->usage == INVALID_NODE)
      goto err;
    
    if ($node->usage->nested === false &&
        !$this->expect_token(TOK_SEMI))
      goto err;
      
    $this->consume_semis();
    goto out;
    
    err:
    $node = INVALID_NODE;
    $this->skip_to_sync_token();
    
    out:
    return $node;
  }
  
  /**
   * see parse_use_decl()
   * 
   * @return Node
   */
  protected function parse_maybe_nested_inner_use()
  {
    if ($this->debug) print "in parse_maybe_nested_inner_use()\n";
  
    $node = $this->node('usage');
    $node->base = $this->parse_module_name();
    
    if ($node->base === INVALID_NODE)
      goto err;
    
    if ($this->consume_keyword(RID_AS)) {
      $node->alias = $this->parse_ident();
      
      if ($node->alias === INVALID_NODE)
        goto err;
      
    } else {
      $incl = $this->consume_token(TOK_BIT_AND);
      $peek = $this->peek_token();
      
      if ($incl || $peek === TOK_LBRACE) {
        $node->nested = true;
        $node->usage = [];
        $node->incl = $incl;
        
        if (!$this->expect_token(TOK_LBRACE))
          goto err;
        
        for (;;) {
          $usage = $this->parse_maybe_nested_inner_use();
          
          if ($usage === INVALID_NODE) {
            $this->skip_to_brace();
            break;
          }
            
          $node->usage[] = $usage;
          
          if (!$this->consume_token(TOK_COMMA))
            break;
        }
        
        if (!$this->expect_token(TOK_RBRACE))
          goto err;
      }
    }
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  /**
   *  fn_decl
   *    : RID_FN ident fn_params fn_body
   *    ;
   *    
   *  RID_FN = "fn"
   *    
   * @param boolean $allow_this_params
   * @return Node
   */
  protected function parse_fn_decl($atp = false)
  {
    if ($this->debug) print "in parse_fn_decl()\n";
  
    if (!$this->expect_keyword(RID_FN))
      goto err;
    
    $node = $this->node('fn_decl');
    $node->id = $this->parse_ident();
    
    if ($node->id === INVALID_NODE) {
      $this->error(ERR_ERROR, 'function declaration requires a name');
      goto err;
    }
    
    $node->params = $this->parse_fn_params($atp);
    
    if ($node->params === INVALID_NODE)
      goto err;
    
    $node->body = $this->parse_fn_body();
    
    if ($node->body === INVALID_NODE)
      goto err;
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    $this->skip_to_sync_rid();
    
    out:
    return $node;
  }
  
  /**
   *  fn_params
   *    : fn_param
   *    | fn_params ',' fn_param
   *    ;
   * 
   * @param boolean $allow_this_params
   * @return Node
   */
  protected function parse_fn_params($atp = false)
  {
    if ($this->debug) print "in parse_fn_params()\n";
  
    if (!$this->expect_token(TOK_LPAREN))
      goto err;
    
    $node = $this->node('fn_params');
    $node->rest = null;
    $node->list = [];
    
    if ($this->consume_token(TOK_RPAREN))
      goto out;
    
    $has_rest = false;
    $has_fall = false;
    $error = false;
    
    for (;;) {
      $param = $this->parse_fn_param($atp);
      
      if ($param === INVALID_NODE) {
        $this->skip_to_comma_or_paren();
        $error = true;
      } else {
        if ($param->kind === 'rest_param') {        
          $has_rest = true;
          $node->rest = $param;
          
          // break here, because more parameters are not allowed
          // the TOK_RPAREN will handle this
          break;
        }
        
        if ($param->fallback === null && $has_fall)
          $this->error_at($param->loc, ERR_WARN, 
            'required parameter after optional parameter');
          
        if ($param->fallback !== null)
          $has_fall = true;
          
        $node->list[] = $param;
      }
      
      if (!$this->consume_token(TOK_COMMA))
        break;
    }
    
    if (!$this->expect_token(TOK_RPAREN)) {
      if ($has_rest === true)
        $this->error(ERR_INFO, 'rest parameter must be at the end');
      
      goto err;
    }
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  /**
   *  fn_param
   *    : ident
   *    | ident '=' expr_no_comma
   *    | TOK_REST ident
   *    | RID_THIS '.' ident
   *    | RID_THIS '.' ident '=' expr_no_comma
   *    ;
   *    
   *  TOK_REST = "..."
   *  RID_THIS = "this"
   *
   * @param boolean $allow_this_params
   * @return Node
   */
  protected function parse_fn_param($atp = false)
  {
    if ($this->debug) print "in parse_fn_param()\n";
  
    $node = null;
    
    if ($this->consume_token(TOK_REST)) {
      $node = $this->node('rest_param');
      $node->id = $this->parse_ident();
      
      if ($node->id === INVALID_NODE)
        goto err;
      
      goto out;
    }
    
    if ($atp === true && $this->consume_keyword(RID_THIS)) {
      if (!$this->consume_token(TOK_DOT))
        goto err;
      
      $node = $this->node('this_param');
      $node->id = $this->parse_ident();
      
      if ($node->id === INVALID_NODE)
        goto err;
      
      goto cnt;
    }
    
    $node = $this->node('param');
    $node->id = $this->parse_ident();
    
    if ($node->id === INVALID_NODE)
      goto err;
    
    cnt:
    $node->fallback = null;
    
    if ($this->consume_token(TOK_ASSIGN)) {
      $node->fallback = $this->parse_expr_no_comma();
      
      if ($node->fallback === INVALID_NODE)
        goto err;
    }
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  /**
   *  fn_body
   *    : block
   *    | TOK_ARR expr_no_comma ';'
   *    ;
   *    
   * TOK_ARR = "=>"
   * 
   * @return Node
   */
  protected function parse_fn_body()
  {
    if ($this->debug) print "in parse_fn_body()\n";
  
    if ($this->consume_token(TOK_ARR)) {
      $node = $this->parse_expr_no_comma();
      
      if ($node === INVALID_NODE)
        goto err;
      
      if (!$this->expect_token(TOK_SEMI))
        goto err;
      
      goto out;
    }
    
    $node = $this->parse_block_stmt();
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  /**
   *  let_decl
   *    : let_decl_no_semi ';'
   *    ;
   * 
   * @param boolean $no_in
   * @return Node
   */
  protected function parse_let_decl($ni = false)
  {
    if ($this->debug) print "in parse_let_decl()\n";
  
    $node = $this->parse_let_decl_no_semi($ni);
    
    if ($node === INVALID_NODE)
      goto err;
    
    if (!$this->expect_token(TOK_SEMI))
      goto err;
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  /**
   *  let_decl_no_semi
   *    : RID_LET var_decl_no_semi
   *    ;
   *    
   *  RID_LET = "let"
   *  
   * @param boolean $no_in
   * @return Node
   */
  protected function parse_let_decl_no_semi($ni = false)
  {
    if ($this->debug) print "in parse_let_decl_no_semi()\n";
  
    if (!$this->expect_keyword(RID_LET))
      goto err;
    
    $node = $this->node('let_decl');
    $vars = $this->parse_var_decl_no_semi($ni);
    
    if ($vars === INVALID_NODE)
      goto err;
    
    $node->vars = $vars;
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  /**
   *  var_decl
   *    : var_decl_no_semi ';'
   *    ;
   * 
   * @param boolean $no_in
   * @return Node
   */
  protected function parse_var_decl($ni = false)
  {
    if ($this->debug) print "in parse_var_decl()\n";
  
    $vars = $this->parse_var_decl_no_semi($ni);
    
    if ($vars === INVALID_NODE)
      goto err;
    
    if (!$this->expect_token(TOK_SEMI))
      goto err;
    
    goto out;
    
    err:
    $vars = INVALID_NODE;
    
    out:
    return $vars;
  }
  
  /**
   *  var_decl_no_semi
   *    : var_item ( ',' var_item )*
   *    ;
   *    
   * @param boolean $no_in
   * @return Node
   */
  protected function parse_var_decl_no_semi($ni)
  {
    if ($this->debug) print "in parse_var_decl_no_semi()\n";
  
    $node = $this->node('var_decl');
    $node->list = [];
    
    for (;;) {
      $item = $this->parse_var_item($ni);
      
      if ($item === INVALID_NODE)
        goto err;
      
      $node->list[] = $item;
      
      if (!$this->consume_token(TOK_COMMA))
        goto out;
    }
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  /**
   *  var_item
   *    : var_pattern ( '=' expr_no_comma )?
   *    ;
   * 
   * @param boolean $no_in 
   * @return Node
   */
  protected function parse_var_item($ni = false)
  {
    if ($this->debug) print "in parse_var_item()\n";
  
    $node = $this->node('var_item');
    $node->pattern = $this->parse_var_pattern();
    $node->init = null;
    
    if ($node->pattern === INVALID_NODE)
      goto err;
    
    if ($this->consume_token(TOK_ASSIGN)) {
      $node->init = $this->parse_expr_no_comma($ni);
      
      if ($node->init === INVALID_NODE)
        goto err;
    }
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  /**
   *  var_pattern
   *    : ident
   *    | '{' var_pattern ( ',' var_pattern )* '}'
   *    | '[' var_pattern ( ',' var_pattern )* ']'
   *    ;
   *    
   * @return Node
   */
  protected function parse_var_pattern()
  {
    if ($this->debug) print "in parse_var_pattern()\n";
  
    $peek = $this->peek_token();
    
    if ($peek === TOK_NAME)
      return $this->parse_ident();
    
    $node = $this->node('var_pattern');
    $node->items = [];
    
    $endt = INVALID_TOKEN;
    
    switch ($peek) {
      case TOK_LBRACE:
        $endt = TOK_RBRACE;
        break;
      case TOK_LBRACKET:
        $endt = TOK_RBRACKET;
        break;
    }
    
    if ($endt === INVALID_TOKEN) {
      $this->error(ERR_ERROR, 'syntax error, expected %token, %token or %token',
        TOK_NAME, TOK_LBRACE, TOK_LBRACKET);
      goto err;
    }
    
    $this->skip_token();
    
    for (;;) {
      $item = $this->parse_var_pattern();
      
      if ($item === INVALID_NODE)
        goto err;
      
      $node->items[] = $item;
      
      if (!$this->consume_token(TOK_COMMA))
        break;
    }
    
    if (!$this->expect_token($endt))
      goto err;
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  /**
   *  enum_decl
   *    : RID_ENUM enum_body
   *    ;
   *    
   *  RID_ENUM = "enum"
   *  
   * @return Node
   */
  protected function parse_enum_decl()
  {
    if ($this->debug) print "in parse_enum_decl()\n";
  
    if (!$this->epxect_keyword(RID_ENUM))
      goto err;
    
    $node = $this->node('enum_decl');
    $node->vars = $this->parse_enum_body();
    
    if ($node->body === INVALID_NODE)
      goto err;
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  /**
   *  enum_body
   *    : '{' [ enum_item [ ',' enum_item ]* ]? '}'
   *    ;
   *    
   * @return array
   */
  protected function parse_enum_body()
  {
    if ($this->debug) print "in parse_enum_body()\n";
  
    if (!$this->expect_token(TOK_LBRACE))
      goto err;
    
    $list = [];
    
    if ($this->consume_token(TOK_RBRACE))
      goto out;
    
    for (;;) {
      $item = $this->parse_enum_item();
      
      if ($item === INVALID_NODE)
        goto err;
        
      $list[] = $item;
      
      if (!$this->consume_token(TOK_COMMA))
        goto out;  
    }
    
    err:
    $list = INVALID_NODE;
    
    out:
    return $list;
  }
  
  /**
   *  enum_item
   *    : ident
   *    | ident '=' expr_no_comma
   *    ;
   *    
   * @return Node
   */
  protected function parse_enum_item()
  {
    if ($this->debug) print "in parse_enum_item()\n";
  
    $node = $this->node('enum_item');
    $node->id = $this->parse_ident();
    $node->init = null;
    
    if ($node->id === INVALID_NODE)
      goto err;
    
    if ($this->consume_token(TOK_ASSIGN)) {
      $node->init = $this->parse_expr_no_comma();
      
      if ($node->init === INVALID_NODE)
        goto err;
    }
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  /**
   *  class_decl
   *    : RID_CLASS ident class_base? class_impl? class_body
   *    ;
   *    
   *  class_base
   *    : ':' module_name
   *    ;
   *    
   *  class_impl
   *    : '<' module_name () ',' module_name )*
   *    ;
   *   
   *  RID_CLASS = "class"
   *  
   * @return Node
   */
  protected function parse_class_decl()
  {
    if ($this->debug) print "in parse_class_decl()\n";
  
    if (!$this->expect_keyword(RID_CLASS))
      goto err;
    
    $node = $this->node('class_decl');
    $node->id = $this->parse_ident();
    $node->base = null;
    $node->impl = null;
    $node->body = null;
    
    if ($node->id === INVALID_NODE)
      goto err;
    
    // class_base
    if ($this->consume_token(TOK_DDOT)) {
      $node->base = $this->parse_module_name();
      
      if ($node->base === INVALID_NODE)
        goto err;
    }
    
    // class_impl
    if ($this->consume_token(TOK_LT)) {
      $node->impl = [];
      
      for (;;) {
        $item = $this->parse_module_name();
        
        if ($item === INVALID_NODE)
          goto err;
        
        $node->impl[] = $item;
        $peek = $this->peek_token();
        
        if ($peek === TOK_SEMI || $peek === TOK_LBRACE)
          break;
      }
    }
    
    // class_body
    if (!$this->consume_token(TOK_SEMI)) {
      $node->body = $this->parse_class_body();
      
      if ($node->body === INVALID_NODE)
        goto err;
    }
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    $this->skip_to_sync_token();
    
    out:
    return $node;
  }
  
  /**
   *  class_body
   *    : '{' class_item* '}'
   *    | ';' // not handled here
   *    ;
   *    
   * @return Node
   */
  protected function parse_class_body()
  {
    if ($this->debug) print "in parse_class_body()\n";
  
    if (!$this->expect_token(TOK_LBRACE))
      goto err;
    
    $list = [];
    
    for (;;) {
      if ($this->at_eof_or_aborted())
        goto err;
      
      if ($this->consume_token(TOK_RBRACE))
        goto out;
      
      $item = $this->parse_class_item();
      
      if ($item === INVALID_NODE)
        continue;
      
      $list[] = $item;
    }
    
    goto out;
    
    err:
    $list = INVALID_NODE;
    $this->skip_to_sync_token();
    
    out:
    return $list;
  }
  
  /**
   *  class_item
   *    : LA(RID_ENUM) class_enum_decl
   *    | LA(RID_USE) class_trait_usage
   *    | maybe_modified_class_memeber
   *    ;
   *    
   *  RID_ENUM = "enum"
   *  RID_USE = "use"
   *    
   * @return Node
   */
  protected function parse_class_item()
  {
    if ($this->debug) print "in parse_class_item()\n";
  
    $peek = $this->peek_keyword();
    
    switch ($peek) {
      case RID_USE:
        return $this->parse_class_trait_usage();
      case RID_ENUM:
        return $this->parse_class_enum_decl();
      default:
        return $this->parse_maybe_modified_class_member();
    }
  }
  
  /**
   *  class_trait_usage
   *    : RID_USE class_trait_usage_body
   *    ;
   *    
   *  class_trait_usage_body
   *    : class_trait_usage_item
   *    | '{' class_trait_usage_item* '}'
   *    ;
   *    
   *  RID_USE = "use"
   *    
   * @return Node
   */
  protected function parse_class_trait_usage()
  {
    if ($this->debug) print "in parse_class_trait_usage()\n";
  
    if (!$this->expect_keyword(RID_USE))
      goto err;
    
    $node = $this->node('class_trait_usage');
    $node->usage = null;
    
    // trait-name
    $node->subject = $this->parse_module_name();
    
    if ($node->subject === INVALID_NODE)
      goto err;
    
    // no special usage
    if ($this->consume_token(TOK_SEMI))
      goto out;
    
    // usage
    $node->usage = [];
    
    if ($this->consume_token(TOK_LBRACE)) {
      // extended usage
      // TDOD: is `use trait_name { }` allowed?
      for (;;) {
        if ($this->consume_token(TOK_RBRACE))
          goto out; // or err?
        
        $item = $this->parse_class_trait_usage_decl();
        
        if ($item === INVALID_NODE)
          goto err;
        
        $node->usage[] = $item;
      } 
    } else {
      // simple usage
      $item = $this->parse_class_trait_usage_decl();
      
      if ($item === INVALID_NODE)
        goto err;
      
      $node->usage[] = $item;
    }
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  /**
   *  class_trait_usage_decl
   *    : maybe_modified_usage_item ';'
   *    | maybe_modified_usage_item RID_AS maybe_modified_usage_item ';'
   *    ;
   *    
   *  RID_AS = "as"
   *    
   * @return Node
   */
  protected function parse_class_trait_usage_decl()
  {
    if ($this->debug) print "in parse_class_trait_usage_decl()\n";
  
    $lhs = $this->parse_maybe_modified_usage_item();
    
    if ($lhs === INVALID_NODE)
      goto err;
    
    $node = $this->node_at($lhs->loc, 'usage_decl');
    $node->item = $lhs;
    $node->alias = null;
    
    if ($this->consume_token(TOK_SEMI))
      goto out;
    
    if (!$this->expect_keyword(RID_AS))
      goto err;
    
    $rhs = $this->parse_maybe_modified_usage_item();
    
    if ($rhs === INVALID_NODE)
      goto err;
    
    $node->alias = $rhs;
    
    if (!$this->consume_token(TOK_SEMI))
      goto err;
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  /**
   *  maybe_modified_usage_item
   *    : modifiers? usage_item 
   *    ;
   *  
   * @return Node
   */
  protected function parse_maybe_modified_usage_item()
  {
    if ($this->debug) print "in parse_maybe_modified_usage_item()\n";
  
    $mods = $this->parse_modifiers(true);
    $item = $this->parse_usage_item();
    
    if ($mods !== INVALID_NODE) {
      $item->loc = $mods->loc;
      $item->modifiers = $mods;
    }
    
    return $item;
  }
  
  /**
   *  usage_item
   *    : usage_keyword? ident
   *    ;
   *    
   *  usage_keyword
   *    : RID_FN
   *    | RID_LET
   *    ;
   *    
   *  RID_FN = "fn"
   *  RID_LET = "let"
   *    
   * @return Node
   */
  protected function parse_usage_item()
  {
    if ($this->debug) print "in parse_usage_item()\n";
  
    // allow "fn" and "let"
    $has_fn = false;
    
    if (($has_fn = $this->consume_keyword(RID_FN)))
      $this->error(ERR_INFO, 'keyword `fn` is not required in a trait-usage declaration');
    else if ($this->consume_keyword(RID_LET))
      $this->error(ERR_INFO, 'keyword `let` is not required in a trait-usage declaration');
    
    $node = $this->node('usage_item');
    $node->id = $this->parse_ident();
    $node->has_fn = $has_fn;
    
    if ($node->id === INVALID_NODE)
      goto err;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  /**
   *  class_enum_decl
   *    : enum_decl
   *    ;
   *    
   * @return Node
   */
  protected function parse_class_enum_decl()
  {
    if ($this->debug) print "in parse_class_enum_decl()\n";
  
    // noting special here (for now)
    return $this->parse_enum_decl();
  }
  
  /**
   *  maybe_modified_class_member
   *    : modifiers modified_class_member
   *    | class_member
   *    ;
   *    
   * @return Node
   */
  protected function parse_maybe_modified_class_member()
  {
    if ($this->debug) print "in parse_maybe_modified_class_member()\n";
  
    $mods = $this->parse_modifiers(true);
    
    if ($mods === INVALID_NODE)
      return $this->parse_class_member();
    
    $item = $this->parse_modified_class_member();
    
    if ($item === INVALID_NODE)
      goto out;
    
    $item->loc = $mods[0]->loc;
    $item->modifiers = $mods;
    
    out:
    return $item;
  }
  
  /**
   *  modified_class_member
   *    : class_member // allow "var_decl"
   *    ;
   *    
   * @return Node
   */
  protected function parse_modified_class_member()
  {
    if ($this->debug) print "in parse_modified_class_member()\n";
      
    if ($this->consume_keyword(RID_LET)) {
      $this->error(ERR_INFO, 'keyword `let` is not required here');
      return $this->parse_var_decl();
    }
    
    return $this->parse_class_member(true);
  }
  
  /**
   *  class_member
   *    : LA(RID_FN) class_fn_decl
   *    | LA(RID_LET) class_let_decl
   *    | LA(RID_NEW) class_ctor_decl
   *    | LA(RID_DEL) class_dtor_decl
   *    | LA(TOK_NAME == "get") maybe_class_getter_decl
   *    | LA(TOK_NAME == "set") maybe_class_setter_decl
   *    ;
   *    
   *  RID_FN = "fn"
   *  RID_LET = "let"
   *  RID_NEW = "new"
   *  RID_DEL = "del"
   *    
   * @param boolean $allow_var_decl
   * @return Node
   */
  protected function parse_class_member($avd = false)
  {
    if ($this->debug) print "in parse_class_member()\n";
  
    $peek = $this->peek_keyword();
    
    switch ($peek) {
      case RID_FN:
        return $this->parse_class_fn_decl();
      case RID_LET:
        return $this->parse_class_let_decl();
      case RID_NEW:
        return $this->parse_class_ctor_decl();
      case RID_DEL:
        return $this->parse_class_dtor_decl();
      default:
        break;
    }
    
    // peek directly
    $peek = $this->lex->peek();
    
    if ($peek && $peek->type === TOK_NAME) {
      switch ($peek->value) {
        case 'get':
          return $this->parse_maybe_class_getter_decl($avd);
        case 'set':
          return $this->parse_maybe_class_setter_decl($avd);
      }
      
      if ($avd === true)
        return $this->parse_var_decl();
    }
    
    $this->error(ERR_ERROR, 'expected `fn` or `let`');
    $this->skip_to_sync_rid();
    
    return INVALID_NODE;
  }
  
  /**
   *  class_fn_decl
   *    : fn_decl // with "this_param" allowed
   *    ;
   *    
   * @return Node
   */
  protected function parse_class_fn_decl()
  {
    if ($this->debug) print "in parse_class_fn_decl()\n";
  
    // allow "this" params
    return $this->parse_fn_decl(true);
  }
  
  /**
   *  class_let_decl
   *    : let_decl
   *    ;
   *    
   * @return Node
   */
  protected function parse_class_let_decl()
  {
    if ($this->debug) print "in parse_class_let_decl()\n";
  
    // nothing special
    return $this->parse_let_decl();
  }
  
  /** 
   *  class_ctor_decl
   *    : RID_NEW fn_params fn_body
   *    ;
   *    
   * @return Node
   */
  protected function parse_class_ctor_decl()
  {
    if ($this->debug) print "in parse_class_ctor_decl()\n";
  
    if (!$this->epxect_keyword(RID_NEW))
      goto err;
    
    $node = $this->node('ctor_decl');
    $node->params = $this->parse_fn_params(true);
    
    if ($node->params === INVALID_NODE)
      goto err;
    
    $node->body = $this->parse_fn_body();
    
    if ($node->body === INVALID_NODE)
      goto err;
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  /**
   *  class_dtor_decl
   *    : RID_DEL '(' ')' fn_body
   *    ;
   *    
   * @return Node
   */
  protected function parse_class_dtor_decl()
  {
    if ($this->debug) print "in parse_class_dtor_decl()\n";
  
    if (!$this->epxect_keyword(RID_DEL))
      goto err;
    
    $node = $this->node('dtor_decl');
    
    if (!$this->expect_token(TOK_LPAREN) ||
        !$this->expect_token(TOK_RPAREN))
      goto err;
    
    $node->body = $this->parse_fn_body();
    
    if ($node->body === INVALID_NODE)
      goto err;
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  /**
   *  maybe_class_getter_decl
   *    : "get" ident '(' ')' fn_body
   *    | var_decl
   *    ;
   *   
   * @param boolean $allow_var_decl
   * @return Node
   */
  protected function parse_maybe_class_getter_decl($avd = false)
  {
    if ($this->debug) print "in parse_maybe_class_getter_decl()\n";
  
    $peek = $this->lex->peek(2);
    
    if (!$peek || $peek->type !== TOK_NAME) {
      if ($avd === true) {
        $node = $this->parse_var_decl();
        goto out;
      }
      
      goto err;
    }
        
    $node = $this->node('getter_decl');
    
    $this->skip_token();
    $node->id = $this->parse_ident();
    
    if ($node->id === INVALID_NODE)
      goto err;
    
    if (!$this->expect_token(TOK_LPAREN) ||
        !$this->expect_token(TOK_RPAREN))
      goto err;
    
    $node->body = $this->parse_fn_body();
    
    if ($node->body === INVALID_NODE)
      goto err;
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  /**
   *  maybe_class_setter_decl
   *    : "set" ident fn_params fn_body
   *    | var_decl
   *    ;
   *   
   * @param boolean $allow_var_decl
   * @return Node
   */
  protected function parse_maybe_class_setter_decl($avd = false)
  {
    if ($this->debug) print "in parse_maybe_class_setter_decl()\n";
  
    $peek = $this->lex->peek(2);
    
    if (!$peek || $peek->type !== TOK_NAME) {
      if ($avd === true) {
        $node = $this->parse_var_decl();
        goto out;
      }
      
      goto err;
    }
        
    $node = $this->node('setter_decl');
    
    $this->skip_token();
    $node->id = $this->parse_ident();
    
    if ($node->id === INVALID_NODE)
      goto err;
    
    $node->params = $this->parse_fn_params(true);
    
    if ($node->params === INVALID_NODE)
      goto err;
    
    $node->body = $this->parse_fn_body();
    
    if ($node->body === INVALID_NODE)
      goto err;
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  /**
   *  trait_decl
   *    : RID_TRAIT ident trait_body
   *    ;
   *    
   *  trait_body
   *    : class_body
   *    ;
   *    
   * @return Node
   */
  protected function parse_trait_decl()
  {
    if ($this->debug) print "in parse_trait_decl()\n";
  
    if (!$this->consume_keyword(RID_TRAIT))
      goto err;
    
    $node = $this->node('trait_decl');
    $node->id = $this->parse_ident();
    $node->body = null;
    
    if ($node->id === INVALID_NODE)
      goto err;
    
    if (!$this->consume_token(TOK_SEMI)) {
      $node->body = $this->parse_class_body();
      
      if ($node->body === INVALID_NODE)
        goto err;
    }
    
    err:
    $node = INVALID_NODE;
    $this->skip_to_sync_token();
    
    out:
    return $node;
  }
  
  /**
   *  iface_decl
   *    : RID_IFACE ident iface_body
   *    ;
   *    
   *  iface_body
   *    : class_body
   *    ;
   *    
   * @return Node
   */
  protected function parse_iface_decl()
  {
    if ($this->debug) print "in parse_iface_decl()\n";
  
    if (!$this->consume_keyword(RID_TRAIT))
      goto err;
    
    $node = $this->node('trait_decl');
    $node->id = $this->parse_ident();
    $node->base = null;
    $node->body = null;
    
    if ($node->id === INVALID_NODE)
      goto err;
    
    if ($this->consume_token(TOK_DDOT)) {
      $node->base = $this->parse_module_name();
      
      if ($node->base === INVALID_NODE)
        goto err;
    }
    
    if (!$this->consume_token(TOK_SEMI)) {
      $node->body = $this->parse_class_body();
      
      if ($node->body === INVALID_NODE)
        goto err;
    }
    
    err:
    $node = INVALID_NODE;
    $this->skip_to_sync_token();
    
    out:
    return $node;
  }
  
  /**
   *  inner_decl
   *    : LA('@') attribute_decl
   *    | modifiers? LA(RID_FN) fn_decl
   *    | modifiers? LA(RID_LET) let_decl
   *    | modifiers? LA(RID_ENUM) enum_decl
   *    | modifiers var_decl
   *    | maybe_label_decl
   *    | stmt
   *    ;
   * 
   * @param boolean $allow_stmt   
   * @return Node
   */
  protected function parse_inner_decl($as = true)
  {
    if ($this->debug) print "in parse_inner_decl()\n";
  
    $peek = $this->peek_token();
    
    if ($peek === TOK_AT)
      return $this->parse_attribute_decl();
    
    if ($peek === TOK_NAME)
      return $this->parse_maybe_label_decl();
    
    $mods = $this->parse_modifiers(false);
    $peek = $this->peek_keyword();
    
    switch ($peek) {
      case RID_FN:
        $node = $this->parse_fn_decl();
        break;
      case RID_LET:
        $node = $this->parse_let_decl();
        break;
      case RID_ENUM:
        $node = $this->parse_enum_decl();
        break;
      default:
        if ($mods !== INVALID_NODE) {
          $node = $this->parse_var_decl();
          break;
        }
        
        if ($as === false)
          goto err;
        
        $node = $this->parse_stmt();
        goto out;
    }
    
    if ($node === INVALID_NODE)
      goto err;
    
    if ($mods !== INVALID_NODE) {
      $node->loc = $mods->loc;
      $node->modifiers = $mods;
    }
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    $this->skip_to_sync_token();
    
    out:
    return $node;
  }
  
  /**
   *  stmt
   *    : do_stmt
   *    | if_stmt
   *    | for_stmt
   *    | try_stmt
   *    | php_stmt
   *    | goto_stmt
   *    | test_stmt
   *    | break_stmt
   *    | throw_stmt
   *    | super_stmt
   *    | while_stmt
   *    | yield_stmt
   *    | assert_stmt
   *    | switch_stmt
   *    | foreach_stmt
   *    | require_stmt
   *    | block_stmt
   *    | expr_stmt
   *    ;
   *    
   * @return [type] [description]
   */
  protected function parse_stmt()
  {
    if ($this->debug) print "in parse_stmt()\n";
  
    // peek directly
    $peek = $this->lex->peek();
    
    if ($peek === INVALID_TOKEN)
      return INVALID_NODE;
    
    switch ($peek->type) {
      case TOK_LBRACE:
        return $this->parse_block_stmt();
      case TOK_KEYWORD:
        switch ($peek->keyword) {
          // errors
          case RID_FN:
          case RID_LET:
          case RID_ENUM:
            return $this->deny_decl_outside_of_block($peek);
            
          case RID_DO:
            return $this->parse_do_stmt();
          case RID_IF:
            return $this->parse_if_stmt();
          case RID_FOR:
            return $this->parse_for_stmt();
          case RID_TRY:
            return $this->parse_try_stmt();
          case RID_PHP:
            return $this->parse_php_stmt();
          case RID_GOTO:
            return $this->parse_goto_stmt();
          case RID_TEST:
            return $this->parse_test_stmt();
          case RID_BREAK:
          case RID_CONTINUE:
            return $this->parse_break_stmt();
          case RID_THROW:
            return $this->parse_throw_stmt();
          case RID_WHILE:
            return $this->parse_while_stmt();
          case RID_YIELD:
            return $this->parse_yield_stmt();
          case RID_ASSERT:
            return $this->parse_assert_stmt();
          case RID_SWITCH:
            return $this->parse_switch_stmt();
          case RID_FOREACH:
            return $this->parse_foreach_stmt();
          case RID_REQUIRE:
            return $this->parse_require_stmt();
        }
      case TOK_NAME:
        return $this->deny_maybe_label_outside_of_block();
      default:
        return $this->parse_expr_stmt();         
    }
  }
  
  // deny a fn/let or enum declaration
  protected function deny_decl_outside_of_block(Token $peek)
  {
    if ($this->debug) print "in deny_decl_outside_of_block()\n";
  
    $peek = $this->
    $this->error_at($peek->loc, ERR_ERROR, 
      '%token is only allowed in global-scope or within a block', $peek);
    
    return INVALID_NODE;
  }
  
  // deny a label declaration
  protected function deny_maybe_label_outside_of_block()
  {
    if ($this->debug) print "in deny_maybe_label_outside_of_block()\n";
  
    $next = $this->lex->peek(2);
    
    if ($next && $next->type === TOK_DDOT) {
      $this->error_at($peek->loc, ERR_ERROR, 
        'labels are only allowed within a block');
      
      $this->skip_to_sync_token();
      return INVALID_NODE;
    }
    
    return $this->parse_expr_stmt();
  }
  
  /**
   *  maybe_label_decl
   *    : LA(TOK_NAME, TOK_DDOT) label_decl
   *    | expr_stmt
   *    ;
   *    
   *  TOK_NAME = some identifier
   *  TOK_DDOT = ":"
   *    
   * @return Node
   */
  protected function parse_maybe_label_decl()
  {
    if ($this->debug) print "in parse_maybe_label_decl()\n";
  
    $p1 = $this->lex->peek(1);
    $p2 = $this->lex->peek(2);
    
    if ($p1 && $p2 && 
        $p1->type === TOK_NAME && 
        $p2->type === TOK_DDOT)
      return $this->parse_label_decl();
    
    return $this->parse_expr_stmt();
  }
  
  /**
   *  label_stmt
   *    : TOK_NAME ':' inner_decl
   *    ;
   *   
   *  TOK_NAME = some identifier
   *  
   * note: labels are not allowed in global-scope, so inner_decl is fine
   *   
   * @return Node
   */
  protected function parse_label_decl()
  {
    if ($this->debug) print "in parse_label_decl()\n";
  
    $node = $this->node('label_decl');
    $node->id = $this->parse_ident();
    
    if ($node->id === INVALID_NODE)
      goto err;
    
    if (!$this->expect_token(TOK_DDOT))
      goto err;
    
    // gets checked later
    $node->decl = $this->parse_inner_decl();
    
    if ($node->stmt === INVALID_NODE)
      goto err;
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  /**
   *  block_stmt
   *    : '{' ( inner_decl )* '}'
   *    ;
   *    
   * @return Node
   */
  protected function parse_block_stmt()
  {
    if ($this->debug) print "in parse_block_stmt()\n";
  
    if (!$this->expect_token(TOK_LBRACE))
      goto err;
    
    $node = $this->node('block_stmt');
    $node->body = [];
    
    $error = false;
    for (;;) {
      if ($this->at_eof_or_aborted())
        break;
              
      if ($this->consume_token(TOK_RBRACE))
        break;
      
      $decl = $this->parse_inner_decl();
      
      if ($decl === INVALID_NODE) {
        $error = true;
        $this->skip_to_sync_token();
      } else
        $node->body[] = $decl;
    }
    
    if ($error === false)
      goto out;
    
    err:
    $node = INVALID_NODE;
    $this->skip_to_brace();
    
    out:
    return $node;
  }
  
  /**
   *  do_stmt
   *    : RID_DO stmt RID_WHILE paren_expr ';'
   *    ;
   *    
   *  RID_DO = "do"
   *  RID_WHILE = "while"
   *  
   * @return Node
   */
  protected function parse_do_stmt()
  {
    if ($this->debug) print "in parse_do_stmt()\n";
  
    if (!$this->expect_keyword(RID_DO))
      goto err;
    
    $node = $this->node('do_stmt');
    $node->body = $this->parse_stmt();
    
    if ($node->body === INVALID_NODE)
      goto err;
    
    if (!$this->consume_keyword(RID_WHILE))
      goto err;
    
    $node->test = $this->parse_paren_expr();
    
    if ($node->test === INVALID_NODE)
      goto err;
    
    if (!$this->expect_token(TOK_SEMI))
      goto err;
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  /**
   *  if_stmt
   *    : RID_IF paren_expr stmt elsif_list else_stmt?
   *    ;
   *   
   *  elif_list
   *    : ( RID_ELIF paren_expr stmt )*
   *    ;
   *    
   *  else_stmt
   *    : RID_ELSE stmt
   *    ;
   *    
   * @return Node
   */
  protected function parse_if_stmt()
  {
    if ($this->debug) print "in parse_if_stmt()\n";
  
    if (!$this->expect_keyword(RID_IF))
      goto err;
    
    $node = $this->node('if_stmt');
    $node->test = $this->parse_paren_expr();
    
    if ($node->test === INVALID_NODE)
      goto err;
    
    $node->stmt = $this->parse_stmt();
    
    if ($node->stmt === INVALID_NODE)
      goto err;
    
    $node->elif = [];
    $node->els = null;
    
    for (;;) {
      if (!$this->consume_keyword(RID_ELIF)) 
        break;
      
      $elif = $this->node('elif_stmt');
      $elif->test = $this->parse_paren_expr();
      
      if ($elif->test === INVALID_NODE)
        goto err;
      
      $elif->stmt = $this->parse_stmt();
      
      if ($elif->stmt === INVALID_NODE)
        goto err;
      
      $node->elif[] = $elif;
    }
    
    if ($this->consume_keyword(RID_ELSE)) {
      $node->els = $this->parse_stmt();
      
      if ($node->els === INVALID_NODE)
        goto err;
    }
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  /**
   *  for_stmt
   *    : for_in_stmt
   *    | for_to_stmt
   *    ;
   *    
   *  for_in_stmt
   *    : RID_FOR '(' let_decl RID_IN expr ')' stmt
   *    ;
   *    
   *  for_to_stmt
   *    : RID_FOR '(' start_decl expr_stmt expr? ')' stmt
   *    ;
   *    
   *  start_decl
   *    : modifiers var_decl
   *    | let_decl
   *    | expr_stmt
   *    ; 
   *  
   *  RID_FOR = "for"
   *    
   * @return Node
   */
  protected function parse_for_stmt()
  {
    if ($this->debug) print "in parse_for_stmt()\n";
  
    if (!$this->expect_keyword(RID_FOR))
      goto err;
    
    $node = $this->node('for_stmt');
    
    // for (a in b)
    $node->foreach = false;
    $node->iter = null;
    
    // both
    $node->init = null;
    
    // for ( a; b; c)
    $node->test = null;
    $node->each = null;
    $node->stmt = null;
    
    if (!$this->consume_token(TOK_LPAREN))
      goto err;
    
    $peek = $this->peek_keyword();
    
    if ($peek === RID_LET) {
      $node->init = $this->parse_let_decl_no_semi(NO_IN);
      
      if ($node->init === INVALID_NODE)
        goto err;
      
    } else {
      $mods = $this->parse_modifiers(false);
      
      if ($mods !== INVALID_NODE) {
        $decl = $this->parse_var_decl_no_semi(NO_IN);
        
        if ($decl === INVALID_NODE)
          goto err;
        
        $decl->modifiers = $mods;
        $node->init = $decl;
                
      } else {
        $node->init = $this->parse_expr(NO_IN);
        
        if ($node->init === INVALID_NODE)
          goto err;
        
        if (!$this->check_lval($node->init))
          goto err;
      }
    }
    
    if ($this->consume_keyword(RID_IN)) {
      $node->foreach = true;
      $node->iter = $this->parse_expr();
      
      if ($node->iter === INVALID_NODE)
        goto err;
      
      if (!$this->expect_token(TOK_RPAREN))
        goto err;
      
    } else {
      $node->test = $this->parse_expr_stmt();
      
      if ($node->test === INVALID_NODE)
        goto err;
      
      if (!$this->consume_token(TOK_RPAREN)) {
        $node->each = $this->parse_expr();
        
        if ($node->each === INVALID_NODE)
          goto err;
        
        if (!$this->expect_token(TOK_RPAREN))
          goto err;
      }
    }
    
    $node->stmt = $this->parse_stmt();
    
    if ($node->stmt === INVALID_NODE)
      goto err;
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    $this->skip_to_sync_token();
    
    out:
    return $node;
  }
  
  /**
   *  try_stmt
   *    : RID_TRY block_stmt catch_list finally_stmt?
   *    ;
   *  
   *  catch_list
   *    : ( RID_CATCH ( module_name? ( (' ident ')' )? )? block_stmt )*
   *    ;
   *    
   *  finally_stmt
   *    : RID_FINALLY block_stmt
   *    ;
   *        
   *  note: catch_list stops if a general catch_stmt was parsed.
   *        general catch_stmt -> a catch statement without a 
   *                              exception-hint (catch-everyting)
   *        
   * @return Node
   */
  protected function parse_try_stmt()
  {
    if ($this->debug) print "in parse_try_stmt()\n";
  
    if (!$this->expect_keyword(RID_TRY))
      goto err;
    
    $node = $this->node('try_stmt');
    $node->body = $this->parse_block_stmt();
    $node->catches = [];
    $node->finalizer = null;
    
    if ($node->body === INVALID_NODE)
      goto err;
    
    $peek = $this->peek_keyword();
    $error = false;
    
    if ($peek === RID_CATCH) {
      $has_general = false;
      $gen = null;
      
      for (;;) {
        if (!$this->consume_keyword(RID_CATCH))
          break;
        
        $loc = $this->get_loc();
        $hint = null;
        $param = null;
        $peek = $this->peek_token();
        
        if ($peek === TOK_NAME) {
          $hint = $this->parse_module_name();
          
          if ($hint === INVALID_NODE) {
            $error = true;
            $this->skip_to([ TOK_LPAREN, TOK_LBRACE ], []);
          }
          
          $peek = $this->peek_token();
        }
        
        if ($peek === TOK_LPAREN) {
          $this->consume_token(TOK_LPAREN);
          $param = $this->parse_ident();
          
          if (!$this->expect_token(TOK_RPAREN)) {
            $error = true;
            $this->skip_to([ TOK_LBRACE ], []);
          }
        }
        
        $body = $this->parse_block_stmt();
        
        if ($body === INVALID_NODE) {
          $error = true;
          $this->skip_to([], array_merge([ RID_CATCH, RID_FINALLY ], self::$sync_rid));
        } elseif ($error === false) {
          if ($hint === null) {
            if ($has_general === true) {
              $this->error_at($loc, ERR_WARN, 'ambiguous catch-all declaration');
              $this->error_at($gen, ERR_WARN, 'previous declaration was here');
            } else {
              $has_general = true;
              $gen = $loc;
            }
          }
          
          $catch = $this->node_at($loc, 'catch_stmt');
          $catch->hint = $hint;
          $catch->param = $param;
          $catch->body = $body;
          
          $node->catches[] = $catch;
        }
      }
    }
    
    if ($this->consume_keyword(RID_FINALLY)) {
      $node->finalizer = $this->parse_block_stmt();
      
      if ($node->finalizer === INVALID_NODE)
        goto err;
    }
    
    var_dump($error);
    if ($error === true)
      goto err;
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    $this->skip_to_sync_rid();
    
    out:
    return $node;
  }
  
  /**
   *  php_stmt
   *    : RID_PHP '{' php_usage string '}'
   *    ;
   *    
   *  php_usage
   *    : use_decl*
   *    ;
   *    
   *  RID_PHP = "__php__"
   *    
   * @return Node
   */
  protected function parse_php_stmt()
  {
    if ($this->debug) print "in parse_php_stmt()\n";
  
    if (!$this->expect_keyword(RID_PHP))
      goto err;
    
    $node = $this->node('php_stmt');
    $node->usage = [];
    
    if (!$this->expect_token(TOK_LBRACE))
      goto err;
    
    for (;;) {
      if (!$this->consume_keyword(RID_USE))
        break;
      
      $use = $this->parse_use_decl();
      
      if ($use === INVALID_NODE)
        goto err;
      
      $node->usage[] = $use;
    }
    
    $peek = $this->lex->peek();
    
    if (!$peek || 
        $peek->type !== TOK_LITERAL || 
        $peek->literal !== LIT_STRING)
      goto err;
    
    $node->code = $peek->value;
    
    if (!$this->expect_token(TOK_RBRACE))
      goto err;
    
    $this->skip_token();
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    $this->skip_to_sync_token();
    
    out:
    return $node;
  }
  
  /**
   *  goto_stmt
   *    : RID_GOTO ident ';'
   *    ;
   *    
   *  RID_GOTO = "goto"
   *  
   * @return Node
   */
  protected function parse_goto_stmt()
  {
    if ($this->debug) print "in parse_goto_stmt()\n";
  
    if (!$this->expect_keyword(RID_GOTO))
      goto err;
    
    $node = $this->node('goto_stmt');
    $node->label = $this->parse_ident();
    
    if ($node->label === INVALID_NODE)
      goto err;
    
    if (!$this->expect_token(TOK_SEMI))
      goto err;
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  /**
   *  test_stmt
   *    : RID_TEST block_stmt
   *    | RID_TEST string block_stmt
   *    ;
   *    
   * @return Node
   */
  protected function parse_test_stmt()
  {
    if ($this->debug) print "in parse_test_stmt()\n";
  
    if (!$this->expect_keyword(RID_TEST))
      goto err;
    
    $node = $this->node('test_stmt');
    $node->desc = null;
    
    $peek = $this->peek_token();
    
    if ($peek !== TOK_LBRACE) {
      $node->desc = $this->parse_string();
      
      if ($node->desc === INVALID_NODE)
        goto err;
    }
    
    $node->body = $this->parse_block_stmt();
    
    if ($node->body === INVALID_NODE)
      goto err;
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  /**
   *  break_stmt
   *    : break_keyword ';'
   *    | break_keyword ident ';'
   *    ;
   *    
   *  break_keyword
   *    : RID_BREAK
   *    | RID_CONTINUE
   *    ;
   *  
   *  RID_BREAK = "break"
   *  RID_CONTINUE = "continue"
   *  
   * @return Node
   */
  protected function parse_break_stmt()
  {
    if ($this->debug) print "in parse_break_stmt()\n";
  
    $peek = $this->peek_keyword();
    $kind = $peek === RID_BREAK ? RID_BREAK : RID_CONTINUE;
    
    if (!$this->expect_keyword($kind))
      goto err;
    
    $node = $this->node($kind === RID_BREAK ? 'break_stmt' : 'continue_stmt');
    $node->label = null;
    
    if ($this->consume_token(TOK_SEMI))
      goto out;
    
    $node->label = $this->parse_ident();
    
    if ($node->label === INVALID_NODE)
      goto err;
    
    if (!$this->expect_token(TOK_SEMI))
      goto err;
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  /**
   *  throw_stmt
   *    : RID_THROW expr_no_comma ';'
   *    ;
   *    
   *  RID_THROW = "throw"
   *    
   * @return Node
   */
  protected function parse_throw_stmt()
  {
    if ($this->debug) print "in parse_throw_stmt()\n";
  
    if (!$this->expect_keyword(RID_THROW))
      goto err;
    
    $node = $this->node('throw_stmt');
    $node->expr = $this->parse_expr_no_comma();
    
    if ($node->expr === INVALID_NODE)
      goto err;
    
    if (!$this->expect_token(TOK_SEMI))
      goto err;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  /**
   *  while_stmt
   *    : RID_WHILE paren_expr stmt
   *    ;
   *    
   *  RID_WHILE = "while"
   *    
   * @return Node
   */
  protected function parse_while_stmt()
  {
    if ($this->debug) print "in parse_while_stmt()\n";
  
    if (!$this->expect_keyword(RID_WHILE))
      goto err;
    
    $node = $this->node('while_stmt');
    $node->test = $this->parse_paren_expr();
    
    if ($node->test === INVALID_NODE)
      goto err;
    
    $node->body = $this->parse_stmt();
    
    if ($node->body === INVALID_NODE)
      goto err;
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  /**
   *  yield_stmt
   *    : RID_YIELD expr_no_comma ';'
   *    ;
   *    
   * @return Node
   */
  protected function parse_yield_stmt()
  {
    if ($this->debug) print "in parse_yield_stmt()\n";
  
    if (!$this->expect_keyword(RID_YIELD))
      goto err;
    
    $node = $this->node('yield_stmt');
    $node->expr = $this->parse_expr_no_comma();
    
    if ($node->expr === INVALID_NODE)
      goto err;
    
    if (!$this->expect_token(TOK_SEMI))
      goto err;
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  /**
   *  assert_stmt
   *    : RID_ASSERT expr_no_comma ';'
   *    | RID_ASSERT expr_no_comma ':' string ';'
   *    ;
   *    
   * @return Node
   */
  protected function parse_assert_stmt()
  {
    if ($this->debug) print "in parse_assert_stmt()\n";
  
    if (!$this->expect_keyword(RID_ASSERT))
      goto err;
    
    $node = $this->node('assert_stmt');
    $node->expr = $this->parse_expr_no_comma();
    $node->msg = null;
    
    if ($node->expr === INVALID_NODE)
      goto err;
    
    if ($this->consume_token(TOK_DDOT)) {
      $node->msg = $this->parse_string();
      
      if ($node->msg === INVALID_NODE)
        goto err;
    }
    
    if (!$this->expect_token(TOK_SEMI))
      goto err;
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  protected function parse_switch_stmt()
  {
    if ($this->debug) print "in parse_switch_stmt()\n";
  
    UNIMPLEMENTED('switch');
  }
  
  /**
   *  foreach_stmt
   *    ... deprecated
   *   
   * @deprecated use for(...) or iter.each(...) instead
   * @return Node
   */
  protected function parse_foreach_stmt()
  {
    if ($this->debug) print "in parse_foreach_stmt()\n";
  
    $this->error(ERR_ERROR, 'foreach(...) is deprecated, use for(...) or iter.each(...) instead');
    
    $this->skip_token();    // RID_FOREACH
    $this->skip_token();    // '('
    $this->skip_to_paren(); // ...
    $this->skip_token();    // ')'
    
    // just for validation
    $this->parse_stmt();    
      
    return INVALID_NODE;
  }
  
  protected function parse_require_stmt()
  {
    if ($this->debug) print "in parse_require_stmt()\n";
  
    UNIMPLEMENTED('require');
  }
  
  /**
   *  ident
   *    : TOK_NAME
   *    | if keywords are allowed: RID_***
   *    ;
   *    
   * @param boolean $allow_keywords allow keywords (rids)
   * @return Node
   */
  protected function parse_ident($ak = true)
  {
    if ($this->debug) print "in parse_ident()\n";
  
    $peek = $this->lex->peek();
    
    if ($peek && ($peek->type === TOK_NAME || 
        ($ak && $peek->type === TOK_KEYWORD))) {
      $this->skip_token();
      $node = $this->node('ident');
      $node->value = $peek->value;
      return $node;
    }
    
    $this->expect_token(TOK_NAME);
    return INVALID_NODE;
  }
  
  /**
   *  literal
   *    : string
   *    | lnum
   *    | dnum
   *    ;
   *    
   * @return Node
   */
  protected function parse_literal()
  {
    if ($this->debug) print "in parse_literal()\n";
  
    $peek = $this->lex->peek();
    
    if ($peek && $peek->type === TOK_LITERAL) {
      $this->skip_token();
      $node = $this->node('literal');
      $node->literal = $peek->literal;
      $node->value = $peek->value;
      return $node;
    }
    
    $this->expect_token(TOK_LITERAL);
    return INVALID_NODE;
  }
  
  /**
   *  string
   *    : LIT_STRING
   *    ;
   *    
   * @return Node
   */
  protected function parse_string()
  {
    if ($this->debug) print "in parse_string()\n";
  
    $peek = $this->lex->peek();
    
    if ($peek && 
        $peek->type === TOK_LITERAL && 
        $peek->literal === LIT_STRING)
      return $this->parse_literal();
    
    if (!$peek) exit('fatal error');
    
    $this->error_at($peek->loc, ERR_ERROR, 'expected string, found %token', $peek);
    $this->skip_token();
    
    return INVALID_NODE;
  }
  
  /**
   *  module_name
   *    : TOK_DDDOT? ident ( TOK_DDDOT ident )*
   *    ;
   *    
   * @return Node
   */
  protected function parse_module_name()
  {
    if ($this->debug) print "in parse_module_name()\n";
  
    $abs = $this->consume_token(TOK_DDDOT);
    
    $peek = $this->peek_token();
    $node = $this->node('module_name');
    $node->abs = $abs;
    $node->parts = [];
    
    for (;;) {
      if ($this->at_eof_or_aborted())
        goto err;
      
      $part = $this->parse_ident();
      
      if ($part === INVALID_NODE)
        goto err;
        
      $node->parts[] = $part;
      
      if (!$this->consume_token(TOK_DDDOT))
        break;    
    }
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  /**
   *  maybe_module_name
   *    : ident
   *    | module_name
   *    ;
   *    
   * @return Node
   */
  protected function parse_maybe_module_name()
  {
    if ($this->debug) print "in parse_maybe_module_name()\n";
  
    $peek1 = $this->lex->peek(1);
    $peek2 = $this->lex->peek(2);
    
    if ($peek1 && $peek2 && 
        ($peek1->type === TOK_DDDOT || 
          ($peek1->type === TOK_NAME && 
           $peek2->type === TOK_DDDOT)))
      return $this->parse_module_name();
    
    return $this->parse_ident();
  }
  
  /**
   *  expr_stmt
   *    : ';'
   *    | expr ';'
   *    ;
   *    
   * @return Node
   */
  protected function parse_expr_stmt()
  {
    if ($this->debug) print "in parse_expr_stmt()\n";
  
    $peek = $this->peek_token();
    
    if ($peek === TOK_SEMI)
      $node = $this->node('empty_expr'); 
    else
      $node = $this->parse_expr();
    
    if ($node === INVALID_NODE)
      goto err;
    
    if (!$this->expect_token(TOK_SEMI))
      goto err;
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  /**
   *  paren_expr
   *    : '(' expr ')'
   *    ;
   *    
   * @return Node
   */
  protected function parse_paren_expr()
  {
    if ($this->debug) print "in parse_paren_expr()\n";
  
    if (!$this->expect_token(TOK_LPAREN))
      goto err;
    
    $expr = $this->parse_expr();
    
    if ($expr === INVALID_NODE) {
      $this->skip_to_paren();
      $this->expect_token(TOK_RPAREN);
      goto err;
    }
    
    if (!$this->expect_token(TOK_RPAREN))
      goto err;
    
    goto out;
    
    err:
    $expr = INVALID_NODE;
    
    out:
    return $expr;
  }
  
  /**
   *  args_paren
   *    : '(' ( arg ( ',' arg )* )* ')'
   *    ;
   *    
   * @return Node
   */
  protected function parse_args_paren()
  {
    if (!$this->expect_token(TOK_LPAREN))
      goto err;
    
    $node = $this->node('arg_list');
    $node->list = [];
    $node->spread = false;
    
    if ($this->consume_token(TOK_RPAREN))
      goto out;
    
    $error = false;
    for (;;) {
      if ($this->at_eof_or_aborted())
        goto err;
      
      $arg = $this->parse_arg();
      
      if ($arg === INVALID_NODE) {
        $this->skip_to_comma_or_paren();
        $error = true;
      } else {
        if ($arg->kind === 'spread_arg')
          $node->spread = true;
        
        $node->list[] = $arg;
      }
      
      if (!$this->consume_token(TOK_COMMA))
        break;
    }
    
    if (!$this->expect_token(TOK_RPAREN))
      goto err;
    
    if ($error === true)
      goto err;
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  /**
   *  arg
   *    : expr_no_comma
   *    | TOK_REST expr_no_comma
   *    ;
   *    
   * @return Node
   */
  protected function parse_arg()
  {
    $rloc = $this->get_loc();
    $rest = $this->consume_token(TOK_REST);
    $expr = $this->parse_expr_no_comma();
    
    if ($expr === INVALID_NODE)
      goto err;
    
    if ($rest === true)
      $node = $this->node_at($rloc, 'spread_arg');
    else
      $node = $this->node_at($expr->loc, 'arg');
    
    $node->expr = $expr;
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  /**
   *  expr
   *    : expr_no_comma ( ',' expr_no_comma )*
   *    ;
   * 
   * @param boolean $no_in 
   * @return Node
   */
  protected function parse_expr($ni = false)
  {
    if ($this->debug) print "in parse_expr()\n";
  
    $expr = $this->parse_expr_no_comma($ni);
    
    if ($expr === INVALID_NODE)
      goto err;
    
    $peek = $this->peek_token();
    
    if ($peek === TOK_COMMA) {
      $node = $this->node_at($expr->loc, 'seq_expr');
      $node->seq = [ $expr ];
      
      $this->skip_token();
      
      for (;;) {
        $expr = $this->parse_expr_no_comma($ni);
        
        if ($expr === INVALID_NODE)
          goto err;
        
        $node->seq[] = $expr;
        
        if (!$this->consume_token(TOK_COMMA))
          break;
      }
    } else
      $node = $expr;
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    $this->skip_to_sync_token();
    
    out:
    return $node;
  }
  
  /**
   *  expr_no_comma
   *    : maybe_assign_expr
   *    ;
   * 
   * @param boolean $no_in   
   * @return Node
   */
  protected function parse_expr_no_comma($ni = false)
  {
    if ($this->debug) print "in parse_expr_no_comma()\n";
  
    return $this->parse_maybe_assign_expr($ni);
  }
  
  /**
   *  maybe_assign_expr
   *    : lval assign_op maybe_assign_expr
   *    | maybe_cond_expr
   *    ;
   *  
   *  lval
   *    : literal
   *    | member_expr
   *    ;
   *    
   *  assign_op
   *    : TOK_ASSIGN
   *    | TOK_APLUS
   *    | TOK_AMINUS
   *    | TOK_AMUL
   *    | TOK_ADIV
   *    | TOK_AMOD
   *    | TOK_APOW
   *    | TOK_ABIT_NOT
   *    | TOK_ABIT_OR
   *    | TOK_ABIT_AND
   *    | TOK_ABIT_XOR
   *    | TOK_ABOOL_OR
   *    | TOK_ABOOL_AND
   *    | TOK_ABOOL_XOR
   *    | TOK_ASHIFT_L
   *    | TOK_ASHIFT_R
   *    ;
   *  
   *  TOK_ASSIGN = "="
   *  TOK_APLUS = "+="
   *  TOK_AMINUS = "-="
   *  TOK_AMUL = "*="
   *  TOK_ADIV = "/="
   *  TOK_AMOD = "%="
   *  TOK_APOW = "**="
   *  TOK_ABIT_NOT = "~="
   *  TOK_ABIT_OR = "|="
   *  TOK_ABIT_AND = "&="
   *  TOK_ABIT_XOR = "^="
   *  TOK_ABOOL_OR = "||="
   *  TOK_ABOOL_AND = "&&="
   *  TOK_ABOOL_XOR = "^^="
   *  TOK_ASHIFT_L = "<<="
   *  TOK_ASHIFT_R = ">>="
   *  
   * @param boolean $no_in 
   * @return Node
   */
  protected function parse_maybe_assign_expr($ni = false)
  {
    if ($this->debug) print "in parse_maybe_assign_expr()\n";
  
    $node = $this->parse_maybe_cond_expr($ni);
    
    if ($node === INVALID_NODE)
      goto err;
    
    $peek = $this->peek_token();
    
    switch ($peek) {
      case TOK_ASSIGN:
      case TOK_APLUS:
      case TOK_AMINUS:
      case TOK_AMUL:
      case TOK_ADIV:
      case TOK_AMOD:
      case TOK_APOW:
      case TOK_ABIT_NOT:
      case TOK_ABIT_OR:
      case TOK_ABIT_AND:
      case TOK_ABIT_XOR:
      case TOK_ABOOL_OR:
      case TOK_ABOOL_AND:
      case TOK_ABOOL_XOR:
      case TOK_ASHIFT_L:
      case TOK_ASHIFT_R:
        $this->skip_token();
        
        if (!$this->check_lval($node))
          goto err;
                
        $left = $node;
        $node = $this->node_at($left->loc, 'assign_expr');
        $node->op = $peek;
        $node->left = $left;
        $node->right = $this->parse_maybe_assign_expr($ni);
        
        if ($node->right === INVALID_NODE)
          goto err;
    }
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  /**
   *  maybe_cond_expr
   *    : expr_ops '?' ':' expr_no_comma
   *    | expr_ops '?' expr_no_comma ':' expr_no_comma
   *    | expr_ops
   *    ;
   * 
   * @param boolean $no_in
   * @return Node
   */
  protected function parse_maybe_cond_expr($ni = false)
  {
    if ($this->debug) print "in parse_maybe_cond_expr()\n";
  
    $node = $this->parse_expr_ops($ni);
    
    if ($node === INVALID_NODE)
      goto err;
    
    if ($this->consume_token(TOK_QM)) {
      if ($this->consume_token(TOK_DDOT)) 
        $cons = null;
      else {
        $cons = $this->parse_expr_no_comma($ni);
        
        if ($cons === INVALID_NODE)
          goto err;
        
        if (!$this->expect_token(TOK_DDOT))
          goto err;
      }
      
      $test = $node;
      $node = $this->node_at($test->loc, 'cond_expr');
      $node->test = $test;
      $node->cons = $cons;
      $node->alt = $this->parse_expr_no_comma($ni);
      
      if ($node->alt === INVALID_NODE)
        goto err;
    }
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;  
  }
  
  /**
   *  expr_ops
   *    : expr_op(maybe_cast_expr)
   *    ;
   *    
   * @param boolean $no_in
   * @return Node
   */
  protected function parse_expr_ops($ni = false)
  {
    if ($this->debug) print "in parse_expr_ops()\n";
  
    // start operator-predence parser
    $base = $this->parse_maybe_cast_expr($ni);
    
    if ($base === INVALID_NODE)
      return INVALID_NODE;
    
    return $this->parse_expr_op($base, -1);
  }
  
  /**
   *  expr_op
   *    : 
   *    
   * @param  Node $left
   * @param  int $minp
   * @return Node
   */
  protected function parse_expr_op($left, $minp)
  {
    if ($this->debug) print "in parse_expr_op()\n";
  
    $top = $this->lex->peek();
    $op = $this->lookup_op($top->type);
    
    if ($op && $op->prec > $minp) {
      $this->skip_token();
      
      $node = $this->node_at($left->loc, 
        $op->logical ? 'logical_expr' : 'binary_expr');
      
      $node->op = $top->type;
      $node->left = $left;
      
      $right = $this->parse_maybe_cast_expr();
      
      if ($right === INVALID_NODE)
        goto err;
      
      $node->right = $this->parse_expr_op($right, $op->prec);  
      
      if ($node->right === INVALID_NODE)
        goto err;
          
      $node = $this->parse_expr_op($node, $minp);
      
      err:
      $node = INVALID_NODE;
      $left = INVALID_NODE;
      $right = INVALID_NODE;
      
      out:
      return $node;
    }
    
    return $left;
  }
  
  /**
   *  maybe_cast_expr
   *    : maybe_check_expr ( RID_AS module_name )?
   *    ;
   *    
   *  RID_AS = "as"
   * 
   * @param boolean $no_in
   * @return Node
   */
  protected function parse_maybe_cast_expr($ni = false)
  {
    if ($this->debug) print "in parse_maybe_cast_expr()\n";
  
    $node = $this->parse_maybe_check_expr($ni);
    
    if ($node === INVALID_NODE)
      goto err;
    
    if ($this->consume_keyword(RID_AS)) {
      $left = $node;
      $node = $this->node_at($left->loc, 'cast_expr');
      $node->expr = $left;
      $node->cast = $this->parse_module_name();
      
      if ($node->cast === INVALID_NODE)
        goto err;
    }
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  /**
   *  maybe_check_expr
   *    : maybe_unary_expr check_op module_name
   *    ;
   *    
   *  check_op
   *    : RID_IS
   *    | RID_ISNT
   *    ;
   *  
   *  RID_IS = "is"
   *  RID_ISNT = "isnt"
   * 
   * @param boolean $no_in  
   * @return Node
   */
  protected function parse_maybe_check_expr($ni = false)
  {
    if ($this->debug) print "in parse_maybe_check_expr()\n";
  
    $node = $this->parse_maybe_unary_expr();
    
    if ($node === INVALID_NODE)
      goto err;
    
    $peek = $this->peek_keyword();
    
    if ($peek === RID_IS || $peek === RID_ISNT) {
      $left = $node;
      $node = $this->node_at($left->loc, 'is_expr');
      $node->op = $peek;
      $node->expr = $left;
      $node->check = $this->parse_module_name();
      
      if ($node->check === INVALID_NODE)
        goto err;
      
    } elseif (!$ni && $peek === RID_IN) {
      $left = $node;
      $node = $this->node_at($left->loc, 'in_expr');
      $node->expr = $left;
      $node->check = $this->parse_expr();
      
      if ($node->check === INVALID_NODE)
        goto err;
    }
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  /**
   *  maybe_unary_expr
   *    : LA(RID_TYPEOF) typeof_expr
   *    | LA(RID_NAMEOF) nameof_expr
   *    | unary_op maybe_unary_expr
   *    | maybe_postfix_expr
   *    ;
   *    
   *  unary_op
   *    : '+'
   *    | '-'
   *    | '!'
   *    | '~'
   *    | '&'
   *    | TOK_REST
   *    | TOK_INC
   *    | TOK_DEC
   *    ;
   *    
   *  RID_TYPEOF = "typeof"
   *  RID_NAMEOF = "nameof"
   *  
   *  TOK_REST = "..."
   *  TOK_INC = "++"
   *  TOK_DEC = "--"
   *    
   * @return [type] [description]
   */
  protected function parse_maybe_unary_expr()
  {
    if ($this->debug) print "in parse_maybe_unary_expr()\n";
  
    $peek = $this->lex->peek();
    
    switch ($peek->type) {
      case TOK_KEYWORD:
        switch ($peek->keyword) {
          case RID_TYPEOF:
            return $this->parse_typeof_expr();
          case RID_NAMEOF:
            return $this->parse_nameof_expr();
          default:
            break 2;
        }
      case TOK_PLUS:
      case TOK_MINUS:
      case TOK_EXCL:
      case TOK_BIT_NOT:
        $this->skip_token();
        
        $expr = $this->parse_maybe_unary_expr();
        
        if ($expr === INVALID_NODE)
          goto err;
        
        $node = $this->node_at($peek->loc, ERR_ERROR, 'unary_expr');
        $node->op = $peek->type;
        $node->expr = $expr;
        
        goto out;
        
      case TOK_INC:
      case TOK_DEC:
        $this->skip_token();
        
        $expr = $this->parse_maybe_unary_expr();
        
        if ($expr === INVALID_NODE)
          goto err;
        
        if (!$this->check_lval($expr))
          goto err;
        
        $node = $this->node_at($peek->loc, 'update_expr');
        $node->op = $peek->type;
        $node->prefix = true;
        $node->expr = $expr;
        
        goto out;
    } 
    
    $node = $this->parse_maybe_postfix_expr();
    
    if ($node === INVALID_NODE)
      goto err;
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  /**
   *  maybe_postfix_expr
   *    : expr_subscripts postfix_op?
   *    ;
   *  
   *  postfix_op
   *    : TOK_INC
   *    : TOK_DEC
   *    ;
   *    
   * @return Node
   */
  protected function parse_maybe_postfix_expr()
  {
    if ($this->debug) print "in parse_maybe_postfix_expr()\n";
  
    $node = $this->parse_expr_subscripts();
    
    if ($node === INVALID_NODE)
      goto err;
    
    for (;;) {
      $peek = $this->peek_token();
      
      switch ($peek) {
        case TOK_INC:
        case TOK_DEC:
          $this->skip_token();
          
          if (!$this->check_lval($node))
            goto err;
          
          $expr = $node;
          $node = $this->node_at($expr->loc, 'update_expr');
          $node->op = $peek;
          $node->prefix = false;
          $node->expr = $expr;
          break;
          
        default:
          break 2;
      }
    }
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  /**
   *  expr_subscripts
   *    : expr_subscripts '[' expr ']'
   *    | expr_subscripts '.' ident
   *    | expr_subscripts '(' args? ')'
   *    | expr_atom
   *    ;
   *  
   * @param boolean $no_calls
   * @return Node
   */
  protected function parse_expr_subscripts($nc = false)
  {
    if ($this->debug) print "in parse_expr_subscripts()\n";
  
    $node = $this->parse_expr_atom();
    
    if ($node === INVALID_NODE)
      goto err;
    
    for (;;) {
      if ($this->at_eof_or_aborted())
        break;
      
      $peek = $this->peek_token();
      
      switch ($peek) {
        case TOK_BANG:
          $this->skip_token();
          $expr = $node;
          $node = $this->node_at($expr->loc, 'bang_expr');
          $node->base = $expr;
          $node->member = $this->parse_ident();
          
          if ($node->member === INVALID_NODE)
            goto err;
          
          $node->args = $this->parse_args_paren();
          
          if ($node->args === INVALID_NODE)
            goto err;
          
          break;          
        case TOK_LBRACKET:
          $this->skip_token();
          $expr = $node;
          $node = $this->node_at($expr->loc, 'member_expr');
          $node->obj = $expr;
          $node->comp = true;
          $node->member = $this->parse_expr();
          
          if ($node->member === INVALID_NODE)
            goto err;
          
          if (!$this->expect_token(TOK_RBRACE))
            goto err;
          
          break; 
        case TOK_DOT:
          $this->skip_token();
          $expr = $node;
          $node = $this->node_at($expr->loc, 'member_expr');
          $node->obj = $expr;
          $node->comp = false;
          $node->member = $this->parse_ident();
          
          if ($node->member === INVALID_NODE)
            goto err;
          
          break; 
        case TOK_LPAREN:
          if ($nc === true)
            break 2;
            
          $expr = $node;
          $node = $this->node('call_expr');
          $node->callee = $expr;
          $node->args = $this->parse_args_paren();
          
          if ($node->args === INVALID_NODE)
            goto err;
          
          break;
        default:
          break 2;   
      }
    }
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  /**
   *  expr_atom
   *    : LA(RID_FN) fn_expr
   *    | LA(RID_NEW) new_expr
   *    | LA(RID_NULL) kw_literal
   *    | LA(RID_TRUE) kw_literal
   *    | LA(RID_FALSE) kw_literal
   *    | LA(RID_FILE_CONST) fl_literal
   *    | LA(RID_LINE_CONST) fl_literal
   *    | LA(RID_FN_CONST) sc_literal
   *    | LA(RID_CLASS_CONST) sc_literal
   *    | LA(RID_METHOD_CONST) sc_literal
   *    | LA(RID_MODULE_CONST) sc_literal
   *    | LA(TOK_DIV) regexp
   *    | LA(TOK_LITERAL) literal
   *    | LA(TOK_DDDOT) module_name
   *    | LA(TOK_NAME) maybe_module_name
   *    | LA(TOK_LBRACKET) array_pattern
   *    | LA(TOK_LBRACE) object_pattern
   *    | LA(TOK_LPAREN) paren_expr
   *    ;
   *   
   *  RID_FN = "fn"
   *  RID_NEW = "new"
   *  RID_NULL = "null"
   *  RID_TRUE = "true"
   *  RID_FALSE = "false"
   *  RID_FILE_CONST = "__file__"
   *  RID_LINE_CONST = "__line__"
   *  RID_FN_CONST = "__fn__"
   *  RID_CLASS_CONST = "__class__"
   *  RID_METHOD_CONST = "__method__"
   *  RID_MODULE_CONST = "__module__"
   *  TOK_DIV = "/"
   *  TOK_LITERAL = some literal like string or number
   *  TOK_DDDOT = "::"
   *  TOK_NAME = a name
   *  TOK_LBRACKET = "["
   *  TOK_LBRACE = "{"
   *  TOK_LPAREN = "("
   *      
   * @return Node
   */
  protected function parse_expr_atom()
  {
    if ($this->debug) print "in parse_expr_atom()\n";
      
    $this->get_loc();
    $peek = $this->lex->peek();
    
    switch ($peek->type) {
      case TOK_KEYWORD:
        switch ($peek->keyword) {
          case RID_FN:
            return $this->parse_fn_expr();
          case RID_NEW:
            return $this->parse_new_expr();
          case RID_NULL:
          case RID_TRUE:
          case RID_FALSE:
            $this->skip_token();
            $node = $this->node('kw_literal');
            $node->keyword = $peek->keyword;
            return $node;
          case RID_FILE_CONST:
          case RID_LINE_CONST:
            $this->skip_token();
            $node = $this->node('fl_literal');
            $node->token = $peek;
            $node->keyword = $peek->keyword;
            return $node;
          case RID_FN_CONST:
          case RID_CLASS_CONST:
          case RID_METHOD_CONST:
          case RID_MODULE_CONST:
            $this->skip_token();
            $node = $this->node('sc_literal');
            $node->token = $peek;
            $node->keyword = $peek->keyword;
            return $node;           
          default:
            $this->skip_token();
            $this->error_at($peek->loc, ERR_ERROR, 'unexpected %keyword', $peek);
            return INVALID_NODE;
        }
        break;
      case TOK_DIV:
        return $this->parse_regexp();
      case TOK_LITERAL:
        return $this->parse_literal();
      case TOK_DDDOT:
        return $this->parse_module_name();
      case TOK_NAME:
        return $this->parse_maybe_module_name();
      case TOK_LBRACKET:
        return $this->parse_array_pattern();
      case TOK_LBRACE:
        return $this->parse_object_pattern();
      case TOK_LPAREN:
        return $this->parse_paren_expr();
      default:
        $this->skip_token();
        $this->error_at($peek->loc, ERR_ERROR, 'unexpected %token', $peek);
        return INVALID_NODE;
    }
  }
  
  protected function parse_fn_expr()
  {
    if ($this->debug) print "in parse_fn_expr()\n";
    
    UNIMPLEMENTED("fn_expr");    
  }
  
  protected function parse_new_expr()
  {
    if ($this->debug) print "in parse_new_expr()\n";
  
    UNIMPLEMENTED("new_expr");
  }
  
  /**
   *  regexp
   *    : built-in regexp
   *    ;
   *    
   * @return Node
   */
  protected function parse_regexp()
  {
    if ($this->debug) print "in parse_regexp()\n";
  
    $loc = $this->get_loc();
    $reg = $this->lex->regexp();
    
    if ($reg === INVALID_TOKEN)
      goto err;
    
    $node = $this->node_at($loc, 'literal');
    $node->value = $reg;
    $node->literal = LIT_REGEXP;
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;    
  }
  
  /**
   *  array_pattern
   *    : '[' expr_no_comma RID_FOR '(' ident RID_IN expr ')' ']'
   *    | '[' array_item ( ',' array_item )* ']'
   *    ;
   *  
   *  array_item
   *    : expr_no_comma
   *    | TOK_REST expr_no_comma
   *    ;
   *    
   * @return Node
   */
  protected function parse_array_pattern()
  {
    if ($this->debug) print "in parse_array_pattern()\n";
  
    if (!$this->expect_token(TOK_LBRACKET))
      goto err;
    
    if ($this->consume_token(TOK_RBRACKET)) {
      $node = $this->node('array_expr');
      $node->items = [];
      goto out;
    }
    
    $item = $this->parse_array_item();
    
    if ($item === INVALID_NODE)
      goto err;
    
    $peek = $this->peek_keyword();
    $error = false;
    
    if ($peek === RID_FOR) {
      $node = $this->node_at($item->loc, 'array_gen');
      $node->item = $item;
      $node->gen = $this->parse_array_gen();
      
      if ($node->gen === INVALID_NODE)
        goto err;
      
    } else {
      $node = $this->node_at($item->loc, 'array_expr');
      $node->items = [ $item ];
      
      for (;;) {
        $peek = $this->peek_token();
        
        if ($peek === TOK_RBRACKET)
          break;
        
        $item = $this->parse_array_item();
        
        if ($item === INVALID_NODE) {
          $this->skip_to_comma_or_bracket();
          $error = true;
        } else {
          $node->items[] = $item;
        }
        
        if (!$this->consume_token(TOK_COMMA))
          break;
      }
    }
    
    if (!$this->expect_token(TOK_RBRACKET))
      goto err;
    
    if ($error === true)
      goto err;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
  
  protected function parse_array_gen()
  {
    
  }
  
  protected function parse_array_item()
  {
    
  }
  
  /**
   *  object_pattern
   *    : '{' object_key expr_no_comma ( ',' object_key expr_no_comma )* '}'
   *    ;
   *    
   * @return Node
   */
  protected function parse_object_pattern()
  {
    if ($this->debug) print "in parse_object_pattern()\n";
  
    if (!$this->expect_token(TOK_LBRACE))
      goto err;
    
    $node = $this->node('object_expr');
    $node->props = [];
    
    if ($this->consume_token(TOK_RBRACE))
      goto out;
    
    $names = [];
    $error = false;
    
    for (;;) {
      $key = $this->parse_object_key();
      
      if ($key === INVALID_NODE) {
        $this->skip_to_comma_or_brace();
        $error = true;
      } else {
        $seen = false;
        foreach ($names as $val => $loc) {
          if ($val === $key->value) {
            $seen = true;
            $this->error_at($key->loc, ERR_WARN, "redefinition of object-property '%s'", $key->value);
            $this->error_at($loc, ERR_INFO, 'previous declaration was here');
            break;
          }  
        }
        
        if ($seen === false)
          $names[$key->value] = $key->loc;
        
        $prop = $this->node('object_prop');
        $prop->key = $key;
        $prop->val = $this->parse_expr_no_comma();
        
        if ($prop->val === INVALID_NODE)
          goto err;
        
        $node->props[] = $prop;
      }
      
      if (!$this->consume_token(TOK_COMMA))
        break;
    }
    
    if (!$this->expect_token(TOK_RBRACE))
      goto err;
    
    if ($error === true)
      goto err;
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
    
  /**
   *  object_key
   *    : ident ':'
   *    | literal ':'
   *    ;
   *    
   * @return Node
   */
  protected function parse_object_key()
  {
    if ($this->debug) print "in parse_object_key()\n";
  
    $node = $this->node('object_key');
    $peek = $this->lex->peek();
    
    switch ($peek->type) {
      case TOK_NAME:
        $this->skip_token();
        $val = $peek->value;
        break;
      case TOK_LITERAL:
        if ($peek->literal !== LIT_STRING)
          $this->error(ERR_WARN, 'unsafe object-property key');
        
        $this->skip_token();
        $val = $peek->value;
        break;
      default:
        $this->error(ERR_ERROR, 'unexpected %token', $peek);
        goto err;
    }
    
    $node->value = $val;
    
    if (!$this->expect_token(TOK_DDOT))
      goto err;
    
    goto out;
    
    err:
    $node = INVALID_NODE;
    
    out:
    return $node;
  }
    
  /**
   * lookup an operator
   * 
   * @param int $type
   * @return Operator
   */
  protected function lookup_op($type)
  {
    if ($this->debug) print "in lookup_op()\n";
          
    if (!self::$optable) {
      $prec_14 = new Operator(14, false);
      $prec_13 = new Operator(13, false);
      $prec_12 = new Operator(12, false);
      $prec_11 = new Operator(11, false);
      $prec_8 = new Operator(8, false);
      $prec_7 = new Operator(7, false);
      
      self::$optable = [
        TOK_DIV => $prec_14,
        TOK_MOD => $prec_14,
        TOK_MUL => $prec_13,
        TOK_POW => $prec_13,
        TOK_PLUS => $prec_12,
        TOK_MINUS => $prec_12,
        TOK_SHIFT_L => $prec_11,
        TOK_SHIFT_R => $prec_11,
        TOK_BIT_NOT => new Operator(10, false), // concat
        TOK_RANGE => new Operator(9, false),
        TOK_LT => $prec_8,
        TOK_GT => $prec_8,
        TOK_LTE => $prec_8,
        TOK_GTE => $prec_8,
        TOK_EQUAL => $prec_7,
        TOK_NOT_EQUAL => $prec_7,
        TOK_BIT_AND => new Operator(6, false),
        TOK_BIT_XOR => new Operator(5, false),
        TOK_BIT_OR => new Operator(4, false),
        TOK_BOOL_AND => new Operator(3, true),
        TOK_BOOL_XOR => new Operator(2, true),
        TOK_BOOL_OR => new Operator(1, true)
      ];
    }
    
    if (!isset(self::$optable[$type]))
      return null;
    
    return self::$optable[$type];
  }
  
  /* ------------------------------------ */
  
  /**
   * checks if a node is a lval (assignable)
   * 
   * @param  Node $node
   * @return boolean
   */
  protected function check_lval($node)
  {
    if ($node->kind === 'member_expr'
     || $node->kind === 'bang_expr'
     || $node->kind === 'ident')
      return true;
    
    $this->error_at($node->loc, ERR_ERROR, 'expected lval, found %s', $node->kind);
    return false;
  }
  
  /**
   * constructs an ast-node using the current location
   * 
   * @param  string $kind
   * @return Node
   */
  protected function node($kind)
  {
    if ($this->debug) print "in node()\n";
  
    return $this->node_at($this->get_loc(), $kind);
  }
  
  /**
   * constructs an ast-node
   * 
   * @param  Location $loc  
   * @param  string   $kind
   * @return Node
   */
  protected function node_at(Location $loc, $kind)
  {
    if ($this->debug) print "in node_at()\n";
  
    // TODO: use $kind to load a specific class from ast/
    return new Node($loc, $kind);    
  }
  
  /**
   * consumes tokens
   * 
   * @param  array  $tids
   * @param  array  $rids
   * @param  boolean $greedy
   * @return boolean true if at least one token was consumed, false otherwise
   */
  protected function consume_tokens($tids = [], $rids = [], $greedy = true)
  {
    if ($this->debug) print "in consume_tokens()\n";
  
    $count = 0;
    
    for (;;) {
      $peek = $this->lex->peek();
      
      if ($peek === INVALID_TOKEN || 
          $peek->type == TOK_EOF)
        break;
      
      if ($peek->type === TOK_KEYWORD) {
        if (!in_array($peek->keyword, $rids, true))
          break;
      } else {
        if (!in_array($peek->type, $tids, true))
          break;
      }
      
      $this->lex->skip();
      ++$count;
      
      if ($greedy === false)
        break;
    }
    
    $this->set_loc();
    return $count > 0;
  }
  
  /**
   * consumes one specific token
   * 
   * @param  int $tid
   * @return boolean
   */
  protected function consume_token($tid)
  {
    if ($this->debug) print "in consume_token()\n";
  
    // simply forward
    return $this->consume_tokens([ $tid ], [], false);
  }
  
  /**
   * consumes one specific keyword
   * 
   * @param  int $rid
   * @return boolean
   */
  protected function consume_keyword($rid)
  {
    if ($this->debug) print "in consume_keyword()\n";
  
    // simply forward
    return $this->consume_tokens([], [ $rid ], false);
  }
  
  /**
   * consumes semicolons
   * 
   * @return boolean
   */
  protected function consume_semis()
  {
    if ($this->debug) print "in consume_semis()\n";
  
    // simply forward
    return $this->consume_tokens([ TOK_SEMI ], [], true);
  }
  
  /**
   * checks if the current token is a $tid
   * 
   * @param  int $tid
   * @return boolean
   */
  protected function expect_token($tid)
  {
    if ($this->debug) print "in expect_token()\n";
  
    $peek = $this->lex->peek();
    
    if ($peek && $peek->type === $tid) {
      $this->set_loc();
      $this->lex->skip();
      return true;
    }
    
    $this->error(ERR_ERROR, 'unexpected %token, expected %token', $peek, $tid);
    return false;
  }
  
  /**
   * checks if the current token is a keyword $rid
   * 
   * @param  int $rid
   * @return boolean
   */
  protected function expect_keyword($rid)
  {
    if ($this->debug) print "in expect_keyword()\n";
  
    $peek = $this->lex->peek();
    
    if ($peek && $peek->keyword === $rid) {
      $this->set_loc();
      $this->lex->skip();
      return true;
    }
    
    $this->error(ERR_ERROR, 'unexpected %token, expected %keyword', $peek, $rid);
    return false;
  }
  
  /**
   * peeks a token (id) from the lexer
   * 
   * @return int
   */
  protected function peek_token() 
  {
    $peek = $this->lex->peek();
    return $peek === INVALID_TOKEN ? -1 : $peek->type;
  }
  
  /**
   * peeks a keyword from the lexer
   * 
   * @return int
   */
  protected function peek_keyword()
  {
    if ($this->debug) print "in peek_keyword()\n";
  
    $peek = $this->lex->peek();
    return $peek === INVALID_TOKEN ? -1 : $peek->keyword;
  }
  
  /**
   * checks if the lexer is at EOF (end of file) or the compilation
   * was aborted
   * 
   * @return boolean
   */
  protected function at_eof_or_aborted()
  {
    if ($this->debug) print "in at_eof_or_aborted()\n";
  
    if ($this->ctx->abort)
      return true;
    
    return $this->peek_token() === TOK_EOF;
  }
  
  /**
   * skips a single token and updates the parser location-information
   * 
   */
  protected function skip_token()
  {
    if ($this->debug) print "in skip_token()\n";
  
    $this->set_loc();
    $this->lex->skip();
  }
  
  /**
   * skips tokens until one of $tids or $rids was found
   * note: this method handles nesting braces
   * 
   * @param array $tids additional token-ids
   * @param array $rids additional keyword-ids
   */
  protected function skip_to($tids = [], $rids = [])
  {
    if ($this->debug) print "in skip_to()\n";
  
    $brace = 0;
    $paren = 0;
    $brack = 0;
    
    for (;;) {
      $peek = $this->lex->peek();
      
      if ($peek === INVALID_TOKEN || 
          $peek->type == TOK_EOF)
        break;
        
      switch ($peek->type) {
        case TOK_LBRACE:
          $this->lex->skip();
          ++$brace;
          continue 2;
        case TOK_RBRACE:
          if ($brace > 0) {
            $this->lex->skip();
            --$brace;
            continue 2;
          }
          break;
        case TOK_LPAREN:
          $this->lex->skip();
          ++$paren;
          continue 2;
        case TOK_RPAREN:
          if ($paren > 0) {
            $this->lex->skip();
            --$paren;
            continue 2;
          }
          break;
        case TOK_LBRACKET:
          $this->lex->skip();
          ++$brack;
          continue 2;
        case TOK_RBRACKET:
          if ($brack > 0) {
            $this->lex->skip();
            --$brack;
            continue 2;
          }
          break;
      }
      
      if (($brace + $paren + $brack) === 0) {
        if ($peek->type === TOK_KEYWORD) {
          if (in_array($peek->keyword, $rids, true))
            break;
        } else {
          if (in_array($peek->type, $tids, true))
            break;
        }
      }
      
      $this->lex->skip();
    }
    
    $this->set_loc();
  }
  
  /**
   * skips to a semicolon
   * 
   * 
   */
  protected function skip_to_semi()
  {
    if ($this->debug) print "in skip_to_semi()\n";
  
    // simply forward
    $this->skip_to([ TOK_SEMI ]);
  }
  
  /**
   * skips to a comma
   * 
   */
  protected function skip_to_comma()
  {
    if ($this->debug) print "in skip_to_comma()\n";
  
    // simply forward
    $this->skip_to([ TOK_COMMA ]);
  }
  
  /**
   * skips to the first matching closing paren ')'
   * 
   *
   */
  protected function skip_to_paren()
  {
    if ($this->debug) print "in skip_to_paren()\n";
  
    // simply forward
    $this->skip_to([ TOK_RPAREN ]);
  }
  
  /** 
   * skips to a comma or the first matching closing paren ')'
   * 
   */
  protected function skip_to_comma_or_paren()
  {
    if ($this->debug) print "in skip_to_comma_or_paren()\n";
  
    // simply forward
    $this->skip_to([ TOK_COMMA, TOK_RPAREN ]);
  }
  
  /** 
   * skips to a comma or the first matching closing brace '}'
   * 
   */
  protected function skip_to_comma_or_brace()
  {
    if ($this->debug) print "in skip_to_comma_or_brace()\n";
  
    // simply forward
    $this->skip_to([ TOK_COMMA, TOK_RBRACE ]);
  }
  
  /**
   * skip to the first matching closing brace '}'
   * 
   */
  protected function skip_to_brace()
  {
    if ($this->debug) print "in skip_to_brace()\n";
  
    // simply forward
    $this->skip_to([ TOK_RBRACE ]);
  }
  
  /**
   * skips to a sync token (see self::$sync_tok and self::$sync_rid)
   *
   */
  protected function skip_to_sync_token()
  {
    if ($this->debug) print "in skip_to_sync_token()\n";
  
    // simply forward
    $this->skip_to(self::$sync_tok, self::$sync_rid);
  }
  
  /**
   * skips to a sync keyword (self::$sync_rid)
   *
   */
  protected function skip_to_sync_rid()
  {
    if ($this->debug) print "in skip_to_sync_rid()\n";
  
    // simply forward
    $this->skip_to([], self::$sync_rid);
  }
}
