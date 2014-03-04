<?php

namespace phs\ast;

abstract class Expr extends Node
{
  // if the expression is reduible at compiletime
  public $value;
}
