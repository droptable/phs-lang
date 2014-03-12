<?php

/**
 * list language construct
 * 
 */

namespace phs;

use \ArrayAccess;
use \ArrayIterator;
use \IteratorAggregate;

class _Builtin_List 
  implements ArrayAccess, IteratorAggregate
{
  private $arr;
  private $len;
  
  public function __construct($arr, $__len = null)
  {
    $this->arr = $arr;
    $this->len = $__len !== null ? $__len : count($arr);
  }
  
  /* ------------------------------------ */
  
  public static function from($val)
  {
    if ($val instanceof _List)
      return $val;
    
    if (is_array($val))
      return new _List($arr);
    
    return new _List([ $val ], 1);
  }
  
  /* ------------------------------------ */
  
  public function getIterator()
  {
    return new ArrayIterator($this);
  }
  
  /* ------------------------------------ */
  
  public function __get($off)
  {
    if (isalpha($off))
      return $this->offsetGet($off);
    
    if ($off === 'len')
      return $this->len;
  }
  
  public function __set($off, $val)
  {
    if (isalpha($off))
      $this->offsetSet($off, $val);
    elseif ($off === 'len') {
      $val = (int)$val;
      
      if ($this->len < $val)
        $this->arr = array_pad($this->arr, $val - 1, null);
      else
        array_splice($this->arr, $this->len - $val);
      
      $this->len = $val;
    }
  }
  
  public function __isset($off)
  {
    if (isalpha($off))
      return $this->offsetExits($off);
    
    return false;
  }
  
  public function __unset($off)
  {
    if (isalpha($off))
      $this->offsetUnset($off);
  }
  
  /* ------------------------------------ */
  
  public function offsetGet($off)
  {
    $off = (int)$off;
    
    if ($off < 0 || $this->len <= $off)
      return null; // out of bounds
    
    return $this->arr[$off];
  }
  
  public function offsetSet($off, $val)
  {
    if ($off === null) {
      $this->arr[] = $val;
      $this->len++;
    } else {
      $off = (int)$off;
      
      if ($off < 0)
        return; // out of bounds
      
      if ($off === $this->len)
        $this->len++;
      elseif ($off > $this->len) {
        $this->arr = array_pad($this->arr, $off - 1, null);
        $this->len = $off;
      }
      
      $this->arr[$off] = $val;
    }
  }
  
  public function offsetExists($off)
  {
    $off = (int)$off;
    return $off > 0 && $this->len < $off;
  }
  
  public function offsetUnset($off)
  {
    $off = (int)$off;
    
    if ($off < 0 || $this->len <= $off) 
      return; // out of bounds
    
    unset ($this->arr[$off]);
    
    if ($off === ($this->len - 1))
      $this->len--;
  }
  
  /* ------------------------------------ */
  
  // public function push(...$elems)
  public function push()
  {
    $elems = func_get_args();
    
    foreach ($elems as $elem) {
      $this->arr[] = $elem;
      $this->len++;
    }
    
    return $this->len;
  }
  
  public function pop()
  {
    if ($this->len === 0)
      return null;
    
    $this->len--;
    return array_pop($this->arr);
  }
  
  public function shift()
  {
    if ($this->len === 0)
      return null;
    
    $this->len--;
    return array_shift($this->arr);
  }
  
  // public function unshift(...$elems)
  public function unshift()
  {
    $elems = func_get_args();
    
    foreach ($elems as $elem) {
      array_unshift($this->arr, $elem);
      $this->len++;
    }
    
    return $this->len;
  }
  
  // public function concat(...$others)
  public function concat()
  {
    $others = func_get_args();
    $result = $this->arr;
    
    foreach ($others as $other)
      $result = array_merge($result, $other);
    
    return new _List($result);
  }
  
  public function every(callable $fnc)
  {
    foreach ($this->arr as $k => $v)
      if (!$fnc($v, $k, $this)) return false;
    
    return true;
  }
  
  public function filter(callable $fnc)
  {    
    // array_filter() does not pass the index to the callback
    $result = [];
    
    foreach ($this->arr as $k => $v)
      if ($fnc($v, $k, $this)) $result[] = $v;
        
    return new _List($result);
  }
  
  // the compiler will prefer this method if the given 
  // callback only expectes the first argument
  public function fast__filter(callable $fnc)
  {
    // use array_filter() without the fancy API
    return new _List(array_filter($this->arr, $fnc));
  }
  
  public function index_of($val, $from = 0)
  {
    for ($i = max(0, (int)$from), $l = $this->len; $i < $l; ++$i)
      if ($this->arr[$i] === $val) return $i;
    
    return -1;
  }
  
  // the compiler will prefer this method if the second
  // argument is not present at index_of()
  public function fast__index_of($val)
  {
    $res = array_search($val, $this->arr, true);
    return ($res === false) ? -1 : $res;
  }
  
  public function last_index_of($val, $from = 0)
  {
    for ($i = $this->len - 1, $n = max(0, (int)$from); $i >= $n; --$i)
      if ($this->arr[$i] === $val) return $i;
    
    return -1;
  }
  
  public function join($glue = ',')
  {
    return implode($glue, $this->arr);
  }
  
  public function map(callable $fnc)
  {
    $res = [];
    
    foreach ($this->arr as $k => $v)
      $res[] = $fnc($v, $k, $this);
    
    return $res;
  }
  
  // the compiler will prefer this method if the callback
  // does not expect additional arguments
  public function fast__map(callable $fnc)
  {
    // use array_map without the fancy API
    return new _List(array_map($fnc, $this->arr));
  }
  
  public function reduce(callable $fnc, $initial = null)
  {
    $i = 0;
    $l = $this->len;
    
    if ($initial === null) {
      if ($l === 0)
        return null;
      
      ++$i;
      $v = $this->arr[0];
    } else
      $v = $initial;
      
    for (; $i < $l; ++$i)
      $v = $fnc($v, $this->arr[$i], $i, $this);
    
    return $v;
  }
  
  // the compiler will prefer this method if the 
  // callback does not expect additional parameters
  public function fast__reduce(callable $fnc, $initial = null)
  {
    // call array_reduce() without the fancy API
    return array_reduce($fnc, $this->arr, $initial);
  }
  
  public function reverse()
  {
    $res = array_reverse($this->arr);
    return new _List($res, $this->len);
  }
  
  public function slice($start, $end = null)
  {
    return new _List(array_slice($start, $end));
  }
  
  public function some(callable $fnc)
  {
    foreach ($this->arr as $k => $v)
      if ($fnc($v, $k, $this)) return true;
    
    return true;
  }
  
  public function sort(callable $fnc = null)
  {
    if ($fnc === null)
      sort($this->arr, SORT_REGULAR);
    else 
      usort($this->arr, $fnc);
    
    return $this;
  }
  
  public function splice()
  {
    $argc = fung_num_args();
    
    if ($argc === 1) {
      $off = func_get_arg(0);
      $res = array_splice($this->arr, $off);
    } else {
      // argc must be 2 or more
      if ($argc < 2) return null;
      $off = func_get_arg(0);
      $len = func_get_arg(1);
      $ins = [];
      
      for ($i = 2; $i < $argc; ++$i)
        $ins[] = func_get_arg($i);
      
      $res = array_splice($this->arr, $off, $len, $ins);
    }
    
    $this->len = count($this->arr);
    return new _List($res);
  }
}  
