<?php
/*!
 * This file is part of the PHS Standard Library
 * Copyright (c) 2014 Andre "asc" Schmidt 
 * 
 * All rights reserved.
 * 
 * This library is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published 
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

/** list - note: the "_" is required */
class List_ extends Obj implements ArrayAccess
{
  // internal array
  private $mem;
  
  /**
   * constructor
   *
   * @param ... $args
   */
  public function __construct(...$args)
  {
    parent::__construct();
    $this->mem = $args;
  }
  
  /**
   * to-string method
   *
   * @return string
   */
  public function __tostring()
  {
    return $this->join();
  }
  
  /**
   * type-cast
   *
   * @param  mixed $val
   * @return List_
   */
  public static function from($val)
  {
    if (is_array($val))
      return new static(...$val);
    
    if ($val instanceof Dict)
      return new static(...$val->iter());
    
    return new static($val);
  }
  
  /**
   * returns a value at the given index
   *
   * @param  int $idx
   * @return mixed
   */
  public function get($idx)
  {
    $idx = (int) $idx;
    
    if ($idx < 0)
      $idx = $this->size() + $idx;
    
    return isset ($this->mem[$idx]) ? $this->mem[$idx] : null;
  }
  
  /**
   * sets a value at the given index
   *
   * @param int $idx
   * @param mixed $val
   */
  public function set($idx, $val)
  {
    $idx = (int) $idx;
    
    if ($idx < 0)
      $idx = $this->size() + $idx;
    
    $this->mem[$idx] = $val;
  }
  
  /**
   * checks if a value exists at the given index
   *
   * @param  int  $idx
   * @return boolean
   */
  public function has($idx)
  {
    $idx = (int) $idx;
    
    if ($idx < 0)
      $idx = $this->size() + $idx;
    
    return isset ($this->mem[$idx]);
  }
  
  /**
   * deletes a index.
   * the list gets rearranged after deletion!
   * 
   * if you just want to delete a value, 
   * use `set(idx, null)` instead.
   *
   * @param  int $idx
   * @return boolean
   */
  public function delete($idx)
  {
    $idx = (int) $idx;
    
    if ($idx < 0)
      $idx = $this->size() + $idx;
    
    if (array_key_exists($idx, $this->mem)) {
      array_splice($this->mem, $idx, 0);
      return true;
    }
    
    return false;
  }
  
  /* ------------------------------------ */
  
  /**
   * ArrayAccess#offsetGet()
   * 
   * @see List_#get()
   */
  public function offsetGet($idx)
  {
    $idx = (int) $idx;
    
    if ($idx < 0)
      $idx = $this->size() + $idx;
    
    return isset ($this->mem[$idx]) ? $this->mem[$idx] : null;
  }
  
  /**
   * ArrayAccess#offsetSet()
   * 
   * @see List_#set()
   */
  public function offsetSet($idx, $val)
  {
    $idx = (int) $idx;
    
    if ($idx < 0)
      $idx = $this->size() + $idx;
    
    $this->mem[$idx] = $val;
  }
  
  /**
   * ArrayAccess#offsetExists()
   * 
   * @see List_#has()
   */
  public function offsetExists($idx)
  {
    $idx = (int) $idx;
    
    if ($idx < 0)
      $idx = $this->size() + $idx;
    
    return isset ($this->mem[$idx]);
  }
  
  /**
   * ArrayAccess#offsetUnset()
   * 
   * @see List_#delete()
   */
  public function offsetUnset($idx)
  {
    $idx = (int) $idx;
    
    if ($idx < 0)
      $idx = $this->size() + $idx;
    
    if (array_key_exists($idx, $this->mem)) {
      array_splice($this->mem, $idx, 0);
      return true;
    }
    
    return false;
  }
  
  /* ------------------------------------ */
  
  /**
   * returns the size of this list
   *
   * @return int
   */
  public function size()
  {
    return count($this->mem);
  }
  
  /* ------------------------------------ */
  /* mutator methods */
  
  /**
   * sets the internal array.
   *
   * @param  array  $mem
   * @return List_
   */
  public function swap(array $mem)
  {
    // the new array must be a tuple
    // associative keys can not be accessed in this list
    $this->mem = $mem;
    return $this;
  }
  
  /**
   * fills this list with values
   *
   * @param  mixed  $val
   * @param  int    $idx
   * @param  int    $end
   * @return List_
   */
  public function fill($val, $idx = 0, $end = null)
  {
    $len = $this->size();
    
    if ($end === null)
      $end = $len;
    
    $idx = (int) $idx;
    $end = (int) $end;
    
    if ($idx < 0)
      $idx = $len + $idx;
    
    if ($end < 0)
      $end = $len + $end;
    
    while ($idx < $end) {
      $this->mem[$idx] = $val;
      $idx++;
    }
    
    return $this;
  }
  
  /**
   * removes the last element from this list
   *
   * @return mixed
   */
  public function pop()
  {
    return array_pop($this->mem);
  }
  
  /**
   * appends values to the list
   *
   * @param  ... $val
   * @return int
   */
  public function push(...$val)
  {
    return array_push($this->mem, ...$val);
  }
  
  /**
   * reverses the list
   *
   * @return List_
   */
  public function reverse()
  {
    $this->mem = array_reverse($this->mem);
    return $this;
  }
  
  /** 
   * removes the first element
   *
   * @return mixed
   */
  public function shift()
  {
    return array_shift($this->mem);
  }
  
  /**
   * sorts the list in place and returns it
   *
   * @param  callable  $fun
   * @return List_
   */
  public function sort(callable $fun = null)
  {
    if ($fun === null)
      sort($this->mem, SORT_REGULAR);
    else
      usort($this->mem, $fun);
    
    return $this;
  }
  
  /**
   * add/removes values
   *
   * @param  int $idx
   * @param  int $len
   * @param  ... $val
   * @return List_
   */
  public function splice($idx, $len, ...$val)
  {
    $lst = new static;
    $lst->mem = array_splice($this->mem, $idx, $len, $val);
    return $lst;
  }
  
  /**
   * adds elements at the beginning
   *
   * @param  ... $val
   * @return List_
   */
  public function unshift(...$val)
  {
    array_unshift($this->mem, ...$val);
    return $this;
  }
  
  /**
   * shuffles the list
   *
   * @return List_
   */
  public function shuffle()
  {
    array_shuffle($this->mem);
    return $this;
  }
  
  /* ------------------------------------ */
  /* accessor methods */
  
  /**
   * returns a new list with all the arguments concatenated
   *
   * @param  ... $val
   * @return List_
   */
  public function concat(...$val)
  {
    $dup = $this->mem;
    
    foreach ($val as $itm)
      if (is_array($itm))
        $dup = array_merge($dup, $itm);
      else
        $dup[] = $itm;
    
    $lst = new static;
    $lst->mem = $dup;
    return $lst;
  }
  
  /**
   * joins all values together
   *
   * @param  string $sep
   * @return string
   */
  public function join($sep = ',')
  {
    implode($sep, $this->mem);
  }
  
  
  /**
   * extract a slice of the list
   *
   * @param  int $beg
   * @param  int $end
   * @return List_
   */
  public function slice($beg = null, $end = null)
  {
    $dup = $this->mem;
    
    if ($beg !== null) {
      $len = $this->size();
      $beg = (int) $beg;
      $end = (int) $end;
      
      if ($beg < 0)
        $beg = $len + $beg;
      
      if ($end < 0)
        $end = $len + $end;
                
      $dup = array_slice($dup, $beg, $end);
    }
    
    $lst = new static;
    $lst->mem = $dup;
    return $lst;
  }
  
  /**
   * searches for the given value ant returns its index
   *
   * @param  mixed  $val
   * @param  int    $beg
   * @return int
   */
  public function index_of($val, $beg = 0)
  {
    if ($beg === 0) {
      $res = array_search($this->mem, $val, true);
      return $res === false ? -1 : $res;
    }
    
    $len = $this->size();
    $beg = (int) $beg;
    
    if ($beg < 0)
      $beg = $len + $beg;
    
    if ($len === 0 || $beg >= $len)
      return -1;
          
    while ($beg < $len) {
      if ($this->mem[$beg] === $val)
        return $beg;
      
      $beg++;
    }    
    
    return -1;
  }
  
  /* ------------------------------------ */
  /* iteration methods */
  
  /**
   * iterator
   *
   * @return Iterable
   */
  public function iter()
  {
    return $this->mem;
  }
  
  /**
   * calls a function for each item in this list.
   * same as map() but without generating a new list
   *
   * @param  callable $fun
   */
  public function each(callable $fun)
  {
    array_walk($this->mem, $fun, $this);
  }
  
  /**
   * returns true if all values pass the test implemented 
   * by the given callback
   *
   * @param  callable $fun
   * @return bool
   */
  public function every(callable $fun)
  {
    foreach ($this->mem as $idx => $itm)
      if (!$fun($itm, $idx, $this))
        return false;
      
    return true;
  }
  
  /**
   * returns true if at least one value passes the 
   * test implemented by the given callback
   *
   * @param  callable $fun
   * @return bool
   */
  public function some(callable $fun)
  {
    foreach ($this->mem as $idx => $itm)
      if ($fun($itm, $idx, $this))
        return true;
      
    return false;
  }
  
  /**
   * filters the list using the given callback
   *
   * @param  callable $fun
   * @return List_
   */
  public function filter(callable $fun)
  {
    $lst = new static;
    $lst->mem = array_filter($this->mem, $fun, ARRAY_FILTER_USE_BOTH);
    return $lst;
  }
  
  /**
   * searches for a value using the given callback
   *
   * @param  callable $fun
   * @return mixed
   */
  public function find(callable $fun)
  {
    foreach ($this->mem as $idx => $val) 
      if ($fun($val, $idx, $this))
        return $val;
      
    return null;
  }
  
  /**
   * returns all keys
   *
   * @return Iterable
   */
  public function keys()
  {
    foreach ($this->mem as $idx => &$_)
      yield $idx;
  }
  
  /**
   * creates a new list by calling the given 
   * callback for each item
   *
   * @param  callable $fun
   * @return List_
   */
  public function map(callable $fun)
  {
    $lst = new static;
    $lst->mem = array_map($fun, $this->mem)
    return $lst;
  }
  
  /** 
   * reduces the list to a single value using the given callback function
   *
   * @param  callable $fun
   * @return mixed
   */
  public function reduce(callable $fun, $carry = null)
  {
    if ($carry === null)
      return array_reduce($this->mem, $fun);
    
    return array_reduce($this->mem, $fun, $carry);
  }
  
  /** 
   * reduces the list from right-to-left to a single 
   * value using the given callback function
   *
   * @param  callable $fun
   * @return mixed
   */
  public function reduce_right(callable $fun, $carry = null)
  {
    $len = $this->size();    
    $val = $carry;
    
    if ($len === 0)
      return $val;
    
    $idx = $len - 1;
    
    if ($val === null) {
      $val = $this->mem[$idx];
      $idx -= 1;
    }
    
    while ($idx >= 0) {
      $val = $fun($val, $this->mem[$idx] /* , $idx, $this */);
      $idx--;
    }
    
    return $val; 
  }
  
  /* ------------------------------------ */
  /* php coverage */
  
  // http://de1.php.net/manual/en/function.array-chunk.php
  public function chunk($size)
  {
    $lst = new static;
    $lst->mem = array_map(function($mem) {
      $lst = new static;
      $lst->mem = $mem;
      return $lst;
    }, array_chunk($this->mem, $size));
    return $lst;
  }
    
  /**
   * probably a more convenient version of array_column()
   * since the phs-language does not have associative arrays
   *
   * @param  string $prop
   * @return List_
   */
  public function pluck($prop)
  {
    $prop = (string) $prop;
    return array_map(function($val) use (&$prop) {
      return $val->{$prop};
    }, $this->mem);
  }
}

