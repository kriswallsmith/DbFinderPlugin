<?php

/**
* sfPropelFinderRelation
*/
class sfPropelFinderRelation
{
  protected
    $fromClass,
    $toClass,
    $fromColumn,
    $toColumn,
    $type,
    $toAddMethod;
  
  const
    ONE_TO_MANY = false,
    MANY_TO_ONE = true;
    
  public function __construct($fromClass, $fromColumn, $toClass, $toColumn)
  {
    $this->fromClass  = $fromClass;
    $this->fromColumn = $fromColumn;
    $this->toClass    = $toClass;
    $this->toColumn   = $toColumn;
    $this->type = self::MANY_TO_ONE;
  }
  
  public function __toString()
  {
    return sprintf('from: %s (%s), to: %s (%s)', $this->fromClass, $this->fromColumn, $this->toClass, $this->toColumn);
  }
  
  public function getFromClass()
  {
    return $this->fromClass;
  }
  
  public function getFromColumn()
  {
    return $this->fromColumn;
  }
  
  public function setFromColumn($value)
  {
    return $this->fromColumn = $value;
  }

  public function getToClass()
  {
    return $this->toClass;
  }
  
  public function getToColumn()
  {
    return $this->toColumn;
  }
  
  public function setToColumn($value)
  {
    return $this->toColumn = $value;
  }
  
  public function getType()
  {
    return $this->type;
  }
  
  public function addObject($fromObject, $toObject, $isNew = false)
  {
    if(is_null($this->toAddMethod))
    {
      $methodName1 = 'add' . $this->toClass;
      $methodName2 = $method1 . 'relatedBy' . self::camelize($this->toColumn);
      if(method_exists($fromClass, $methodName2))
      {
        $this->toAddMethod = $methodName2;
      }
      elseif(method_exists($fromClass, $methodName1))
      {
        $this->toAddMethod = $methodName1;
      }
      else
      {
        throw new Exception('Unable to find foreign key setter method');
      }
    }
    if($isNew)
    {
      call_user_func(array($toObject, 'init'.$this->fromClass.'s'));
    }
    call_user_func(array($toObject, $methodName), $fromObject);
  }
  
  protected static function camelize($phpName)
  {
    return sfModelFinder::camelize(strtolower($phpName));
  }
  
  public function reverse()
  {
    $fromClass  = $this->fromClass;
    $fromColumn = $this->fromColumn;
    $this->fromClass  = $this->toClass;
    $this->fromColumn = $this->toColumn;
    $this->toClass    = $fromClass;
    $this->toColumn   = $fromColumn;
    $this->type = !$this->type;
    
    return $this;
  }

}
