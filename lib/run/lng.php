<?php
/*!
 * This file is part of the PHS Runtime Library
 * Copyright (c) 2014 The PHS Team 
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
 * enables the "in" operator for objects
 * 
 * obj = new SomethingInable;
 * res = val in obj; // -> res = obj.contains(val)
 */
interface Inable {
  /**
   * should check if a value exists inside this object
   *
   * @param  mixed val
   * @return boolean
   */
  public function contains($val);
}
