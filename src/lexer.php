<?php

namespace phs;

require_once 'glob.php';

/** ascii tokens */
const 
  T_DOLLAR = 36,   // '$'
  T_ASSIGN = 61,   // '='
  T_LT = 60,       // '<'
  T_GT = 62,       // '>'
  T_PLUS = 43,     // '+'
  T_MINUS = 45,    // '-'
  T_DIV = 47,      // '/'
  T_MUL = 42,      // '*'
  T_MOD = 37,      // '%'
  T_CONCAT = 126,  // '~'
  T_BIT_NOT = 126, // '~'
  T_BIT_OR = 124,  // '|'
  T_BIT_AND = 38,  // '&'
  T_BIT_XOR = 94,  // '^'
  T_TREF    = 38,  // '&' 
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

const
  T_APLUS = 259,
  T_AMINUS = 260,
  T_AMUL = 261,
  T_ADIV = 262,
  T_AMOD = 263,
  T_APOW = 264,
  T_ACONCAT = 265,
  T_ABIT_OR = 266,
  T_ABIT_AND = 267,
  T_ABIT_XOR = 268,
  T_ABOOL_OR = 269,
  T_ABOOL_AND = 270,
  T_ABOOL_XOR = 271,
  T_ASHIFT_L = 272,
  T_ASHIFT_R = 273,
  T_AREF = 274
;

const 
  T_ARR = 257,
  T_YIELD = 258,
  T_RANGE = 275,
  T_BOOL_OR = 276,
  T_BOOL_XOR = 277,
  T_BOOL_AND = 278,
  T_EQ = 279,
  T_NEQ = 280,
  T_IN = 281,
  T_NIN = 282,
  T_IS = 283,
  T_NIS = 284,
  T_GTE = 285,
  T_LTE = 286,
  T_SL = 287,
  T_SR = 288,
  T_AS = 289,
  T_REST = 290,
  T_DEL = 291,
  T_INC = 292,
  T_DEC = 293,
  T_POW = 294,
  T_NEW = 295,
  T_DDDOT = 296,
  T_FN = 297,
  T_LET = 298,
  T_USE = 299,
  T_ENUM = 300,
  T_TYPE = 301,
  T_CLASS = 302,
  T_TRAIT = 303,
  T_IFACE = 304,
  T_MODULE = 305,
  T_REQUIRE = 306,
  T_IDENT = 307,
  T_LNUM = 308,
  T_DNUM = 309,
  T_SNUM = 310,
  T_STRING = 311,
  T_REGEXP = 312,
  T_TRUE = 313,
  T_FALSE = 314,
  T_NULL = 315,
  T_THIS = 316,
  T_SUPER = 317,
  T_SELF = 318,
  T_GET = 319,
  T_SET = 320,
  T_DO = 321,
  T_IF = 322,
  T_ELIF = 323,
  T_ELSE = 324,
  T_FOR = 325,
  T_TRY = 326,
  T_GOTO = 327,
  T_BREAK = 328,
  T_CONTINUE = 329,
  T_PRINT = 330,
  T_THROW = 331,
  T_CATCH = 332,
  T_FINALLY = 333,
  T_WHILE = 334,
  T_ASSERT = 335,
  T_SWITCH = 336,
  T_CASE = 337,
  T_DEFAULT = 338,
  T_RETURN = 339,
  T_CONST = 340,
  T_FINAL = 341,
  T_GLOBAL = 342,
  T_STATIC = 343,
  T_EXTERN = 344,
  T_PUBLIC = 345,
  T_PRIVATE = 346,
  T_PROTECTED = 347,
  T_SEALED = 348,
  T_INLINE = 349,
  T_UNSAFE = 350,
  T_NATIVE = 351,
  T_HIDDEN = 352,
  T_REWRITE = 353,
  T_PHP = 354,
  T_TEST = 355,
  T_NL = 356,
  T_SUBST = 357,
  T_TINT = 358,
  T_TBOOL = 359,
  T_TFLOAT = 360,
  T_TSTR = 361,
  T_TTUP = 362,
  T_TDEC = 363,
  T_TANY = 364,
  T_CDIR = 365,
  T_CFILE = 366,
  T_CLINE = 367,
  T_CCOLN = 368,
  T_CFN = 369,
  T_CCLASS = 370,
  T_CTRAIT = 371,
  T_CMETHOD = 372,
  T_CMODULE = 373,
  T_INVL = 374,
  T_END = 375
;

/**
 * lexer class
 * produces tokens from a given input-string
 * 
 * TODO: fix string-interpolation lexing/parsing
 */
class Lexer
{
  // sync-mode
  public $sync = false;
  
  // the source
  private $data;
  
  // the current line and column
  private $line, $coln;
  
  // name of file
  private $file;
  
  // token queue used as cache for regexps
  private $queue = [];
  
  // substitution flag
  private $subst = 0;
  
  // substitutio stack
  private $subsk = [];
  
  // substitution end-quotation ( " or ' )
  private $subqt;
  
  // location of string-interpolation start
  private $sublc;
  
  // substitution l-brace count
  private $subbc = 0;
  
  // track new-lines "\n"
  private $tnl = false;
  
  // next '<' is part of a type-name
  private $genc = false;
  
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
  public function __construct(Source $src)
  {
    $this->line = 1;
    $this->coln = 1;
    $this->data = $src->get_data();
    $this->file = $src->get_path();
    $this->dir = dirname($this->file);
    
    // load pattern
    if (!self::$re)
      self::$re = file_get_contents(__DIR__ . '/lexer.re');
    /*
    for ($i = 0; $i < 100; ++$i) {
      $tok = $this->next();
      
      print "\n";
      Logger::debug('state = %d, braces = %d', $this->subst, $this->subbc);
      print "\n";
      $tok->debug();
      
      if ($tok->type === T_EOF)
        break;
    }
    
    exit;
    */
  }
  
  /**
   * returns the directory of the current file
   *
   * @return string
   */
  public function get_dir()
  {
    return $this->dir;
  }
  
  /**
   * returns the current file
   * 
   * @return string
   */
  public function get_file()
  {
    return $this->file;
  }
  
  /**
   * returns the current line
   * 
   * @return string
   */
  public function get_line()
  {
    return $this->line;
  }
  
  /**
   * error handler
   * 
   * @return void
   */
  public function loc($line = null, $coln = null)
  {
    if ($line === null) $line = $this->line;
    if ($coln === null) $coln = $this->coln;
    
    return new Location($this->file, new Position($line, $coln));
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
    
    return $tok;
  }
  
  /**
   * peeks a token
   * 
   * @param  int   $num
   * @return Token
   */
  public function peek($num = 1)
  {
    assert(isset ($this->queue));
    
    $avl = count($this->queue);
    
    for (; $avl < $num; ++$avl)
      array_push($this->queue, $this->scan());
    
    return $this->queue[$num - 1];
  }
  
  /**
   * pushes a token onto the queue 
   * 
   * @param  Token  $t
   */
  public function push(Token $t)
  {
    if (!isset ($this->queue))
      return;
    
    array_push($this->queue, $t);
  }
  
  /**
   * skips a token
   * 
   */
  public function skip()
  {
    if (!empty ($this->queue))
      array_shift($this->queue);
    else
      $this->scan();
  }
  
  /**
   * returns the current look-ahead queue
   *
   * @return array
   */
  public function get_queue()
  {
    return $this->queue;
  }
  
  /**
   * sets the look-ahead queue
   *
   * @param array $nq
   */
  public function set_queue(array $nq) 
  {
    $this->queue = $nq;
  }
  
  /**
   * adds a array-of-tokens to the current look-ahead queue
   *
   * @param array $aq
   */
  public function add_queue(array $aq)
  {
    foreach ($aq as $tok)
      $this->queue[] = $tok;
  }
  
  /**
   * returns a string-representation of a token-id
   *
   * @param  Token|int  $tok
   * @return string
   */
  public function lookup($tok)
  {
    $req = -1;
    
    if (is_int($tok))
      $req = $tok;
    else {
      assert($tok instanceof Token);
      $req = $tok->type;
    }
    
    switch ($req) {
      case T_EOF:
        return 'end-of-file';
      case T_INVL:
        return '{invalid token}';
      case T_LNUM: case T_DNUM:
        return 'number';
      case T_IDENT:
        return 'ident';
      case T_STRING:
        return 'string';
      case T_REGEXP:
        return 'regular expression';
    }
    
    foreach (self::$rids as $str => $rid)
      if ($req === $rid)
        return "`$str`";
      
    foreach (self::$table as $str => $tid)
      if ($req === $tid)
        return "\"$str\"";
      
    return '??? (id=' . $req . ')';
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
    
    if ($this->eof === true || $this->ends())
      return $this->scan_eof();
    
    // in substitution
    switch ($this->subst) {
      case 1: // start
      case 3: // after ${...}
        $this->subst += 1;
        return $this->token(T_SUBST, '${...}', true);
        
      case 2: // during ${ -> ... <- }
        $tok = $this->scan_token();
        
        // if a '}' gets scanned and no open '{' are left:
        // -> switch to state 3
        if ($tok->type === T_RBRACE && --$this->subbc === 0)
          $this->subst = 3;
        elseif ($tok->type === T_LBRACE)
          ++$this->subbc;
        
        return $tok;
      
      case 4: // repeat or end interpolation
        return $this->scan_string($this->subqt, true);
        
      default: // no state
        return $this->scan_token();
    }
  }
  
  /**
   * scans the eof-token
   *
   * @return Token
   */
  protected function scan_eof()
  {
    // free remaining data
    unset ($this->data);
    
    if ($this->subst !== 0) {
      Logger::error_at($this->loc(), 'unterminated string-literal');
      Logger::error_at($this->sublc, 'string started here');
      $this->subst = 0;
    }
    
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
  
  /**
   * scans a common token
   *
   * @return Token
   */
  protected function scan_token()
  {
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
        
        Logger::error_at($this->loc(), 'invalid input near: %s [...]',
          strtr(substr($this->data, 0, 10), [ "\n" => '\\n' ]));
        
        return $this->scan_eof();
      }
      
      // start scan
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
      
      // comments
      if (preg_match('/^(?:[#]|\/[*\/])/', $sub)) {
        // update end line/coln
        $this->adjust_line_coln_end($sub, 0);
        
        // handle <eof> if the comment was at the end of our input
        if ($this->ends()) 
          return $this->scan_eof();
        
        continue; // continue otherwise
      }
      
      $str = null;
      if (preg_match('/^([cor])?(["\'])/', $sub, $str)) {
        if ($str[1] === 'r') {
          // raw string
          $tok = $this->token(T_STRING, substr($sub, 2, -1));
          // update end line/coln
          $this->adjust_line_coln_end($sub, 0);
        } else 
          // flag === '' (none) 
          // flag === 'c' (constant / constant-expression)
          // flag === 'o' (object [instance of class `Str`] - unused atm)
          // start advanced string-scanner
          $tok = $this->scan_string($str[2]);
        
        $tok->flag = $str[1];
        $tok->delim = $str[2];
      } else {
        // update end line/coln
        $this->adjust_line_coln_end($sub, 0);
        
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
   * scans a string with optional interpolation
   *
   * @param  string $dlm
   * @param  boolean $loc
   * @return Token
   */
  protected function scan_string($dlm, $loc = false)
  {
    $str = '';
    $inp = $this->data;  // does not get modified during scan, 
                         // so this is the same as a reference
    $len = strlen($inp);
    
    // scanner vars
    $dol = false; // seen '$'
    $esc = false; // seen '\'
    $hex = false; // seen "\x" or "\X"
    $end = -1;    // end of slice/string
    $eos = false; // seen ending delimiter
    
    // hex-scanner vars
    $hex_chr = ''; // hex char 'x' or 'X'
    $hex_buf = ''; // hex buffer
    $hex_len = 0;  // hex length
    
    // modifing line & coln directly would corrupt locations
    $line = $this->line;
    $coln = $this->coln;
    
    for ($idx = 0;;) { // <- used for concatenation
      for (; $idx < $len && !$eos; ++$idx) {
        $raw = $inp[$idx];
        $chr = $raw; // may get modified
        
        if ($esc) {
          switch ($chr) {
            case 'n': $chr = "\n"; break; 
            case 'r': $chr = "\r"; break;          
            case 't': $chr = "\t"; break;         
            case 'f': $chr = "\f"; break;          
            case 'v': $chr = "\v"; break;          
            case 'e': $chr = "\e"; break;
            case '0': $chr = "\0"; break; // TODO: remove?
            case 'x': 
            case 'X':
              // start hex scanner
              $hex_chr = $chr;
              $hex_len = 0;
              $hex_buf = '';
              $hex = true; 
              $chr = '';
              break;
            default: 
              // unknown escape-sequence
              $str .= '\\';
          }
          
          $str .= $chr;
          $esc = false;
        } else {
          if ($hex) {
            // scan a hex-char
            if (ctype_xdigit($chr)) {
              $hex_buf .= $chr;
              
              if (++$hex_len === 2) {
                $str .= chr(hexdec($hex_buf));
                $hex = false;
              }
            } else {
              $str .= '\\';
              $str .= $hex_chr;
              $str .= $hex_buf;
              $str .= $chr;
              $hex = false;
            }
          } else {
            // handle non-escaped data
            if ($dol && $chr === '{') {
              /* start interpolation */
              $eos = false;
              $end = $idx;
              break;
            }
            
            if ($dol) {
              $str .= '$';
              $dol = false;
            }
            
            switch ($chr) {
              case '$':  $dol = true; break;
              case '\\': $esc = true; break;
              case $dlm: 
                $eos = true; 
                $end = $idx + 1; 
                break;
              default:
                $str .= $chr;
            }
          }
        }
        
        if ($raw === "\n") {
          $line += 1;
          $coln = 1;
        } else
          $coln += 1;
      }
      
      // break if concatenation is not possible
      if (!$eos || $dlm !== '"' || $end + 1 >= $len)
        break;
      
      // skip whitespace between strings
      $tl = $line;
      $tc = $coln;
      
      for (; $idx < $len && ctype_space($inp[$idx]); ++$idx)
        if ($inp[$idx] === "\n") {
          $tl += 1;
          $tc = 1;
        } else
          $tc += 1;
      
      // if the next char is a " -> start concatenation
      if ($idx >= $len || $inp[$idx] !== '"')
        break;
      
      $idx += 1;
      $eos = false;
      
      $line = $tl;
      $coln = $tc;
    }
    
    $this->data = substr($this->data, $end);
    
    if ($eos) {
      // don't change state if we're waiting for a '}'
      if ($this->subbc === 0)
        // pop state
        if ($this->subst === 4 && !empty($this->subsk)) {
          $prev = array_pop($this->subsk);
          $this->subst = 2;
          $this->subbc = $prev[0];
          $this->subqt = $prev[1];  
          $this->sublc = $prev[2];      
        } else
          $this->subst = 0;
    } else {     
      // push state
      if ($this->subst === 2) {
        array_push($this->subsk, [
          $this->subbc,
          $this->subqt,
          $this->sublc
        ]);
      }
      
      $this->subst = 1;
      $this->subbc = 0;
      $this->subqt = $dlm;
      $this->sublc = $this->loc();
    }
    
    $tok = $this->token(T_STRING, $str, $loc);
    
    $this->line = $line;
    $this->coln = $coln;
    
    return $tok;
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
      Logger::warn_at($this->loc(), 
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
    static $re_aref = '/^=\s*&/';
    
    static $eq_err = "found '%s', used '%s' instead";
    
    $tok = null;
    
    if (preg_match($re_base, $sub)) {
      $prf = substr($sub, 0, 2);
      if ($prf === '0x' || $prf === '0X')
        $num = hexdec(substr($sub, 2));
      else
        $num = bindec(substr($sub, 2));
      $tok = $this->token(T_LNUM, $num);
      $tok->suffix = null;
    } elseif (preg_match($re_dnum, $sub)) {
      // double
      $tok = $this->token(T_DNUM, $sub);
    } elseif (preg_match($re_lnum, $sub, $m)) {
      // integer (with optional suffix)
      $tid = !empty($m[2]) ? T_SNUM : T_LNUM; 
      $tok = $this->token($tid, $m[1]);
      $tok->suffix = $tid === T_SNUM ? $m[2] : null;
    } elseif (preg_match($re_aref, $sub)) {
      // LALR generator would be able to handle <left '=' '&' right> in the grammar.
      // this little "hack" reduces the parser-table sizes a bit
      $tok = $this->token(T_AREF, $sub);
    } else {
      // warn about '===' and '!=='
      if ($sub === '===' || $sub === '!==')
        Logger::warn_at($this->loc(), $eq_err, $sub, substr($sub, 0, -1));
      
      if (isset(self::$table[$sub]))         
        // lookup token-table and check if the token is separator/operator  
        $tok = $this->token(self::$table[$sub], $sub);
      elseif (!$this->tnl && isset(self::$rids[$sub]))
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
    
    if ($this->data === null)
      return true;
    
    // if $tnl (track new lines) is true: use \h, else: use \h\v
    return preg_match($this->tnl ? $re_nnl : $re_all, $this->data);
  }
  
  /* ------------------------------------ */
  
  /**
   * checks is a token is defined as operator
   *
   * @param  Token|int  $tok
   * @return boolean
   */
  public static function is_op($tok)
  {
    if ($tok instanceof Token)
      $tok = $tok->type;
    else
      assert(is_int($tok));
    
    foreach (self::$table as $str => $tid)
      if ($tid === $tok) return true;
    
    return false;
  }
  
  /**
   * checks if a token is defined as reserved identifier
   *
   * @param  Token|int  $tok
   * @return boolean
   */
  public static function is_rid($tok)
  {
    if ($tok instanceof Token)
      $tok = $tok->type;
    else
      assert(is_int($tok));
    
    foreach (self::$rids as $rid => $tid)
      if ($tid === $tok) return true;
    
    return false;
  }
  
  /* ------------------------------------ */
    
  private static $rids = [   
    'fn' => T_FN,
    'let' => T_LET,
    'use' => T_USE,
    'enum' => T_ENUM,
    'type' => T_TYPE,
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
    'self' => T_SELF,

    'get' => T_GET,
    'set' => T_SET,

    'do' => T_DO,
    'if' => T_IF,
    'elif' => T_ELIF,
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
    'print' => T_PRINT,

    'const' => T_CONST,
    'final' => T_FINAL,
    'static' => T_STATIC,
    'extern' => T_EXTERN,
    'public' => T_PUBLIC,
    'private' => T_PRIVATE,
    'protected' => T_PROTECTED,
    
    // TODO: replace keywords with attributes
    '__sealed__' => T_SEALED, // @[sealed] fn ...
    '__inline__' => T_INLINE, // @[inline] fn ...
    '__global__' => T_GLOBAL, // @[global] ...
    '__unsafe__' => T_UNSAFE, // @[unsafe] ...
    '__native__' => T_NATIVE, // @[native] ...
    '__hidden__' => T_HIDDEN, // @[hidden] ...
    '__rewrite__' => T_REWRITE,
    
    '__php__' => T_PHP,
    '__test__' => T_TEST,
        
    '__end__' => T_END,
    
    'yield' => T_YIELD,
    'new' => T_NEW,
    'del' => T_DEL,
    'as' => T_AS,
    'is' => T_IS,
    '!is' => T_NIS,
    'in' => T_IN,
    '!in' => T_NIN,
      
    'int' => T_TINT,
    'dbl' => T_TFLOAT,
    'float' => T_TFLOAT,
    'tup' => T_TTUP,
    'bool' => T_TBOOL,
    'str' => T_TSTR,
    'dec' => T_TDEC, /* int or float */  
    
    // hardcoded "special" constants
    '__dir__'  => T_CDIR,
    '__file__' => T_CFILE,
    '__line__' => T_CLINE,
    '__coln__' => T_CCOLN,
    
    '__fn__' => T_CFN,
    '__class__' => T_CCLASS,
    '__trait__' => T_CTRAIT,
    '__method__' => T_CMETHOD,
    '__module__' => T_CMODULE
  ];
  
  // some tokens are ascii-tokens, see comment at the top of this file
  private static $table = [   
    // this is a special token used for new-lines
    "\n" => T_NL,
    
    '$' => T_DOLLAR,
    
    '=' => T_ASSIGN,
    '+=' => T_APLUS,
    '-=' => T_AMINUS,
    '*=' => T_AMUL,
    '**=' => T_APOW,
    '/=' => T_ADIV,
    '%=' => T_AMOD,
    '~=' => T_ACONCAT,
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
    '<>' => T_NEQ,
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
