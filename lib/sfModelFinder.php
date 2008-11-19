<?php

/*
 * This file is part of the sfPropelFinder package.
 * 
 * (c) 2007 François Zaninotto <francois.zaninotto@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
 
abstract class sfModelFinder
{
  protected
    $connection = null,
    $class = '',
    $relations = array();
  
  public function __construct($class = '', $connection = null)
  {
    $this->connection = $connection;
    $class = $class ? $class : $this->class;
    if($class)
    {
      list($class, $alias) = $this->getClassAndAlias($class);
      $this->setClass($class, $alias);
    }
  }
  
  // Finder Initializers
  
  public static function from($from, $connection = null)
  {
    throw new Exception('This method must be overridden in subclasses!'); // abstract static not allowed since PHP 5.2 
  }
  
  public static function fromArray($array, $class, $pkName)
  {
    throw new Exception('This method must be overridden in subclasses!'); // abstract static not allowed since PHP 5.2 
  }
  
  public static function fromClass($class, $connection = null)
  {
    throw new Exception('This method must be overridden in subclasses!'); // abstract static not allowed since PHP 5.2 
  }
  
  public static function fromCollection($collection, $class = '', $pkName = '')
  {
    throw new Exception('This method must be overridden in subclasses!'); // abstract static not allowed since PHP 5.2 
  }
  
  // Finder accessors
  
  abstract public function getClass();
  abstract public function setClass($class, $alias = '');
  abstract public function getConnection();
  abstract public function setConnection($connection);
  abstract public function getQueryObject();
  abstract public function setQueryObject($query);
  
  // Finder executers
  
  abstract public function count($distinct = false);
  abstract public function find($limit = null);
  abstract public function findOne();
  abstract public function findLast($column = null);
  abstract public function findFirst($column = null);
  abstract public function findBy($columnName, $value, $limit = null);
  abstract public function findOneBy($columnName, $value);
  abstract public function findPk($pk);
  abstract public function delete($forceIndividualDeletes = false);
  abstract public function paginate($page = 1, $maxPerPage = 10);
  abstract public function set($values, $forceIndividualSaves = false);

  // Hydrating
  
  abstract public function with($classes);
  abstract public function withI18n($culture = null);
  abstract public function withColumn($column, $alias = null, $type = null);
  
  // Finder Filters
  
  abstract public function distinct();
  abstract public function limit($limit);
  abstract public function offset($offset);
  abstract public function where();
  abstract public function orWhere();
  abstract public function combine($conditions, $operator = 'and', $namedCondition = null);
  abstract public function relatedTo($object);
  abstract public function orderBy($columnName, $order = null);
  abstract public function groupBy($columnName);
  abstract public function groupByClass($class);
  abstract public function guessOrder($direction = 'desc');
  abstract public function join();
  
  // Finder utilities
  
  abstract public function keepQuery($keep = true);
  abstract public function getLatestQuery();
  abstract protected function cleanup();
  abstract public function getUniqueIdentifier();
  abstract public function useCache($cacheInstance, $lifetime = 0);

  // Finder Outputters
  
  /**
   * Array outputter
   * Executes the finder and returns an array of results
   * Each result being an associative array
   * TODO: Bypass hydration for better performance
   *
   * @param $limit Integer Optional number of results to return
   *
   * @return Array the list of results as arrays
   */
  public function toArray($limit = null)
  {
    $objects = $this->find($limit);
    $res = array();
    foreach($objects as $object)
    {
      $res []= $object->toArray();
    }
    
    return $res;
  }

  /**
   * String outputter
   * Executes the finder and returns a string (incidentally, YAML compliant)
   * TODO: Bypass hydration for better performance
   *
   * @param $limit Integer Optional number of results to return
   *
   * @return String the list of results as YAML
   */
  public function toString($limit = null)
  {
    $objects = $this->find($limit);
    $res = '';
    $i = 0;
    foreach($objects as $object)
    {
      $res .= sprintf("%s_%d:\n", get_class($object), $i);
      foreach ($object->toArray() as $key => $value)
      {
        $res .= sprintf("  %-10s %s\n", $key . ':', $value);
      }
      $i++;
    }
    
    return $res;
  }
  
  /**
   * Magic string outputter
   * As of PHP 5.3, __toString() doesn't accept parameters
   * So the magic function must be distinct from the string outputter
   */
  public function __toString()
  {
    return $this->toString();
  }
  
  /**
   * HTML outputter
   * Executes the finder and returns a HTML table
   * TODO: Bypass hydration for better performance
   *
   * @param $limit Integer Optional number of results to return
   *
   * @return String the list of results as an HTML table
   */
  public function toHtml($limit = null)
  {
    $objects = $this->find($limit);
    $res = "<table class=\"DbFinder\">\n";
    $isFirstLine = true;
    foreach($objects as $object)
    {
      if($isFirstLine)
      {
        $res .= "  <tr>\n";
        foreach ($object->toArray() as $key => $value)
        {
          $res .= sprintf("    <th>%s</th>\n", $key);
        }
        $res .= "  </tr>\n";
      }
      $res .= "  <tr>\n";
      foreach ($object->toArray() as $value)
      {
        $res .= sprintf("    <td>%s</td>\n", $value);
      }
      $res .= "  </tr>\n";
      $isFirstLine = false;
    }
    $res .= "</table>\n";
    
    return $res;
  }
  
  protected static function getValueAndComparisonFromArguments($arguments = array())
  {
    $comparison = Criteria::EQUAL;
    switch (count($arguments))
    {
      case 0:
        $value = true;
        break;
      case 1:
        $value = array_shift($arguments);
        break;
      case 2:
        $comparison = array_shift($arguments);
        $comparisonUp = trim(strtoupper($comparison));
        if(in_array($comparisonUp, array('LIKE', 'NOT LIKE', 'ILIKE', 'NOT ILIKE', 'IN', 'NOT IN', 'IS NULL', 'IS NOT NULL')))
        {
          $comparison = ' '.$comparisonUp.' ';
        }
        $value = array_shift($arguments);
        break;
      default:
        throw new Exception('sfPropelFinder::whereXXX() can only be called with one or two arguments');
    }
    
    return array($value, $comparison);
  }
  
  public function __sleep()
  {
    // If the connection is a PDO instance, PHP throws an exception when serializing a finder object
    // So we must play well with it
    $attributes = get_object_vars($this);
    unset($attributes['connection']);
    return array_keys($attributes);
  }
  
  public static function camelize($arg)
  {
    if(is_array($arg))
    {
      $ret = array();
      foreach ($arg as $arg1)
      {
        $ret []= self::camelize($arg1);
      }
      return $ret;
    }
    else
    {
      return sfInflector::camelize($arg);
    }
  }

  public static function underscore($arg)
  {
    if(is_array($arg))
    {
      $ret = array();
      foreach ($arg as $arg1)
      {
        $ret []= self::underscore($arg1);
      }
      return $ret;
    }
    else
    {
      return sfInflector::underscore($arg);
    }
  }
  
  /**
   * Handle the magic
   */
  public function __call($name, $arguments)
  {
    if(strpos($name, 'where') === 0)
    {
      array_unshift($arguments, substr($name, 5));
      return call_user_func_array(array($this, 'where'), $arguments);
    }
    if(strpos($name, 'orWhere') === 0)
    {
      array_unshift($arguments, substr($name, 7));
      return call_user_func_array(array($this, 'orWhere'), $arguments);
    }
    if(strpos($name, 'orderBy') === 0)
    {
      array_unshift($arguments, substr($name, 7));
      return call_user_func_array(array($this, 'orderBy'), $arguments);
    }
    if(strpos($name, 'groupBy') === 0)
    {
      array_unshift($arguments, substr($name, 7));
      return call_user_func_array(array($this, 'groupBy'), $arguments);
    }
    if(strpos($name, 'join') === 0)
    {
      array_unshift($arguments, substr($name, 4));
      return call_user_func_array(array($this, 'join'), $arguments);
    }
    if(($pos = strpos($name, 'Join')) > 0)
    {
      $type = strtoupper(substr($name, 0, $pos));
      if(in_array($type, array('LEFT', 'RIGHT', 'INNER')))
      {
        $joinType = $type . ' JOIN';
        array_push($arguments, $joinType);
        return call_user_func_array(array($this, 'join'), $arguments);
      }
    }
    if(strpos($name, 'findBy') === 0)
    {
      array_unshift($arguments, substr($name, 6));
      return call_user_func_array(array($this, 'findBy'), $arguments);
    }
    if(strpos($name, 'findOneBy') === 0)
    {
      array_unshift($arguments, substr($name, 9));
      return call_user_func_array(array($this, 'findOneBy'), $arguments);
    }
    if(method_exists($this->getQueryObject(), $name))
    {
      call_user_func_array(array($this->getQueryObject(), $name), $arguments);
      return $this;
    }
    throw new Exception(sprintf('Undefined method *s::%s()', __CLASS__, $name));
  }
}