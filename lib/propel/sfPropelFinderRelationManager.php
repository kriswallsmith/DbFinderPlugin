<?php

/**
* sfPropelFinderRelationManager
*/
class sfPropelFinderRelationManager implements ArrayAccess
{
  protected 
    $startClass,
    $startPeerClass,
    $databaseMap,
    $relations = array();
  
  public function __construct($startClass)
  {
    $this->startClass = $startClass;
    $this->startPeerClass = sfPropelFinderUtils::getPeerClassFromClass($startClass);
    $mapBuilder = call_user_func(array($this->startPeerClass, 'getMapBuilder'));
    $mapBuilder->doBuild();
    $this->databaseMap = $mapBuilder->getDatabaseMap();
  }
  
  public function getRelations()
  {
    return $this->relations;
  }
  
  /* ArrayAccess interface methods */
  
  public function offsetExists($offset)
  {
    return array_key_exists($offset, $this->relations);
  }
  
  public function offsetGet($offset)
  {
    return array_key_exists($offset, $this->relations) ? $this->relations[$offset] : null;
  }
  
  public function offsetSet($offset, $value)
  {
    $this->relations[$offset] = $value;
  }
  
  public function offsetUnset ($offset)
  {
    unset($this->relations[$offset]);
  }
  
  /* Convenient and meaningful shortcut */
  
  public function hasRelation($alias)
  {
    return $this->offsetExists($alias);
  }
  
  /* Relation builders */
  
  public function addRelationFromClass($class, $alias = null)
  {
    if (is_null($alias))
    {
      $alias = $class;
    }
    if($this->hasRelation($alias))
    {
      // There is already a relation on this table, so skip the join
      return false;
    }
    else
    {
      $relation = $this->guessRelation($class);
      if($alias != $class)
      {
        if($relation->getToColumn() == $class)
        {
          $peerClass = sfPropelFinderUtils::getPeerClassFromClass($relation->getFromClass());
          $relation->setFromColumn(call_user_func(array($peerClass, 'alias'), $alias, $relation->getFromColumn()));
        }
        else
        {
          $peerClass = sfPropelFinderUtils::getPeerClassFromClass($relation->getToClass());
          $relation->setToColumn(call_user_func(array($peerClass, 'alias'), $alias, $relation->getToColumn()));
        }
      }
      $this->relations[$alias] = $relation;
      return $relation;
    }
  }
  
  public function addRelationFromColumns($fromClass, $fromColumn, $toClass, $toColumn, $alias = null)
  {
    if(is_null($alias))
    {
      $alias = $toClass;
    }
    if($this->hasRelation($alias))
    {
      // There is already a relation on this table, so skip the join
      return false;
    }
    else
    {
      $relation = new sfPropelFinderRelation($fromClass, $fromColumn, $toClass, $toColumn);
      $this->relations[$alias] = $relation;
      return $relation;
    }
  }
  
  /**
   * Guess the relation to another class, possibly using existing relations
   *
   * @param string $phpName Propel Class name (e.g. 'Article')
   * 
   * @return sfPropelFinderRelation A relationship
   * @throws Exception if no relationship can be found
   */
  public function guessRelation($phpName)
  {
    // try to find many to one or one to one relationship on the startClass
    if($relation = $this->findRelation($phpName, $this->startClass))
    {
      return $relation->reverse();
    }
    // try to find one to many relationship on the startClass
    if($relation = $this->findRelation($this->startClass, $phpName))
    {
      return $relation;
    }
    // try to find a relationship on the related objects
    foreach($this->relations as $rel)
    {
      // try to find many to one or one to one relationship
      if($relation = $this->findRelation($phpName, $rel->getToClass()))
      {
        return $relation->reverse();
      }
      // try to find one to many relationship
      if($relation = $this->findRelation($rel->getToClass(), $phpName))
      {
        return $relation;
      }
    }
    throw new Exception(sprintf('sfPropelFinder: %s has no %s related table', $this->startClass, $phpName));
  }
  
  /**
   * Finds a relation between two classes by introspection
   * A relation is found only if the foreign key lies in the columns of the first class
   *
   * @param string $from Propel Class name (e.g. 'Article')
   * @param string $to   Propel Class name (e.g. 'Comment')
   * 
   * @return sfPropelFinderRelation A relationship, or false if no relationship is found
   */
  public function findRelation($from, $to)
  {
    $fromPeer = sfPropelFinderUtils::getPeerClassFromClass($from);
    $toPeer = sfPropelFinderUtils::getPeerClassFromClass($to);
    
    foreach (sfPropelFinderUtils::getColumnsForPeerClass($fromPeer) as $c)
    {
      if ($c->isForeignKey())
      {
        if(!$this->databaseMap->containsTable($c->getRelatedTableName()))
        {
          $mapBuilder = call_user_func(array($toPeer, 'getMapBuilder'));
          $mapBuilder->doBuild();
        }
        try
        {
          $tableMap = $this->databaseMap->getTable($c->getRelatedTableName());
        }
        catch (PropelException $e)
        {
          // so $c->getRelatedTable() is not in the database map
          // even though the $phpName table map has been initialized
          // we are obviously looking for the wrong table here
          continue;
        }
        if($tableMap->getPhpName() == $to)
        {
          return new sfPropelFinderRelation($from, constant($fromPeer .'::'.$c->getColumnName()), $to, $c->getRelatedName());
        }
      }
    }
    
    return false;
  }
}
