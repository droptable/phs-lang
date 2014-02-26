<?php

namespace phs;

require_once 'glob.php';

/** ascii tokens */
const 
  T_ASSIGN = 61,   // '='
  T_LT = 60,       // '<'
  T_GT = 62,       // '>'
  T_PLUS = 43,     // '+'
  T_MINUS = 45,    // '-'
  T_DIV = 47,      // '/'
  T_MUL = 42,      // '*'
  T_MOD = 37,      // '%'
  T_BIT_NOT = 126, // '~'
  T_BIT_OR = 124,  // '|'
  T_BIT_AND = 38,  // '&'
  T_BIT_XOR = 94,  // '^'
  T_LPAREN = 40,   // '('
  T_RPAREN = 41,   // ')'
  T_LBRACKET = 91, // '['
  T_RBRACKET = 93, // ']'
  T_LBRACE = 123,  // '{'
  T_RBRACE = 125,  // '}'
  T_DOT = 46,      // '.'
  T_SEMI = 59,     // ';'
  T_COMMA = 44,    // ','
  T_AT = 64,       // '@'
  T_QM = 63,       // '?'
  T_EXCL = 33,     // '!'
  T_DDOT = 58,     // ':'
  T_EOF  = 0       // end of file
;

/**
 * lexer class
 * produces tokens from a given input-string
 */
class Lexer
{
  // sync-mode
  public $sync = false;
  
  // the compiler context
  private $ctx;
  
  // the source
  private $data;
  
  // the current line and column
  private $line, $coln;
  
  // name of file
  private $file;
  
  // token queue used as cache for regexps
  private $queue = [];
  
  // track new-lines "\n"
  private $tnl = false;
  
  // end-of-file reached?
  private $end = false, $eof = false, $eof_token;
  
  // scanner pattern
  private static $re;
  
  /**
   * constructor
   * 
   * @param string $file
   * @param string $data
   */
  public function __construct(Context $ctx, Source $src)
  {
    $this->ctx = $ctx;
    $this->line = 1;
    $this->coln = 1;
    $this->data = $src->get_text();
    $this->file = $src->get_name();
    
    // load pattern
    if (!self::$re)
      self::$re = file_get_contents(__DIR__ . '/lexer.re');
    
    /*
    for (;;) {
      $tok = $this->next();
      
      if ($tok === INVALID_TOKEN)
        break;
      
      print "\n";
      $tok->debug();
      
      if ($tok->type === TOK_EOF)
        break;
    }
    
    exit;
    */
  }
  
  /**
   * error handler
   * 
   * @return void
   */
  public function error()
  {
    $args = func_get_args();
    $lvl = array_shift($args);
    $msg = array_shift($args);
    $loc = new Location($this->file, new Position($this->line, $this->coln));
    
    $this->ctx->verror_at($loc, COM_LEX, $lvl, $msg, $args);
  }
    
  /**
   * returns the next token
   * 
   * @return Token
   */
  public function next()
  {
    if (!empty($this->queue))
      $tok = array_shift($this->queue);
    else
      $tok = $this->scan();
    
    // if the scanner is in sync-mode
    if ($this->sync && in_array($tok->type, self::$sync_tok)) {
      $loc = $tok->loc;
      
      if (!empty($this->queue)) 
        array_unshift($this->queue, $tok);
      else 
        // token is new - or the queue is empty
        array_push($this->queue, $tok);
      
      $tok = $this->token(T_SYNC, '<synchronizing token>', false);
      $tok->loc = $loc;
      
      // not longer needed
      $this->sync = false;
    }
    
    return $tok;
  }
  
  /**
   * peeks a token
   * 
   * @return [type] [description]
   */
  public function peek()
  {
    if (!empty($this->queue))
      return $this->queue[0];
    
    $tok = $this->scan();
    $this->push($tok);
    
    return $tok;
  }
  
  /**
   * pushes a token onto the queue 
   * 
   * @param  Token  $t
   */
  public function push(Token $t)
  {
    array_push($this->queue, $t);
  }
  
  /**
   * skips a token
   * 
   */
  public function skip()
  {
    if (!empty($this->queue))
      array_shift($this->queue);
    else
      $this->scan();
  }
  
  /**
   * scans the given string for new-lines and adjusts $line and $coln
   * 
   * @param  string $sub
   * @param  int    $len
   */
  protected function adjust_line_coln($sub, $len)
  {
    if ($len === 0) $len = strlen($sub);
    for ($idx = 0; $idx < $len; ++$idx) {
      $cur = $sub[$idx];
      
      if ($cur === "\n") {
        $this->line += 1;
        $this->coln = 1;
      } else
        $this->coln += 1;
    }
  }
  
  /**
   * like adjust_line_coln() but stops on the first non-whitespace character
   * 
   * @param  string $sub
   * @param  int    $len
   */
  protected function adjust_line_coln_beg($sub, $len)
  {
    if ($len === 0) $len = strlen($sub);
    for ($idx = 0; $idx < $len; ++$idx) {
      $cur = $sub[$idx];
      
      // stop if a non-whitespace char was found
      if (!ctype_space($cur)) break;
      
      if ($cur === "\n") {
        // found a <new line>
        $this->line += 1;
        $this->coln = 1;
      } else
        $this->coln += 1;
    }
  }
  
  /**
   * like adjust_line_coln() but skips whitespace characters at the beginning.
   * counterpart of adjust_line_coln_beg()
   * 
   * @param  string $sub
   * @param  int    $len
   */
  protected function adjust_line_coln_end($sub, $len)
  {
    $beg = false;
    
    if ($len === 0) $len = strlen($sub);
    for ($idx = 0; $idx < $len; ++$idx) {
      $cur = $sub[$idx];
      
      // skip whitespace at the beginning
      if (!$beg && ctype_space($cur))
        continue;
      
      $beg = true;
        
      if ($cur === "\n") {
        $this->line += 1;
        $this->coln = 1;
      } else
        $this->coln += 1;
    }
  }
  
  /**
   * scans a token
   * 
   * @return Token
   */
  protected function scan()
  {        
    if (!$this->eof && $this->end) {
      $this->eof = true;
      return $this->end;
    }
    
    if ($this->eof === true || $this->ends()) {
      eof:
      
      // if EOF was not set before
      if ($this->eof !== true) {
        // produce one more TOK_SEMI
        $this->eof = true;
        
        $tok = $this->token(T_SEMI, ';', true);
        $tok->implicit = true;
        return $tok;
      }
     
      // generate EOF token to save memory if ->scan() gets called again
      if (!$this->eof_token)
        $this->eof_token = $this->token(T_EOF, '<end of file>', true);
      
      return $this->eof_token;
    }
    
    $tok = null;
    
    // loop used to avoid recursion if tokens get skipped (comments)
    for (;;) {
      $m = null;
        
      // track new lines?
      if ($this->tnl === true) {
        // test of a new-line can be scanned
        if (preg_match('/^\h*\r?(\n)/', $this->data, $m)) {
          $this->tnl = false;
          goto prd;
        }
      } 
         
      if (!preg_match(self::$re, $this->data, $m)) {
        // the scanner-pattern could not match anything
        // in this state we can not produce tokens (anymore)
        $this->eof = true;
        $this->adjust_line_coln_beg($this->data, 0);
        $this->error(ERR_ABORT, 'invalid input near: ' . substr($this->data, 0, 10) . '...');
        goto eof;
      }
      
      // start analizing
      prd:
      
      // "raw" and "sub" matched data
      // raw: can contain whitespace at the beginning
      // sub: the relevant data
      list ($raw, $sub) = $m;
      $len = strlen($raw);
      
      // remove match from data
      $this->data = substr($this->data, $len);
      
      // get correct starting line/coln
      $this->adjust_line_coln_beg($raw, $len);
      
      // save start line/coln
      $pos = new Position($this->line, $this->coln);
      
      // update end line/coln
      $this->adjust_line_coln_end($sub, 0);
      
      // comments
      if ($sub[0] === '#' || in_array(substr($sub, 0, 2), [ '/*', '//' ])) {
        // handle <eof> if the comment was at the end of our input
        if ($this->ends()) goto eof;
        continue; // continue otherwise
      }
      
      // if we scanned a string-literal: concat following strings
      // "foo" "bar" -> "foobar"
      if ($sub[0] === '"' || substr($sub, 1, 1) === '"') {
        $flg = $sub[0] !== '"' ? $sub[0] : null;
        $beg = $flg ? 2 : 1;
        $sub = $this->scan_concat(substr($sub, $beg, -1));
        $tok = $this->token(T_STRING, $sub);
        $tok->flag = $flg;
      } elseif ($sub[0] === "'" || substr($sub, 1, 1) === "'") {
        // strings can be in single-quotes too.
        // note: concat only applies to double-quoted strings
        $flg = $sub[0] !== "'" ? $sub[0] : null;
        $beg = $flg ? 2 : 1;
        $sub = substr($sub, $beg, -1);
        $tok = $this->token(T_STRING, $sub);
        $tok->flag = $flg;
      } else {
        // analyze match
        $tok = $this->analyze($sub);
        assert($tok !== null);
        
        if ($tok->type === T_SEMI)
          $tok->implicit = false;
        // track new lines if the current token was a '@'
        elseif ($tok->type === T_AT)
          $this->tnl = true;
        elseif ($tok->type === T_END) {
          // the __end__ marker behaves just like real-eof
          $end = $tok;
          
          // produce one more ';'
          $tok = $this->token(T_SEMI, ';', false);
          $tok->implicit = true;
          
          $end->raw = $raw;
          $end->loc = new Location($this->file, $pos);
          
          // push the end-token onto the queue
          $this->end = $end;
        }
      }
      
      break;
    }
    
    assert($tok !== null);
    $tok->raw = $raw;
    $tok->loc = new Location($this->file, $pos);
    
    return $tok;
  }
  
  /** 
   * scans a string-concat token
   * "foo" "bar" -> "foobar"
   * 
   * @param  string $str
   * @return string
   */
  protected function scan_concat($str)
  {
    static $re = '/^[\h\v]*["]([^\\\\"]+|[\\\\].)*["]/';
    
    for (;;) {
      if (!preg_match($re, $this->data, $m))
        break;
      
      list ($raw, $sub) = $m;
      $len = strlen($raw);
      $str .= $sub;
      
      // update line and coln
      $this->adjust_line_coln($raw, $len);
      
      // remove scan from data
      $this->data = substr($this->data, $len);
    }
    
    return $str;
  }
  
  /**
   * scans a regex
   * 
   * @return Token
   */
  public function scan_regexp(Token $div)
  {  
    assert($div->type === T_DIV);
    
    $reg = '/';
    $len = strlen($this->data);
    $idx = 0;    
    $esc = false;
    $mrk = false;
    $end = false;
    
    // copy $line and $coln to local vars
    $line = $this->line;
    $coln = $this->coln;
    
    while ($idx < $len) {
      $cur = $this->data[$idx++];
      
      // everything has an end :-)
      if ($cur === '/' && !$esc && !$mrk) {
        $end = true;
        $coln += 1;
        break;
      }
      
      if ($cur === '[' && !$esc && !$mrk)
        $mrk = true; // in brackets now
      else {
        if ($cur === ']' && !$esc)
          $mrk = false; // end of brackets
        else if ($cur === '\\') {
          $esc = true; // in escape-sequence
          continue;
        }
      }
      
      // we do not compile the regex here, so just append the escape-char
      if ($esc) $reg .= '\\';
      
      // we can not update the members $line and $coln directly,
      // because we don't know if the regex is valid
      if ($cur === "\n") {
        $line += 1;
        $coln = 1;
      } else
        $coln += 1;
      
      $reg .= $cur;
      $esc = false;
    }
    
    // do not handle it as regex
    if (!$end) {
      $this->error(ERR_WARN, 
        'a regular expression was expected but could not be scanned');
      
      // push a T_INVL token
      $this->push($this->token(T_INVL, '<invalid>', true));
    } else {
      $reg .= '/';
      
      // flags
      for (; $idx < $len; ++$idx) {
        $cur = $this->data[$idx];
        
        switch ($cur) {
          case 'i':
          case 'm':
          case 's':
          case 'A':
          case 'D':
          case 'S':
          case 'U':
          case 'X':
          case 'J':
          case 'u':
          case 'g':
            $reg .= $cur;
            break;
          
          default:
            break 2;
        }
        
        $coln += 1;
      }
      
      // update $data, $line and $coln
      $this->data = substr($this->data, $idx);
      $this->line = $line;
      $this->coln = $coln;
      
      // construct token
      $tok = $this->token(T_REGEXP, $reg, false);
      $tok->loc = $div->loc;
      
      // push the token to the queue
      $this->push($tok);
    }
  }
  
  /**
   * analyzes a scanned value and returns it as a token
   * 
   * @param  string  $sub
   * @return Token
   */
  protected function analyze($sub)
  {
    static $re_dnum = '/^(\.\d+|\d+\.\d*)/';
    static $re_lnum = '/^(\d+)([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)?/';
    static $re_base = '/^(?:0[xX][0-9A-Fa-f]+|0[0-7]+|0[bB][01]+)$/';
    
    static $eq_err = "found '%s', used '%s' instead";
    
    $tok = null;
    
    if (preg_match($re_dnum, $sub))
      // double
      $tok = $this->token(T_DNUM, $sub);
    else if (preg_match($re_lnum, $sub, $m)) {
      // integer (with optional suffix)
      $tid = !empty($m[2]) ? T_SNUM : T_LNUM; 
      $tok = $this->token($tid, $m[1]);
      $tok->suffix = $tid === T_SNUM ? $m[2] : null;
    } else if (preg_match($re_base, $sub)) {
      $tok = $this->token(T_LNUM, $sub);
      $tok->suffix = null;
    } else {
      // warn about '===' and '!=='
      if ($sub === '===' || $sub === '!==')
        $this->error(ERR_WARN, sprintf($eq_err, $sub, substr($sub, 0, -1)));
      
      if (isset(self::$table[$sub]))         
        // lookup token-table and check if the token is separator/operator  
        $tok = $this->token(self::$table[$sub], $sub);
      elseif (isset(self::$rids[$sub]))
        // check if the token is a keyword (rid -> reserved identifier)
        $tok = $this->token(self::$rids[$sub], $sub); 
      else
        // otherwise the token must be a name
        $tok = $this->token(T_IDENT, $sub);
    }
    
    return $tok;
  }
  
  /**
   * creates a new token
   * 
   * @param  int $type
   * @param  string $value
   * @return Token
   */
  protected function token($type, $value, $genloc = false)
  {
    $tok = new Token($type, $value);
    
    if ($genloc === true)
      $tok->loc = new Location($this->file, 
        new Position($this->line, $this->coln));
    
    return $tok;
  }
  
  /**
   * checks if the source can not produce more tokens
   * 
   * @return bool
   */
  protected function ends()
  {
    static $re_all = '/^[\h\v]*$/';
    static $re_nnl = '/^[\h]*$/';
    
    // if $tnl (track new lines) is true: use \h, else: use \h\v
    return preg_match($this->tnl ? $re_nnl : $re_all, $this->data);
  }
  
  /* ------------------------------------ */
  
  private static $sync_tok = [
    T_FN,
    T_LET,
    T_USE,
    T_ENUM,
    T_CLASS,
    T_TRAIT,
    T_IFACE,
    T_MODULE,
    T_REQUIRE,
    T_DO,
    T_IF,
    T_FOR,
    T_TRY,
    T_GOTO,
    T_BREAK,
    T_CONTINUE,
    T_THROW,
    T_WHILE,
    T_ASSERT,
    T_SWITCH,
    T_RETURN,
    T_CONST,
    T_FINAL,
    T_GLOBAL,
    T_STATIC,
    T_EXTERN,
    T_PUBLIC,
    T_PRIVATE,
    T_PROTECTED,
    T_SEALED,
    T_INLINE,
    T_PHP,
    T_TEST
  ];
  
  private static $rids = [   
    'fn' => T_FN,
    'let' => T_LET,
    'use' => T_USE,
    'enum' => T_ENUM,
    'class' => T_CLASS,
    'trait' => T_TRAIT,
    'iface' => T_IFACE,
    'module' => T_MODULE,
    'require' => T_REQUIRE,

    'true' => T_TRUE,
    'false' => T_FALSE,
    'null' => T_NULL,
    'this' => T_THIS,
    'super' => T_SUPER,

    'get' => T_GET,
    'set' => T_SET,

    'do' => T_DO,
    'if' => T_IF,
    'elsif' => T_ELSIF,
    'else' => T_ELSE,
    'for' => T_FOR,
    'try' => T_TRY,
    'goto' => T_GOTO,
    'break' => T_BREAK,
    'continue' => T_CONTINUE,
    'throw' => T_THROW,
    'catch' => T_CATCH,
    'finally' => T_FINALLY,
    'while' => T_WHILE,
    'assert' => T_ASSERT,
    'switch' => T_SWITCH,
    'case' => T_CASE,
    'default' => T_DEFAULT,
    'return' => T_RETURN,

    'const' => T_CONST,
    'final' => T_FINAL,
    'global' => T_GLOBAL,
    'static' => T_STATIC,
    'extern' => T_EXTERN,
    'public' => T_PUBLIC,
    'private' => T_PRIVATE,
    'protected' => T_PROTECTED,
    
    // this tokens are '''shortcuts''' for the corresponding attributes
    '__sealed__' => T_SEALED, // @ sealed fn ...
    '__inline__' => T_INLINE, // @ inline fn ...
    
    '__php__' => T_PHP,
    '__test__' => T_TEST,
    
    '__file__' => T_CFILE,
    '__line__' => T_CLINE,
    '__coln__' => T_CCOLN,
    
    '__fn__' => T_CFN,
    '__class__' => T_CCLASS,
    '__method__' => T_CMETHOD,
    '__module__' => T_CMODULE,
    
    '__end__' => T_END,
    
    'yield' => T_YIELD,
    'new' => T_NEW,
    'del' => T_DEL,
    'as' => T_AS,
    'is' => T_IS,
    'isnt' => T_ISNT,
    'in' => T_IN,
    
    'int' => T_TINT,
    'integer' => T_TINT,
    'bool' => T_TBOOL,
    'boolean' => T_TBOOL,
    'float' => T_TFLOAT,
    'double' => T_TFLOAT,
    'string' => T_TSTRING,
    'regexp' => T_TREGEXP
  ];
  
  // some tokens are ascii-tokens, see comment at the top of this file
  private static $table = [   
    // this is aspecial token used for new-lines
    "\n" => T_NL,
     
    '=' => T_ASSIGN,
    '+=' => T_APLUS,
    '-=' => T_AMINUS,
    '*=' => T_AMUL,
    '**=' => T_APOW,
    '/=' => T_ADIV,
    '%=' => T_AMOD,
    '~=' => T_ABIT_NOT,
    '|=' => T_ABIT_OR,
    '&=' => T_ABIT_AND,
    '^=' => T_ABIT_XOR,
    '||=' => T_ABOOL_OR,
    '&&=' => T_ABOOL_AND,
    '^^=' => T_ABOOL_XOR,
    '<<=' => T_ASHIFT_L,
    '>>=' => T_ASHIFT_R,
    '**=' => T_APOW,
    
    '==' => T_EQ,
    '!=' => T_NEQ,
    '===' => T_EQ,
    '!==' => T_NEQ,
    
    '<' => T_LT,
    '>' => T_GT,
    '<=' => T_LTE,
    '>=' => T_GTE,
    
    '>>' => T_SR,
    '<<' => T_SL,
    
    '+' => T_PLUS,
    '-' => T_MINUS,
    '/' => T_DIV,
    '*' => T_MUL,
    '%' => T_MOD,
    '**' => T_POW,
    
    '~' => T_BIT_NOT,
    '|' => T_BIT_OR,
    '&' => T_BIT_AND,
    '^' => T_BIT_XOR,
    
    '||' => T_BOOL_OR,
    '&&' => T_BOOL_AND,
    '^^' => T_BOOL_XOR, 
    
    '++' => T_INC,
    '--' => T_DEC,
    
    '(' => T_LPAREN,
    ')' => T_RPAREN,
    '[' => T_LBRACKET,
    ']' => T_RBRACKET,    
    '{' => T_LBRACE,
    '}' => T_RBRACE,
    
    '.' => T_DOT,
    '..' => T_RANGE,
    '...' => T_REST,
    
    ';' => T_SEMI,
    ',' => T_COMMA,
    
    '@' => T_AT,
    '?' => T_QM,
    '!' => T_EXCL,
    ':' => T_DDOT,
    '::' => T_DDDOT,
    '=>' => T_ARR,
    // '!.' => T_BANG
  ];
}
