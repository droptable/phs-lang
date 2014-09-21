<?php

namespace phs\util;

use \RuntimeException as RTEx;

const 
  RES_KIND_SOME = 1,
  RES_KIND_NONE = 2,
  RES_KIND_ERROR = 3
;

/** wrapper for <results> (Some/None/Error) */
class Result
{
  // @var int
  public $kind;
  
  // @var mixed
  public $data;
  
  /**
   * constructor
   *
   * @param int $kind
   * @param mixed $data
   */
  public function __construct($kind, $data = null)
  {
    $this->kind = $kind;
    $this->data = $data;
  }
  
  /**
   * unwraps the data
   *
   * @return mixed
   */
  public function &unwrap()
  {
    return $this->data;
  }
  
  /**
   * checks if this result is "some"
   *
   * @return boolean
   */
  public function is_some()
  {
    return $this->kind === RES_KIND_SOME;
  }
  
  /**
   * converts this result to "some"
   *
   * @param  mixed $data
   */
  public function to_some($data)
  {
    $this->kind = RES_KIND_SOME;
    $this->data = $data;
  }
  
  /**
   * checks if this result is "none"
   *
   * @return boolean
   */
  public function is_none()
  {
    return $this->kind === RES_KIND_NONE ||
           $this->kind === RES_KIND_ERROR;
  }
  
  /**
   * converts this result to a "none" result
   *
   */
  public function to_none()
  {
    $this->kind = RES_KIND_NONE;
  }
  
  /**
   * checks if this result is a error
   *
   * @return boolean
   */
  public function is_error()
  {
    return $this->kind === RES_KIND_ERROR;
  }
  
  /**
   * converts this result to an error
   * 
   * @param mixed $data
   */
  public function to_error($data = null) 
  {
    $this->kind = RES_KIND_ERROR;
    $this->data = $data;
  }
  
  /* ------------------------------------ */
  
  public function __tostring()
  {
    switch ($this->kind) {
      case RES_KIND_NONE:
        return 'none';
      case RES_KIND_SOME:
        return 'some';
      case RES_KIND_ERROR:
        return 'error';
      default:
        assert(0);
    }
  }
  
  /* ------------------------------------ */
  
  /**
   * generates a result based on the given data
   *
   * @param  mixed $data
   * @return Result
   */
  public static function from($data)
  {
    if ($data === null)
      return self::None();
    
    return self::Some($data);
  }
  
  /* ------------------------------------ */
  
  /**
   * generates a "some" result object
   *
   * @param  mixed $data
   * @return Result
   */
  public static function Some($data) 
  {
    return new static(RES_KIND_SOME, $data);
  }

  /**
   * generates a "none" result object
   *
   * @return Result
   */
  public static function None() 
  {
    return new static(RES_KIND_NONE);
  }
    
  /**
   * generates an error result
   *
   * @param mixed $data
   * @return Result
   */
  public static function Error($data = null)
  {
    return new static(RES_KIND_ERROR, $data);
  }
}
