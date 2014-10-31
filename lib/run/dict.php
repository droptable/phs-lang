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

use InvalidArgumentException as IAEx;

/**
 * helper function
 *
 * @param  Dict   $dict
 * @return array
 */
function get_dict_vars(Dict $dict) {
  return get_object_vars($dict);
}

/**
 * dictionary class
 * this class is used for { ... } literals
 */
class Dict extends Obj
{
  /**
   * constructor
   *
   * @param array  $dict  an array with key-value pairs for this dict
   */
  public function __construct(array $dict = [])
  {
    parent::__construct();
    
    // this can be a performance problem
    // TODO: find a way to optimize this
    foreach ($dict as $key => &$val)
      $this->{$key} = $val;
  }
  
  /**
   * __clone
   *
   * @return void
   */
  public function __clone()
  {
    foreach (get_dict_vars($this) as $key => &$val)
      if (is_object($val))
        $this->{$key} = clone $val;
  }
  
  /**
   * type-cast
   *
   * @param  mixed $val
   * @return Dict
   */
  public static function from($val)
  {
    if ($val instanceof self)
      return $val;
    
    if (is_array($val))
      return new static($val);
    
    throw new Exception("unable to create dict from " . gettype($val));
  }
  
  /**
   * returns a generator with all key/value pairs in this dict
   * 
   * @return Iterable
   */
  public function iter()
  {
    return get_dict_vars($this);
  }
      
  /**
   * returns a value of the given key
   *
   * @param  string $key
   * @return mixed
   */
  public function get($key) 
  {
    $key = (string) $key;
    return isset ($this->{$key}) ? $this->{$key} : null;
  }
  
  /**
   * sets a key/value pair
   *
   * @param string $key
   * @param mixed $val
   */
  public function set($key, $val)
  {
    $key = (string) $key;
    $this->{$key} = $val;
  }
  
  /**
   * checks if a key is set (and not null)
   *
   * @param  string  $key
   * @return boolean
   */
  public function has($key)
  {
    $key = (string) $key;
    return isset ($this->{$key});
  }
  
  /**
   * changes a key using a new value.
   *
   * @param  string $key
   * @param  mixed $val
   * @return mixed      the old value
   */
  public function swap($key, $val)
  {
    $key = (string) $key;
    $prv = null;
    
    if (isset ($this->{$key}))
      $prv = $this->{$key};
    
    $this->{$key} = $val;
    return $prv;
  }
  
  /**
   * deletes a key/value pair
   *
   * @param  string $key
   * @return boolean
   */
  public function delete($key)
  {
    $key = (string) $key;
    if (!isset ($this->{$key}))
      return false;
    
    unset ($this->{$key});
    return true;    
  }
}
