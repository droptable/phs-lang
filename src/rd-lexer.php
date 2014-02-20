<?php

/* this was the lexer for the recursive descent parser.
   this file is not longer maintained */
   
/* see: yy-lexer.php */

namespace phs;

exit('use yy-lexer.php');

const INVALID_TOKEN = null;

// tokens
const
  TOK_EOF = 0,
  TOK_ASSIGN = 1,
  TOK_APLUS = 2,
  TOK_AMINUS = 3,
  TOK_AMUL = 4,
  TOK_ADIV = 5,
  TOK_ABIT_NOT = 6,
  TOK_ABIT_OR = 7,
  TOK_ABIT_AND = 8,
  TOK_ABIT_XOR = 9,
  TOK_ABOOL_OR = 10,
  TOK_ABOOL_AND = 11,
  TOK_ABOOL_XOR = 12,
  TOK_ASHIFT_L = 13,
  TOK_ASHIFT_R = 14,
  TOK_EQUAL = 15,
  TOK_NOT_EQUAL = 16,
  TOK_LT = 17,
  TOK_GT = 18,
  TOK_LTE = 19,
  TOK_GTE = 20,
  TOK_SHIFT_R = 21,
  TOK_SHIFT_L = 22,
  TOK_PLUS = 23,
  TOK_MINUS = 24,
  TOK_DIV = 25,
  TOK_MUL = 26,
  TOK_MOD = 27,
  TOK_BIT_NOT = 28,
  TOK_BIT_OR = 29,
  TOK_BIT_AND = 30,
  TOK_BIT_XOR = 31,
  TOK_BOOL_OR = 32,
  TOK_BOOL_AND = 33,
  TOK_BOOL_XOR = 34, 
  TOK_INC = 35,
  TOK_DEC = 36,
  TOK_LPAREN = 37,
  TOK_RPAREN = 38,
  TOK_LBRACKET = 39,
  TOK_RBRACKET = 40,    
  TOK_LBRACE = 41,
  TOK_RBRACE = 42,
  TOK_DOT = 43,
  TOK_RANGE = 44,
  TOK_REST = 45,
  TOK_SEMI = 46,
  TOK_COMMA = 47,
  TOK_QM = 48,
  TOK_EXCL = 49,
  TOK_DDOT = 50,
  TOK_DDDOT = 51,
  TOK_ARR = 52,
  TOK_AT = 53,
  TOK_BANG = 54,
  TOK_HALT = 55,
  TOK_POW = 56,
  TOK_APOW = 57,
  TOK_AMOD = 58
;

// special tokens
const
  TOK_UNEXP = 199,
  TOK_NAME = 200,
  TOK_IDENT = 200, // alias
  TOK_LITERAL = 201,
  TOK_KEYWORD = 202
;

// literal types
const
  LIT_NONE = 0,
  LIT_STRING = 1,
  LIT_DNUM = 2,
  LIT_LNUM = 3,
  LIT_REGEXP = 4,
  LIT_CHAR = 5
;

// reserved identifiers
const
  RID_NONE = 0,
  RID_FN = 1,
  RID_LET = 2,
  RID_AS = 20,
  RID_CONST = 21,
  RID_TRUE = 22,
  RID_FALSE = 23,
  RID_NULL = 24,
  RID_CLASS = 25,
  RID_ENUM = 26,
  RID_IFACE = 27,
  RID_TRAIT = 28,
  RID_MODULE = 29,
  RID_PUBLIC = 30,
  RID_PRIVATE = 31,
  RID_PROTECTED = 32,
  RID_FINAL = 33,
  RID_STATIC = 34,
  RID_NEW = 35,
  RID_DEL = 36,
  RID_THIS = 37,
  RID_SUPER = 38,
  RID_TYPEOF = 39,
  RID_NAMEOF = 40,
  RID_EXTERN = 41,
  RID_DO = 42,
  RID_WHILE = 43,
  RID_FOR = 44,
  RID_IF = 45,
  RID_ELSE = 46,
  RID_RETURN = 47,
  RID_GOTO = 48,
  RID_BREAK = 49,
  RID_CONTINUE = 50,
  RID_ASSERT = 51,
  RID_USE = 52,
  RID_TRY = 53,
  RID_CATCH = 54,
  RID_FINALLY = 55,
  // RID_INCLUDE = 56, // removed, use std::include instead
  RID_YIELD = 57,
  RID_PHP = 58,
  RID_TEST = 59,
  RID_REQUIRE = 60,
  RID_SWITCH = 61,
  RID_CASE = 62,
  RID_DEFAULT = 63,
  RID_FOREACH = 64,
  RID_THROW = 65,
  // RID_GET = 66,
  // RID_SET = 67,
  RID_FILE_CONST = 68,
  RID_LINE_CONST = 69,
  RID_CLASS_CONST = 70,
  RID_FN_CONST = 71,
  RID_METHOD_CONST = 72,
  RID_MODULE_CONST = 73,
  RID_IS = 74,
  RID_ISNT = 75,
  RID_GLOBAL = 76,
  RID_INLINE = 77,
  RID_IN = 78,
  RID_ELIF = 79
;

// token 
class Token
{
  public $loc;
  public $raw;
  public $type;
  public $value;
  public $suffix;
  public $literal = LIT_NONE;
  public $keyword = RID_NONE;
  
  public function __construct($type, $value)
  {
    $this->type = $type;
    $this->value = $value;
  }
  
  public function debug()
  {
    $this->loc->debug();
    print "tok: {$this->type}\n";
    print "value: {$this->value}\n";
  }
}

// position
class Position
{
  public $line;
  public $coln;
  
  public function __construct($line, $coln)
  {
    $this->line = $line;
    $this->coln = $coln;
  }
}

// location
class Location
{
  public $file;
  public $pos;
  public $range;
  
  public function __construct($file, Position $pos)
  {
    $this->file = $file;
    $this->pos = $pos;
  }
  
  public function debug()
  {
    print "{$this->file} {$this->pos->line}:{$this->pos->coln}\n";
  }
}

/**
 * lexer class
 * produces tokens from a given input-string
 */
class Lexer
{
  // the compiler context
  private $ctx;
  
  // the source
  private $data;
  
  // the current line and column
  private $line, $coln;
  
  // name of file
  private $file;
  
  // halt-flag: gets set if someting ambiguous was 
  //            found and the parser should figure it out first
  private $halt = false;
  
  // how many tokens are available (in queue)
  private $avail = 0;
  
  // token queue used as look-ahead cache
  private $queue = [];
  
  // end-of-file reached?
  private $eof, $eof_token;
  
  // scanner pattern
  private static $re;
  
  /**
   * constructor
   * 
   * @param string $file
   * @param string $data
   */
  public function __construct(Context $ctx, $data, $file)
  {
    $this->ctx = $ctx;
    $this->data = $data;
    $this->line = 1;
    $this->coln = 1;
    $this->file = $file;
    
    // load pattern
    if (!self::$re)
      self::$re = file_get_contents(__DIR__ . '/lexer.re');
    
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
   * check if the lexer is halted
   * 
   * @return boolean
   */
  public function halted()
  {
    return $this->halt === true;
  }
  
  /**
   * returns the top-most $n'th token without removing it from the queue
   * 
   * @return Token
   */
  public function peek($n = 1)
  {  
    if ($this->halt === true)
      // more lookahead is not possible
      goto best;
    
    if ($this->avail < $n) {
      $d = $n - $this->avail;
            
      for (; $d > 0; --$d) {
        $t = $this->scan();        
        array_push($this->queue, $t);
        
        if ($t->type === TOK_DIV) {
          // set halt-flag: the parser must proccess the current queue first
          $this->halt = true;
          break;
        }
      }
      
      $this->avail = count($this->queue);
      
      if ($this->halt === true)       
        // return the last scanned token
        return end($this->queue);
    }
        
    best:
    $i = max(0, $n - 1);
    $i = min($this->avail, $i);
    
    # print "-> avl = {$this->avail}\n-> idx = $i\n";
    
    return $this->queue[$i];    
  }
  
  /**
   * skips a token 
   * 
   */
  public function skip()
  {
    if ($this->avail > 0)
      $this->shift();
    else
      $this->scan(false);
  }
  
  /**
   * shift from the queue and return 
   * 
   * @return Token
   */
  private function shift()
  {    
    assert($this->avail > 0);
    $this->avail -= 1;
    
    // if the queue is now empty: release the halt-flag
    if ($this->avail === 0 && $this->halt)
      $this->halt = false;
    
    return array_shift($this->queue);
  }
  
  /**
   * returns the next token in the queue or scans a new one
   * 
   * @return Token
   */
  public function next()
  {
    $tok = null;
    
    if ($this->avail > 0)
      $tok = $this->shift();
    else
      $tok = $this->scan();
    
    return $tok;
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
  protected function scan($gen = true)
  {        
    if ($this->eof === true || $this->ends()) {
      eof:
      
      // if EOF was not set before
      if ($this->eof !== true) {
        // produce one more TOK_SEMI
        $this->eof = true;
        
        if ($gen === true) {
          $tok = $this->token(TOK_SEMI, ';', true);
          $tok->implicit = true;
          return $tok;
        }
        
        return null;
      }
     
      // generate EOF token to save memory if ->scan() gets called again
      // note: we ignore $gen here
      if (!$this->eof_token)
        $this->eof_token = $this->token(TOK_EOF, '<end of file>', true);
      
      return $gen ? $this->eof_token : null;
    }
    
    $tok = null;
    
    // loop used to avoid recursion if comments get matched
    for (;;) {      
      $m = null;
      if (!preg_match(self::$re, $this->data, $m)) {
        // the scanner-pattern could not match anything
        // in this state we can not produce tokens (anymore)
        $this->eof = true;
        $this->adjust_line_coln_beg($this->data, 0);
        $this->error(ERR_ABORT, 'invalid input');
        goto eof;
      }
      
      // "raw" and "sub" matched data
      // raw: can contain whitespace at the beginning
      // sub: the relevant data
      list ($raw, $sub) = $m;
      $len = strlen($raw);
      
      // remove match from data
      $this->data = substr($this->data, $len);
      
      // get correct starting line/coln
      $this->adjust_line_coln_beg($raw, $len);
      
      if ($gen === true)
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
      if ($sub[0] === '"') {
        $sub = $this->scan_concat(substr($sub, 1, -1));
        if ($gen === true) {
          $tok = $this->token(TOK_LITERAL, $sub);
          $tok->literal = LIT_STRING;
        }
      } else if ($sub[0] === "'") {
        // strings can be in single-quotes too.
        // note: concat only applies to double-quoted strings
        $sub = substr($sub, 1, -1);
        if ($gen === true) {
          $tok = $this->token(TOK_LITERAL, $sub);
          $tok->literal = LIT_STRING;
        }
      } else if ($gen === true) {
        // analyze match
        $tok = $this->analyze($sub);
        assert($tok !== null);
        
        // check if TOK_NAME is a keyword
        if ($tok->type === TOK_NAME) {
          if (isset(self::$rids[$tok->value])) {
            $tok->type = TOK_KEYWORD;
            $tok->keyword = self::$rids[$tok->value];
          }
        } elseif ($tok->type === TOK_SEMI)
          $tok->implicit = false;
      }
      
      break;
    }
    
    if ($gen === true) {
      assert($tok !== null);
      $tok->raw = $raw;
      $tok->loc = new Location($this->file, $pos);
    }
    
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
  public function regexp(Token $div = null)
  {
    if ($div === null) {
      // the lexer must be halted
      assert($this->halt === true);
      $div = end($this->queue);
    }
    
    assert($div && $div->type === TOK_DIV);
    
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
      $this->error(ERR_WARN, 'a regular expression was requested but could not be scanned');
      return INVALID_TOKEN;
    }
    
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
    
    if ($this->halt === true) {
      // halt not longer needed
      $this->halt = false;
        
      // clear queue
      array_pop($this->queue);
      $this->avail -= 1;
    }
    
    // construct token
    $tok = $this->token(TOK_LITERAL, $reg, false);
    $tok->loc = $div->loc;
    $tok->literal = LIT_REGEXP;
    
    return $tok;
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
    
    static $eq_err = "'===' or '!==' found, used '==' or '!=' instead";
    
    $tok = null;
    
    if (preg_match($re_dnum, $sub)) {
      // double
      $tok = $this->token(TOK_LITERAL, $sub);
      $tok->literal = LIT_DNUM;
    } else if (preg_match($re_lnum, $sub, $m)) {
      // integer (with optional suffix)
      $tok = $this->token(TOK_LITERAL, $m[1]);
      $tok->literal = LIT_LNUM;
      $tok->suffix = isset($m[2]) ? $m[2] : null;
    } else if (preg_match($re_base, $sub)) {
      $tok = $this->token(TOK_LITERAL, $sub);
      $tok->literal = LIT_LNUM;
      $tok->suffix = null;
    } else
      // lookup token-table and check if the token is
      // separator/operator ...
      if (isset(self::$table[$sub])) {
        if ($sub === '===' || $sub === '!==')
          $this->error(ERR_WARN, $eq_err);
        
        $tok = $this->token(self::$table[$sub], $sub);
      } else      
        // otherwise the token must be a name
        $tok = $this->token(TOK_NAME, $sub);
    
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
    static $re = '/^[\h\v]*$/';
    return preg_match($re, $this->data);
  }
  
  /* ------------------------------------ */
  
  public static function lookup_keyword($rid)
  {
    if (!self::$rids_flip)
      self::$rids_flip = array_flip(self::$rids);  
    
    if (isset (self::$rids_flip[$rid]))
      return self::$rids_flip[$rid];
    
    return '(unknown)';
  }
  
  public static function lookup_token($tok)
  {
    if (!self::$table_flip)
      self::$table_flip = array_flip(self::$table);
    
    if (isset (self::$table_flip[$tok]))
      return self::$table_flip[$tok];
    
    if ($tok === TOK_EOF)
      return 'EOF (end of file)';
    
    if ($tok === TOK_LITERAL)
      return '(literal)';
    
    if ($tok === TOK_KEYWORD)
      return '(keyword)';
    
    if ($tok === TOK_NAME)
      return '(identifier)';
    
    return '(unknown)';
  }
  
  private static $rids_flip;
  private static $rids = [
    'in' => RID_IN,
    'as' => RID_AS,
    'const' => RID_CONST,
    
    'fn' => RID_FN,
    'let' => RID_LET,
    
    'true' => RID_TRUE,
    'false' => RID_FALSE,
    'null' => RID_NULL,
    
    'class' => RID_CLASS,
    'enum' => RID_ENUM,
    'iface' => RID_IFACE,
    'trait' => RID_TRAIT,
    'module' => RID_MODULE,
    
    'public' => RID_PUBLIC,
    'private' => RID_PRIVATE,
    'protected' => RID_PROTECTED,
    'final' => RID_FINAL,
    'static' => RID_STATIC,
    
    'is' => RID_IS,
    'isnt' => RID_ISNT,
    '!is' => RID_ISNT,
    'is!' => RID_ISNT,
    'in' => RID_IN,
    
    'new' => RID_NEW,
    'del' => RID_DEL,
    // 'get' => RID_GET,
    // 'set' => RID_SET,
    
    'this' => RID_THIS,
    'super' => RID_SUPER,
    'typeof' => RID_TYPEOF,
    'nameof' => RID_NAMEOF,
    'extern' => RID_EXTERN,
    
    'do' => RID_DO,
    'while' => RID_WHILE,
    'for' => RID_FOR,
    'if' => RID_IF,
    'elif' => RID_ELIF,
    'else' => RID_ELSE,
    'return' => RID_RETURN,
    'goto' => RID_GOTO,
    'break' => RID_BREAK,
    'continue' => RID_CONTINUE,
    'assert' => RID_ASSERT,
    'use' => RID_USE,
    'try' => RID_TRY,
    'catch' => RID_CATCH,
    'finally' => RID_FINALLY,
    'throw' => RID_THROW,
    // 'include' => RID_INCLUDE,
    'require' => RID_REQUIRE,
    'yield' => RID_YIELD,
    'switch' => RID_SWITCH,
    'case' => RID_CASE,
    'default' => RID_DEFAULT,
    'foreach' => RID_FOREACH,
    
    '__php__' => RID_PHP,
    '__test__' => RID_TEST,
    
    '__file__' => RID_FILE_CONST,
    '__line__' => RID_LINE_CONST,    
    '__class__' => RID_CLASS_CONST,
    '__fn__' => RID_FN_CONST,
    '__method__' => RID_METHOD_CONST,
    '__module__' => RID_MODULE_CONST,
    
    'global' => RID_GLOBAL,
    'inline' => RID_INLINE
  ];
  
  private static $table_flip;
  private static $table = [    
    '=' => TOK_ASSIGN,
    '+=' => TOK_APLUS,
    '-=' => TOK_AMINUS,
    '*=' => TOK_AMUL,
    '**=' => TOK_APOW,
    '/=' => TOK_ADIV,
    '%=' => TOK_AMOD,
    '~=' => TOK_ABIT_NOT,
    '|=' => TOK_ABIT_OR,
    '&=' => TOK_ABIT_AND,
    '^=' => TOK_ABIT_XOR,
    '||=' => TOK_ABOOL_OR,
    '&&=' => TOK_ABOOL_AND,
    '^^=' => TOK_ABOOL_XOR,
    '<<=' => TOK_ASHIFT_L,
    '>>=' => TOK_ASHIFT_R,
    
    '==' => TOK_EQUAL,
    '!=' => TOK_NOT_EQUAL,
    
    '===' => TOK_EQUAL,
    '!==' => TOK_NOT_EQUAL,
    
    '<' => TOK_LT,
    '>' => TOK_GT,
    '<=' => TOK_LTE,
    '>=' => TOK_GTE,
    
    '>>' => TOK_SHIFT_R,
    '<<' => TOK_SHIFT_L,
    
    '+' => TOK_PLUS,
    '-' => TOK_MINUS,
    '/' => TOK_DIV,
    '*' => TOK_MUL,
    '%' => TOK_MOD,
    '**' => TOK_POW,
    
    '~' => TOK_BIT_NOT,
    '|' => TOK_BIT_OR,
    '&' => TOK_BIT_AND,
    '^' => TOK_BIT_XOR,
    
    '||' => TOK_BOOL_OR,
    '&&' => TOK_BOOL_AND,
    '^^' => TOK_BOOL_XOR, 
    
    '++' => TOK_INC,
    '--' => TOK_DEC,
    
    '(' => TOK_LPAREN,
    ')' => TOK_RPAREN,
    '[' => TOK_LBRACKET,
    ']' => TOK_RBRACKET,    
    '{' => TOK_LBRACE,
    '}' => TOK_RBRACE,
    
    '.' => TOK_DOT,
    '..' => TOK_RANGE,
    '...' => TOK_REST,
    
    ';' => TOK_SEMI,
    ',' => TOK_COMMA,
    
    '@' => TOK_AT,
    '?' => TOK_QM,
    '!' => TOK_EXCL,
    ':' => TOK_DDOT,
    '::' => TOK_DDDOT,
    '=>' => TOK_ARR,
    '!.' => TOK_BANG
  ];
  
  // lookuptables (gets initialized if needed)
  private static $tid_lookup = null;
  private static $rid_lookup = null;
}
