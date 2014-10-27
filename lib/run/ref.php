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

/**
 * reference wrapper.
 * this class gets used for call-time-pass-by-reference,
 * but can also be used for user-defined references
 */
class Ref extends Obj 
{
  // @var mixed
  private $val;
  
  /**
   * constructor
   *
   * @param mixed $val
   */
  public function __construct(&$val)
  {
    parent::__construct();
    $this->val = $val;  
  }
  
  /**
   * forward calls to the value
   *
   * @param  string $name
   * @param  array $args
   * @return mixed
   */
  public function __call($name, $args)
  {
    return $this->val->{$name}(...$args);
  }
  
  /**
   * forward getter to the value
   *
   * @param  string $name
   * @return mixed
   */
  public function __get($name)
  {
    return $this->val->{$name};
  }
  
  /**
   * forward setter to the value
   *
   * @param string $name 
   * @param mixed $value
   */
  public function __set($name, $value)
  {
    $this->val->{$name} = $value;
  }
  
  /**
   * dereference
   *
   * @return mixed
   */
  public function & unwrap()
  {
    return $this->val;
  }
}
