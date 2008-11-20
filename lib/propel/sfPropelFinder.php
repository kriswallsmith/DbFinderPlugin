<?php

/*
 * This file is part of the sfPropelFinder package.
 * 
 * (c) 2007 François Zaninotto <francois.zaninotto@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
 
class sfPropelFinder extends sfModelFinder
{
  protected 
    $class           = null,
    $alias           = null,
    $peerClass       = null,
    $object          = null,
    $databaseMap     = null,
    $connection      = null,
    $criteria        = null,
    $reinit          = true,
    $latestQuery     = '',
    $criterions      = array(),
    $namedCriterions = array(),
    $withClasses     = array(),
    $withColumns     = array(),
    $culture         = null,
    $cache           = null;
  
  public function getClass()
  {
    return $this->class;
  }

  public function setClass($class, $alias = '')
  {
    $this->class = $class;
    $this->alias = $alias;
    $this->object = new $class();
    $this->peerClass = get_class($this->object->getPeer());
    $this->addRelation($this->peerClass, $this->alias);
    $this->initialize();
    
    return $this;
  }

  public function getConnection()
  {
    if(is_null($this->connection))
    {
      $name = $this->peerClass ? constant($this->peerClass.'::DATABASE_NAME') : '';
      $this->connection = Propel::getConnection($name);
    }
    
    return $this->connection;
  }

  public function setConnection($connection)
  {
    $this->connection = $connection;
    
    return $this;
  }
  
  /**
   * Returns the internal query object
   *
   * @return Criteria
   */
  public function getQueryObject()
  {
    return $this->criteria;
  }
  
  /**
   * Replaces the internal query object
   *
   * @return sfPropelFinder The current finder
   */
  public function setQueryObject($query)
  {
    $this->criteria = $query;
    
    return $this;
  }
  
  // Finder Initializers
  
  /**
   * Mixed initializer
   * Accepts either a string (Model object class) or an array of model objects
   *
   * @param mixed $from The data to initialize the finder with
   * @param mixed $connection Optional connection object
   *
   * @return sfPropelFinder a finder object
   * @throws Exception If the data is neither a classname nor an array
   */
  public static function from($from, $connection = null)
  {
    if (is_string($from))
    {
      return self::fromClass($from, $connection);
    }
    if (is_array($from) || $from instanceof Doctrine_Collection)
    {
      return self::fromCollection($from);
    }
    throw new Exception('sfPropelFinder::from() only accepts a model object classname or an array of model objects');
  }
  
  /**
   * Array initializer
   *
   * @param array $array Array of Primary keys
   * @param string $class Model classname on which the search will be done
   *
   * @return sfPropelFinder a finder object
   */
  public static function fromArray($array, $class, $pkName)
  {
    $finder = self::fromClass($class);
    $finder->where($pkName, 'in', $array);
    
    return $finder;
  }
  
  /**
   * Class initializer
   *
   * @param string $from Model classname on which the search will be done
   * @param mixed $connection Optional connection object
   *
   * @return sfPropelFinder a finder object
   */
  public static function fromClass($class, $connection = null)
  {
    if(strpos($class, ' ') !== false)
    {
      list($realClass, $alias) = explode(' ', $class);
    }
    else
    {
      $realClass = $class;
    }
    if(class_exists($realClass))
    {
      $tmp = new $realClass;
      if($tmp instanceof BaseObject)
      {
        $me = __CLASS__;
        $finder = new $me($class, $connection);
      }
      else
      {
        throw new Exception('sfPropelFinder::fromClass() only accepts a Propel model classname');
      }
    }
    else
    {
       throw new Exception('sfPropelFinder::fromClass() only accepts a Propel model classname');
    }
    
    return $finder;
  }
  
  /**
   * Collection initializer
   *
   * @param array $from Array of model objects of the same class
   * @param string $class Optional classname of the desired objects
   * @param string $class Optional column name of the primary key
   *
   * @return sfPropelFinder a finder object
   * @throws Exception If the array is empty, contains not model objects or composite objects
   */
  public static function fromCollection($collection, $class = '', $pkName = '')
  {
    $pks = array();
    foreach($collection as $object)
    {
      if($class != get_class($object))
      {
        if($class)
        {
          throw new Exception('A sfPropelFinder can only be initialized from an array of objects of a single class');
        }
        if($object instanceof BaseObject)
        {
          $class = get_class($object);
        }
        else
        {
          throw new Exception('A sfPropelFinder can only be initialized from an array of Propel objects');
        }
      }
      $pks []= $object->getPrimaryKey();
    }
    if(!$class)
    {
      throw new Exception('A sfPropelFinder cannot be initialized with an empty array');
    }
    $tempObject = new $class();
    foreach ($tempObject->getPeer()->getTableMap()->getColumns() as $column)
    {
      if($column->isPrimaryKey())
      {
        if($pkName)
        {
          throw new Exception('A sfPropelFinder cannot be initialized from an array of objects with several foreign keys');
        }
        else
        {
          $pkName = $column->getPhpName();
        }
      }
    }
    
    return self::fromArray($pks, $class, $pkName);
  }
  

  public function initialize()
  {
    $this->reinitCriteria();
    $mapBuilder = call_user_func(array($this->peerClass, 'getMapBuilder'));
    $mapBuilder->doBuild();
    $this->databaseMap = $mapBuilder->getDatabaseMap();
  }
  
  protected function addRelation($peerClass, $alias = '')
  {
    if(!$alias) list($class, $alias) = $this->getClassAndAlias($peerClass);
    $this->relations[$alias] = $peerClass;
  }
  
  public function getCriteria()
  {
    return $this->buildCriteria();
  }
  
  public function setCriteria($criteria)
  {
    $this->criteria = $criteria;
    $this->criterions = array();
    
    return $this;
  }

  public function reinitCriteria()
  { 
    return $this->setCriteria(new Criteria());
  }
  
  public function getLatestQuery()
  {
    if(method_exists($this->getConnection(), 'getLastExecutedQuery'))
    {
      return $this->latestQuery;
    }
    else
    {
      throw new RuntimeException('sfPropelFinder::getLatestQuery() only works when debug mode is enabled');
    }
  }
  
  public function updateLatestQuery()
  {
    if(method_exists($this->getConnection(), 'getLastExecutedQuery'))
    {
      $this->latestQuery = $this->getConnection()->getLastExecutedQuery();
    }
  }
  
  public function addWithClass($class)
  {
    $this->withClasses []= $class;
    
    return $this;
  }
  
  public function getWithClasses()
  {
    return $this->withClasses;
  }
  
  public function reinitWithClasses()
  {
    $this->withClasses = array();
    
    return $this;
  }

  public function getWithColumns()
  {
    return $this->withColumns;
  }
  
  public function reinitWithColumns()
  {
    $this->withColumns = array();
    
    return $this;
  }
  
  // Finder Executers
  
  /**
   * Returns the number of records matching the finder
   *
   * @param Boolean $distinct Whether the count query has to add a DISTINCT keyword
   *
   * @return integer Number of records matching the finder
   */
  public function count($distinct = false)
  {
    if($cache = $this->cache)
    {
      $key = $this->getUniqueIdentifier().'_count';
      $ret = $cache->getIfSet($key);
      if($ret !== false)
      {
        return $ret;
      }
    }
    $ret = call_user_func(array($this->peerClass, 'doCount'), $this->getCriteria(), $distinct, $this->getConnection());
    if($cache)
    {
      $cache->set($key, $ret);
    }
    $this->cleanup();
    
    return $ret;
  }
  
  /**
   * Executes the finder and returns the matching Propel objects
   *
   * @param integer $limit Optional maximum number of results to retrieve
   *
   * @return array A list of BaseObject Propel objects
   */
  public function find($limit = null)
  {
    if($limit)
    {
      $this->criteria->setLimit($limit);
    }
    $ret = $this->doFind();
    $this->cleanup();
    
    return $ret;
  }
  
  /**
   * Limits the search to a single result, and executes the finder
   * Returns the first Propel object matching the finder
   *
   * @return mixed a BaseObject object or null
   */
  public function findOne()
  {
    $this->criteria->setLimit(1);
    $ret = $this->doFind();
    $this->cleanup();
    
    return isset($ret[0]) ? $ret[0] : null;
  }
  
  /**
   * Returns the last record matching the finder
   * Optionally, you can specify the column to sort on
   * If no column is passed, the finder guesses the column to use
   * @see guessOrder()
   *
   * @param string $columnName Optional: The column to order by
   *
   * @return mixed a BaseObject object or null
   */
  public function findLast($columnName = null)
  {
    if($columnName)
    {
      $this->orderBy($columnName, 'desc');
    }
    else
    {
      $this->guessOrder('desc');
    }
    
    return $this->findOne();
  }
  
  /**
   * Returns the first record matching the finder
   * Optionally, you can specify the column to sort on
   * If no column is passed, the finder guesses the column to use
   * @see guessOrder()
   *
   * @param string $columnName Optional: The column to order by
   *
   * @return mixed a BaseObject object or null
   */
  public function findFirst($columnName = null)
  {
    if($columnName)
    {
      $this->orderBy($columnName, 'asc');
    }
    else
    {
      $this->guessOrder('asc');
    }
    
    return $this->findOne();
  }
  
  /**
   * Adds a condition on a column and returns the records matching the finder
   *
   * @param string $columnName Name of the columns
   * @param mixed $value
   * @param integer $limit Optional maximum number of records to return
   *
   * @return array A list of BaseObject Propel objects
   */
  public function findBy($columnName, $value, $limit = null)
  {
    $column = $this->getColName($columnName);
    $this->addCondition('and', $column, $value, Criteria::EQUAL);
    
    return $this->find($limit);
  }
  
  /**
   * Adds a condition on a column and returns the first record matching the finder
   * Useful to retrieve objects by a column which is not the primary key
   *
   * @param string $columnName Name of the columns
   * @param mixed $value
   *
   * @return mixed a BaseObject object or null
   */
  public function findOneBy($columnName, $value)
  {
    $column = $this->getColName($columnName);
    $this->addCondition('and', $column, $value, Criteria::EQUAL);
    
    return $this->findOne();
  }
  
  /**
   * Finds record(s) based on one or several primary keys
   * Takes into account hydrating methods previously called on the finder
   *
   * @param mixed $pk A primary kay, a composite primary key, or an array of primary keys
   *
   * @return mixed One or several BaseObject records (based on the input)
   */
  public function findPk($pk)
  {
    $tableMap = call_user_func(array($this->peerClass, 'getTableMap'));
    $pkColumns = array();
    foreach ($tableMap->getColumns() as $column)
    {
      if($column->isPrimaryKey())
      {
        $pkColumns []= $column->getFullyQualifiedName();
      }
    }
    if(($count = count($pkColumns)) > 1)
    {
      // composite primary key
      if(!is_array($pk))
      {
        if(count($pk = func_get_args()) != count($pkColumns))
        {
          throw new Exception(sprintf('Class %s has a composite primary key and expects %s parameters to retrieve a record by pk', $this->class, join(', ', $pkColumns)));
        }
      } 
      else if (is_array($count[0]))
      {
        // array of arrays
        // sorry the finder can't do that on objects with composte primary keys
        throw new Exception('Impossible to find a list of Pks on an objects with composite primary keys');
      }
      for ($i=0; $i < $count; $i++)
      { 
        $this->criteria->add($pkColumns[$i], $pk[$i]);
      }
      return $this->findOne();
    }
    else
    {
      // simple primary kay
      if(is_array($pk))
      {
        $this->criteria->add($pkColumns[0], $pk, Criteria::IN);
        return $this->find();
      }
      else
      {
        $this->criteria->add($pkColumns[0], $pk);
        return $this->findOne();
      }
    } 
  }
  
  /**
   * Deletes the records found by the finder
   * Beware that behaviors based on hooks in the object's delete() method (such as sfPropelParanoidBehavior)
   * Will only be triggered if you force individual deletes, i.e. if you pass true as first argument
   *
   * @param Boolean $forceIndividualDeletes If false (default), the resulting call is a BasePeer::doDelete(), ortherwise it is a series of delete() calls on all the found objects
   *
   * @return Integer Number of deleted rows
   */
  public function delete($forceIndividualDeletes = false)
  {
    $deleteCriteria = $this->getCriteria();
    if($forceIndividualDeletes)
    {
      $objects = $this->find();
      foreach($objects as $object)
      {
        $object->delete($this->getConnection());
      }
      $ret = count($objects);
    }
    else
    {
      if($deleteCriteria->equals(new Criteria()))
      {
        // doDelete will delete nothing when passed an empty criteria
        // while it should, in fact, delete all
        $deleteCriteria = $this->addTrueCondition($deleteCriteria);
      }
      $ret = call_user_func(array($this->peerClass, 'doDelete'), $deleteCriteria, $this->getConnection());
    }
    $this->cleanup();
    
    return $ret;
  }
  
  /**
   * Prepares a pager based on the finder
   * The pager is initialized (it knows how many pages it contains)
   * But it won't be populated until you call getResults() on it
   *
   * @param integer $page The current page (1 by default)
   * @param integer $maxPerPage The maximum number of results per page (10 by default)
   *
   * @return sfPropelFinderPager The initialized pager object
   */
  public function paginate($page = 1, $maxPerPage = 10)
  {
    // Children of sfPropelPager don't have a $class property, so we need to guess it
    $pager = new sfPropelFinderPager($this->class, $maxPerPage);
    $pager->setFinder($this);
    $pager->setPage($page);
    $pager->init();
    
    return $pager;
  }
  
  /**
   * Updates the records found by the finder
   * Beware that behaviors based on hooks in the object's save() method
   * Will only be triggered if you force individual saves, i.e. if you pass true as second argument
   *
   * @param Array $values Associative array of keys and values to replace
   * @param Boolean $forceIndividualSaves If false (default), the resulting call is a BasePeer::doUpdate(), ortherwise it is a series of save() calls on all the found objects
   *
   * @return Integer Number of updated rows
   */
  public function set($values, $forceIndividualSaves = false)
  {
    if (!is_array($values))
    {
      throw new Exception('sfPropelFinder::set() expects an array as first argument');
    }
    if($forceIndividualSaves)
    {
      $objects = $this->find();
      foreach ($objects as $object)
      {
        foreach ($values as $key => $value)
        {
          $object->setByName($key, $value);
        }
        $object->save();
      }
      $ret = count($objects);
    }
    else
    {
      $find = $this->getCriteria();
      if (count($find->getJoins()))
      {
        throw new Exception('sfPropelFinder::set() does not support multitable updates, please do not use join');
      }
      if($find->equals(new Criteria()))
      {
        // doUpdate will delete nothing when passed an empty criteria
        // while it should, in fact, update all
        $find = $this->addTrueCondition($find);
      }
      
      $set = new Criteria();
      foreach ($values as $columnName => $value)
      {
        $set->add($this->getColName($columnName), $value);
      }
      
      $ret = BasePeer::doUpdate($find, $set, $this->getConnection());
    }
    $this->cleanup();
    
    return $ret;
  }
  
  /**
   * Cleans up the query object (if necessary) and logs the latest query
   *
   * @return sfPropelFinder the current finder object
   */
  protected function cleanup()
  {
    if($this->reinit)
    {
      $this->reinitCriteria();
      $this->reinitWithClasses();
      $this->reinitWithColumns();
      $this->relations = array();
      $this->addRelation($this->peerClass, $this->alias);
    }
    $this->updateLatestQuery();
    
    return $this;
  }
  
  /**
   * Finalizes the query, executes it and hydrates results
   * 
   * @return array List of Propel objects
   */
  public function doFind()
  {
    if($cache = $this->cache)
    {
      $key = $this->getUniqueIdentifier();
      $ret = $cache->getIfSet($key);
      if($ret !== false)
      {
        return $ret;
      }
    }
    
    if($this->getWithClasses() || $this->getWithColumns())
    {
      $c = $this->prepareCompositeCriteria();
      if(method_exists($this->peerClass, 'doSelectRS'))
      {
        $resultSet = call_user_func(array($this->peerClass, 'doSelectRS'), $c, $this->getConnection());
        $propelVersion = '1.2';
        $nextFunction = 'next';
        $nextParam = null;
      }
      else
      {
        $resultSet = call_user_func(array($this->peerClass, 'doSelectStmt'), $c, $this->getConnection());
        $propelVersion = '1.3';
        $nextFunction = 'fetch';
        $nextParam = PDO::FETCH_NUM;
      }
      
      // Hydrate the objects based on the resultset
      $omClass = call_user_func(array($this->peerClass, 'getOMClass'));
      $cls = substr('.'.$omClass, strrpos('.'.$omClass, '.') + 1);
      $objects = array();
      $withObjs = array();
      while ($row = $resultSet->$nextFunction($nextParam))
      {
        // First come the columns of the main class
        $obj = new $cls();
        if($propelVersion == '1.2')
        {
          $startCol = $obj->hydrate($resultSet, 1);
        }
        else
        {
          $startCol = $obj->hydrate($row, 0);
        }
        if($this->culture)
        {
          $obj->setCulture($this->culture);
        }
        // Then the related classes added by way of 'with'
        $objectsInJoin = array($obj);
        foreach ($this->getWithClasses() as $className)
        {
          $withObj = new $className();
          if($propelVersion == '1.2')
          {
            $startCol = $withObj->hydrate($resultSet, $startCol);
          }
          else
          {
            $startCol = $withObj->hydrate($row, $startCol);
          }
          
          // As we can be in a left join, there is a possibility that the hydrated related object is null
          // In this case, we must not relate it to the main object
          $isEmpty = true;
          foreach ($withObj->toArray() as $value)
          {
            if($value !== null)
            {
              $isEmpty = false;
            }
          }
          if($isEmpty)
          {
            continue;
          }
          
          // initialize our object directory
          if (!isset($withObjs[$className]))
          {
            $withObjs[$className] = array();
          }
          
          // check if object is not already referenced in allObjects directory
          $isNewObject = true;
          foreach ($withObjs[$className] as $otherObject)
          {
            if ($otherObject->getPrimaryKey() === $withObj->getPrimaryKey())
            {
              $isNewObject = false;
              $withObj = $otherObject;
              break;
            }
          }
          if(strpos(get_class($withObj), 'I18n') !== false)
          {
            sfPropelFinderUtils::relateI18nObjects($withObj, $objectsInJoin, $this->culture);
          }
          else
          {
            sfPropelFinderUtils::relateObjects($withObj, $objectsInJoin, $isNewObject);
          }
          $objectsInJoin []= $withObj;
          if ($isNewObject)
          {
            $withObjs[$className][] = $withObj;
          }
        }
        // Then the columns added one by one by way of 'withColumn'
        foreach($this->getWithColumns() as $alias => $column)
        {
          // Additional columns are stored in the object, in a special 'namespace'
          // see getColumn() for how to retrieve the value afterwards
          // Using the third parameter of withColumn() as a type. defaults to $rs->get() (= $rs->getString())
          $typedGetter = 'get'.ucfirst($column['type']);
          if($propelVersion == '1.2')
          {
            $this->setColumn($obj, $alias, $resultSet->$typedGetter($startCol));
          }
          else
          {
            $this->setColumn($obj, $alias, $row[$startCol]);
          }
          $startCol++;
        }
        
        $objects []= $obj;
      }
      
      // activate custom column getter if asColumns were added
      if($this->getWithColumns() && !sfMixer::getCallable('Base'.$cls.':getColumn'))
      {
        sfMixer::register('Base'.$cls, array($this, 'getColumn'));
      }
    }
    else
    {
      // No 'with', so we use the native Propel doSelect()
      $objects = call_user_func(array($this->peerClass, 'doSelect'), $this->buildCriteria(), $this->getConnection());
    }
    
    if($cache)
    {
      $cache->set($key, $objects);
    }
    
    return $objects;
  }
  
  /**
   * Adds missing Joins from with() and withColumn()
   */
  protected function addMissingJoins()
  {
    foreach ($this->getWithClasses() as $className)
    {
      if(!in_array(sfPropelFinderUtils::getPeerClassFromClass($className), $this->relations))
      {
        $this->join($className);
      }
    }
    foreach($this->getWithColumns() as $alias => $column)
    {
      $peerClass = $column['peerClass'];
      if($peerClass && !in_array($peerClass, $this->relations))
      {
        $this->join(sfPropelFinderUtils::getClassFromPeerClass($peerClass));
      }
    }
    
    return $this;
  }
  
  /**
   * Prepare the select columns and add the missing joins
   */
  protected function prepareCompositeCriteria()
  {
    $criteria = $this->buildCriteria();
    $c = clone $criteria;
    $c->clearSelectColumns();
    // First come the columns of the main class
    call_user_func(array($this->peerClass, 'addSelectColumns'), $c);
    // Then the related classes added by way of 'with'
    foreach ($this->getWithClasses() as $className)
    {
      call_user_func(array(sfPropelFinderUtils::getPeerClassFromClass($className), 'addSelectColumns'), $c);
    }
    // Then the columns added one by one by way of 'withColumn'
    foreach($this->getWithColumns() as $alias => $column)
    {
      if(strpos($alias, '.') !== false)
      {
        // The alias contains a forbidden character, so we must quote it
        $alias = '"'.$alias.'"';
      }
      $c->addAsColumn($alias, $column['column']);
    }
    
    return $c;
  }
  
  // Hydrating
  
  /**
   * Ask the finder to hydrate related records
   * With a single class, it is equivalent to Propel's doSelectJoinXXX() methods
   * But it accepts several arguments, so you can hydrate a lot of related objects
   * Examples:
   *   // Article has an author, article has a category, and author has a group
   *   $articleFinder->with('Author')->find();
   *   $articleFinder->with('Author', 'Category')->find();
   *   $articleFinder->with('Author', 'Group')->find();
   *   $articleFinder->join('Author')->with('Group')->find();
   *   // By default, the finder uses a simple join (where) but you can force another join
   *   $articleFinder->leftJoin('Author')->with('Author')->find();
   *
   * @return     sfPropelFinder the current finder object
   */
  public function with($classes)
  {
    if(!is_array($classes))
    {
      $classes = func_get_args();
    }
    foreach($classes as $class)
    {
      if(strtolower($class) == 'i18n')
      {
        $this->withI18n();
      }
      else
      {
        $this->addWithClass($class);
      }
    }
    
    return $this;
  }
  
  /**
   * Ask the finder to hydrate related i18n records
   *
   * @param string $culture Optional culture value. If no culture is given, the current user's culture is used
   *
   * @return     sfPropelFinder the current finder object
   */
  public function withI18n($culture = null)
  {
    $i18nClass = method_exists($this->peerClass, 'getI18nModel') ? call_user_func(array($this->peerClass, 'getI18nModel')) : $this->class.'I18n';
    $this->addWithClass($i18nClass);
    $this->culture = is_null($culture) ? sfContext::getInstance()->getUser()->getCulture() : $culture;
    $this->criteria->add(constant(sfPropelFinderUtils::getPeerClassFromClass($i18nClass).'::CULTURE'), $this->culture);
    
    return $this;
  }

  /**
   * Ask the finder to hydrate related columns
   * Columns hydrated by way of withColumn() can be retrieved on the object via getColumn()
   * If the join was not explicitly declared earlier in the finder, it guesses it
   * Examples:
   *   $article = $articleFinder->join('Author')->withColumn('Author.Name')->findOne();
   *   // The join() can be omitted, in which case the finder will try to guess the join based on the schema
   *   $article = $articleFinder->withColumn('Author.Name')->findOne();
   *   // Columns added by way of withColumn() can be retrieved by getColumn()
   *   $authorName = $article->getColumn('Author.Name');
   *
   *   // Alias support
   *   $article = $articleFinder->withColumn('Author.Name', 'authorName')->findOne();
   *   $authorName = $article->getColumn('authorName');
   *
   *   // type support
   *   $article = $articleFinder->withColumn('Author.Name', 'authorName', 'string')->findOne();
   *   $authorName = $article->getColumn('authorName');
   *
   *   // Support for calculated columns
   *   $articles = articleFinder->
   *     join('Comment')->
   *     withColumn('COUNT(comment.ID)', 'NbComments')->
   *     groupBy('Article.Id')->
   *     find();
   *
   * @param string $column The column to add. Can be a calculated column
   * @param string $alias Optional alias for column retrieval
   * @param string $type Optional type converter to be sure the retrieved column has the correct type
   *
   * @return     sfPropelFinder the current finder object
   */
  public function withColumn($column, $alias = null, $type = null)
  {
    $isCalculationColumn = strpos($column, '(') !== false;
    if(!$alias)
    {
      if($isCalculationColumn)
      {
        throw new Exception('Calculated colums added with sfPropelFinder::withColumn() need an alias as second parameter');
      }
      else
      {
        $alias = $column;
      }
    }
    if($isCalculationColumn)
    {
      preg_match_all('/\w+\.\w+/', $column, $matches);
      foreach ($matches[0] as $key)
      {
        try
        {
          $colname = $this->getColName($key);
          $column = str_replace($key, $colname, $column);
        }
        catch(Exception $e)
        {
          // Do nothing: the match is probably not a ClassName.ColumnName identifier (e.g. decimal number)
        }
      }
      $peerClass = null;
    }
    else
    {
      list($peerClass, $columnName) = $this->getColName($column, null, true);
    }
    $this->withColumns [$alias]= array(
      'column'    => $isCalculationColumn ? $column : $columnName,
      'type'      => $type,
      'peerClass' => $peerClass
    );
    
    return $this;
  }
  
  // Finder Filters
  
  /**
   * Finder Fluid Interface for Criteria::setDistinct()
   *
   * @return     sfPropelFinder the current finder object
   */
  public function distinct()
  {
    $this->criteria->setDistinct();
    
    return $this;
  }
  
  /**
   * Finder Fluid Interface for Criteria::setLimit()
   *
   * @return     sfPropelFinder the current finder object
   */
  public function limit($limit)
  {
    $this->criteria->setLimit($limit);
    
    return $this;
  }
  
  /**
   * Finder Fluid Interface for Criteria::setOffset()
   *
   * @return     sfPropelFinder the current finder object
   */
  public function offset($offset)
  {
    $this->criteria->setOffset($offset);
    
    return $this;
  }
  
  /**
   * Finder Fluid Interface for Criteria::add()
   * Infers $column, $value, $comparison from $columnName and some optional arguments
   * Examples:
   *   $articleFinder->where('IsPublished')
   *    => $c->add(ArticlePeer::IS_PUBLISHED, true)
   *   $articleFinder->where('CommentId', 3)
   *    => $c->add(ArticlePeer::COMMENT_ID, 3)
   *   $articleFinder->where('Title', 'like', '%foo')
   *    => $c->add(ArticlePeer::TITLE, '%foo', Criteria::LIKE)
   *   $articleFinder->where('Title', 'like', '%foo', 'FooTitle')
   *    => $FooTitle = $c->getNewCriterion(ArticlePeer::TITLE, '%foo', Criteria::LIKE)
   *
   * @param      string  $columnName PHPName of the column bearing the condition
   * @param      string  $valueOrOperator  Value if 2 arguments, operator otherwise
   * @param      string  $value  Value if 3 arguments (optional)
   * @param      string  $namedCondition  If condition is to be stored for later combination (see combine())
   *
   * @return     sfPropelFinder the current finder object
   */
  public function where()
  {
    $arguments = func_get_args();
    $columnName = array_shift($arguments);
    $column = $this->getColName($columnName);
    if(isset($arguments[2]))
    {
      $namedCondition = $arguments[2];
      unset($arguments[2]);
    }
    else
    {
      $namedCondition = null;
    }
    list($value, $comparison) = self::getValueAndComparisonFromArguments($arguments);
    $this->addCondition('And', $column, $value, $comparison, $namedCondition);
    
    return $this;
  }

  /**
   * Finder Fluid Interface for Criteria::addOr()
   * Infers $column, $value, $comparison from $columnName and some optional arguments
   * Examples:
   *   $articleFinder->orWhere('CommentId', 3)
   *    => $c->addOr(ArticlePeer::COMMENT_ID, 3)
   *   $articleFinder->orWhere('Title', 'like', '%foo')
   *    => $c->addOr(ArticlePeer::TITLE, '%foo', Criteria::LIKE)
   *
   * @param      string  $columnName PHPName of the column bearing the condition
   * @param      string  $valueOrOperator  Value if 2 arguments, operator otherwise
   * @param      string  $value  Value if 3 arguments (optional)
   *
   * @return     sfPropelFinder the current finder object
   */
  public function orWhere()
  {
    $arguments = func_get_args();
    $columnName = array_shift($arguments);
    $column = $this->getColName($columnName);
    list($value, $comparison) = self::getValueAndComparisonFromArguments($arguments);
    $this->addCondition('Or', $column, $value, $comparison);
    
    return $this;
  }

  /**
   * Conditions have to be stored before being really used
   *
   * @see sfPropelFinder::buildCriteria()
   */
  protected function addCondition($cond, $column, $value, $comparison, $namedCondition = null)
  {
    $criterion = $this->criteria->getNewCriterion($column, $value, $comparison);
    if($namedCondition)
    {
      $this->namedCriterions[$namedCondition] = $criterion;
    }
    else
    {
      $criterion->func = "add".$cond;
      $this->criterions []= $criterion;
    }
  }

  /**
   * Combine named conditions into the main criteria or into a new named condition
   * Named conditions are to be defined in where()
   *
   * @param Array $conditions list of named conditions already set by way of where()
   * @param string $operator Combine operator ('and' or 'or')
   * @param string $namedCondition  If combined condition is to be stored for later combination (see combine())
   * 
   * @see where()
   * @return     sfPropelFinder the current finder object
   */
  public function combine($conditions, $operator = 'and', $namedCondition = null)
  {
    $addMethod = 'add'.ucfirst(strtolower(trim($operator)));
    if(!is_array($conditions))
    {
      $conditions = array($conditions);
    }
    foreach($conditions as $condition)
    {
      if(!isset($criterion))
      {
        $criterion = $this->namedCriterions[$condition];
      }
      else
      {
        $criterion->$addMethod($this->namedCriterions[$condition]);
      }
    }
    if($namedCondition)
    {
      $this->namedCriterions[$namedCondition] = $criterion;
    }
    else
    {
      $criterion->func = "addAnd";
      $this->criterions []= $criterion;
    }
    
    return $this;
  }
  
  /**
   * We want that the Finder fluid Interface works like:
   *   PHP : whereA()->whereB->orWhereC()->orWhereD()->whereE()
   *   SQL : where A=? AND (B=? OR (C=? OR (D=? AND E=?)))
   * So we have to add condition starting by the last one!
   */
  protected function buildCriteria()
  {
    $this->addMissingJoins();
    
    if($criterions = $this->criterions)
    {
      // Clone criterions to avoid repetition of conditions in a finder with several executions (like in a pager)
      foreach ($criterions as &$criterion) 
      { 
        $criterion = clone $criterion;
      }

      while ($criterion = array_pop($criterions))
      {
        if ($c = count($criterions))
        {
          call_user_func(array($criterions[$c-1], $criterion->func), $criterion);
        }
        else
        {
          call_user_func(array($this->criteria, $criterion->func), $criterion);
        }
      }
      // Reinitialize the criterions array so that this method can be called several times
      $this->criterions = array();
    }
    
    return $this->criteria;
  }
  
  /**
   * Finder fluid method to restrict results to a related object
   * Examples:
   *   $commentFinder->relatedTo($article)
   *    => $c->add(CommentPeer::ARTICLE_ID, $article->getId())
   *
   * @param  BaseObject $object The related object to restrict to
   * @return sfPropelFinder the current finder object
   */
  public function relatedTo($object)
  {
    // looking for a 1-n relationship
    $relatedObjectTableName = $object->getPeer()->getTableMap()->getName();
    foreach (sfPropelFinderUtils::getColumnsForPeerClass($this->peerClass) as $c)
    {
      if($c->getRelatedTableName() == $relatedObjectTableName)
      {
        $this->addCondition('and', $c->getFullyQualifiedName(), $object->getByName($c->getRelatedName(), BasePeer::TYPE_COLNAME), Criteria::EQUAL);
        
        return $this;
      }
    }
    // looking for a n-1 relationship
    $localObjectTableName = $this->object->getPeer()->getTableMap()->getName();
    foreach (sfPropelFinderUtils::getColumnsForPeerClass(get_class($object->getPeer())) as $c)
    {
      if($c->getRelatedTableName() == $localObjectTableName)
      {
        $this->addCondition('and', $c->getRelatedName(), $object->getByName($c->getFullyQualifiedName(), BasePeer::TYPE_COLNAME), Criteria::EQUAL);
        
        return $this;
      }
    }
    
    throw new Exception('Could not find a relation with object of class '.get_class($object));
  }
  
  /**
   * Finder Fluid Interface for Criteria::addAscendingOrderByColumn()
   * and Criteria::addDescendingOrderByColumn()
   * Infers $column and $order from $columnName and some optional arguments
   * Examples:
   *   $articleFinder->orderBy('CreatedAt')
   *    => $c->addAscendingOrderByColumn(ArticlePeer::CREATED_AT)
   *   $articlefinder->orderBy('CategoryId', 'desc')
   *    => $c->addDescendingOrderByColumn(ArticlePeer::CATEGORY_ID)
   *
   * @param string $columnName The column to order by
   * @param string $order      The sorting order. 'asc' by default, also accepts 'desc'
   *
   * @return     sfPropelFinder the current finder object
   */
  public function orderBy($columnName, $order = 'asc')
  {
    $column = $this->getColName($columnName);
    if(!$order)
    {
      $order = Criteria::ASC;
    }
    else
    {
      $order = strtoupper($order);
    }
    
    switch ($order)
    {
      case Criteria::ASC:
        $this->criteria->addAscendingOrderByColumn($column);
        break;
      case Criteria::DESC:
        $this->criteria->addDescendingOrderByColumn($column);
        break;
      default:
        throw new Exception('sfPropelFinder::orderBy() only accepts "asc" or "desc" as argument');
    }
    
    return $this;
  }

  /**
   * Finder Fluid Interface for Criteria::addGroupByColumn()
   * Infers $column and $order from $columnName and some optional arguments
   * Examples:
   *   $articleFinder->groupBy('CreatedAt')
   *    => $c->addGroupByColumn(ArticlePeer::CREATED_AT)
   *
   * @param string $columnName The column to group by
   *
   * @return     sfPropelFinder the current finder object
   */
  public function groupBy($columnName)
  {
    $column = $this->getColName($columnName);
    $this->criteria->addGroupByColumn($column);
    
    return $this;
  }
  
  /**
   * Finder Fluid Interface for Criteria::addGroupByColumn() but this times we add all columns from given class.
   * Examples:
   *   $articleFinder->groupBy('Article')
   *    => $c->addGroupByColumn(ArticlePeer::ID);$c->addGroupByColumn(ArticlePeer::TITLE);$c->addGroupByColumn(ArticlePeer::CREATED_AT);...
   * @param string $class
   *
   * @return sfPropelFinder the current finder object
   */
  public function groupByClass($class)
  {
    $peerClass = sfPropelFinderUtils::getPeerClassFromClass($class);
    $columns = call_user_func(array($peerClass, 'getFieldNames'), BasePeer::TYPE_COLNAME);
    foreach ($columns as $column)
    {
      $this->criteria->addGroupByColumn($column);
    }
    
    return $this;
  }
   
  /**
   * Guess sort column based on their names
   * Will look primarily for columns named:
   * 'UpdatedAt', 'UpdatedOn', 'CreatedAt', 'CreatedOn', 'Id'
   * You can change this sequence by modifying the app_sfPropelFinder_sort_column_guesses value
   *
   * @param string $direction 'desc' (default) or 'asc'
   *
   * @return sfPropelFinder the current finder object
   */
  public function guessOrder($direction = 'desc')
  {
    $columnNames = array();
    foreach (sfPropelFinderUtils::getColumnsForPeerClass($this->peerClass) as $c)
    {
      $columnNames []= $c->getPhpName();
    }
    foreach(sfConfig::get('app_sfPropelFinder_sort_column_guesses', array('UpdatedAt', 'UpdatedOn', 'CreatedAt', 'CreatedOn', 'Id')) as $testColumnName)
    {
      if(in_array($testColumnName, $columnNames))
      {
        $this->orderBy($testColumnName, $direction);
        return $this;
      }
    }
    
    throw new Exception('Unable to figure out the column to use to order rows in sfPropelFinder::guessOrder()');
  }
  
  /**
   * Finder Fluid Interface for Criteria::addJoin()
   * Infers $column1, $column2 and $operator from $relatedClass and some optional arguments
   * Uses the Propel column maps, based on the schema, to guess the related columns
   * Beware that the default JOIN operator is INNER JOIN, while Criteria defaults to WHERE
   * Examples:
   *   $articleFinder->join('Comment')
   *    => $c->addJoin(ArticlePeer::ID, CommentPeer::ARTICLE_ID, Criteria::INNER_JOIN)
   *   $articleFinder->join('Category', 'RIGHT JOIN')
   *    => $c->addJoin(ArticlePeer::CATEGORY_ID, CategoryPeer::ID, Criteria::RIGHT_JOIN)
   *   $articleFinder->join('Article.CategoryId', 'Category.Id', 'RIGHT JOIN')
   *    => $c->addJoin(ArticlePeer::CATEGORY_ID, CategoryPeer::ID, Criteria::RIGHT_JOIN)
   * 
   * @param  string $classOrColumn Class to join if 1 argument, first column of the join otherwise
   * @param  string $column Second column of the join if more than 1 argument
   * @param  string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
   *
   * @return     sfPropelFinder the current finder object
   */
  public function join()
  {
    $args = func_get_args();
    switch(count($args))
    {
      case 0:
        throw new Exception('sfPropelFinder::join() expects at least one argument');
        break;
      case 1:
      case 2:
        // $articleFinder->join('Comment')
        // $articleFinder->join('Category', 'RIGHT JOIN')
        list($relatedClass, $alias) = $this->getClassAndAlias($args[0]);
        list($column1, $column2) = $this->getRelation($relatedClass);
        $relatedPeerClass = sfPropelFinderUtils::getPeerClassFromClass($relatedClass);
        if($this->hasRelation($relatedPeerClass))
        {
          // There is already a relation on this table, so skip the join
          return $this;
        }
        $this->addRelation($relatedPeerClass, $alias);
        $operator = isset($args[1]) ? $args[1] : Criteria::INNER_JOIN;
        break;
      case 3:
        // $articleFinder->join('Article.CategoryId', 'Category.Id', 'RIGHT JOIN')
        list($column1, $column2, $operator) = $args;
        list($peerClass1, $column1) = $this->getColName($column1, $peerClass = null, $withPeerClass = true, $autoAddJoin = false);
        if($peerClass1 != $this->peerClass && !$this->hasRelation($peerClass1))
        {
          list($peerClass1, $alias) = $this->getClassAndAlias($peerClass1);
          if($this->hasRelation($peerClass1))
          {
            // There is already a relation on this table, so skip the join
            return $this;
          }
          $this->addRelation($peerClass1, $alias);
        }
        list($peerClass2, $column2) = $this->getColName($column2, $peerClass = null, $withPeerClass = true, $autoAddJoin = false);
        if($peerClass2 != $this->peerClass && !$this->hasRelation($peerClass2))
        {
          list($peerClass2, $alias) = $this->getClassAndAlias($peerClass2);
          if($this->hasRelation($peerClass2))
          {
            // There is already a relation on this table, so skip the join
            return $this;
          }
          $this->addRelation($peerClass2, $alias);
        }
        break;
    }
    $operator = trim(str_replace('JOIN', '', strtoupper($operator))) . ' JOIN';
    $this->criteria->addJoin($column1, $column2, $operator);
    
    return $this;
  }
  
  /**
   * Do no reinitialize the finder object after a termination method
   * By default the Criteria is wiped off whenever a termination method is called
   * Calling this method with true as parameter will keep the internal Criteria intact for the next termination method
   *
   * @param  Boolean $keep true (default) or false
   *
   * @return sfPropelFinder the current finder object
   */
  public function keepQuery($keep = true)
  {
    $this->reinit = !$keep;
    
    return $this;
  }
  
  protected function hasRelation($peerName)
  {
    return in_array($peerName, $this->relations);
  }
  
  /**
   * Guess the relation to another class
   *
   * @param string $phpName Propel Class name (e.g. 'Article')
   * 
   * @return array A list with the two columns member of the relationship
   */
  public function getRelation($phpName)
  {
    foreach($this->relations as $peerClass)
    {
      // try to find many to one or one to one relationship
      if($relation = $this->findRelation($phpName, $peerClass))
      {
        return $relation;
      }
      // try to find one to many relationship
      if($relation = $this->findRelation(
        sfPropelFinderUtils::getClassFromPeerClass($peerClass),
        sfPropelFinderUtils::getPeerClassFromClass($phpName)))
      {
        return array_reverse($relation);
      }
    }
    throw new Exception(sprintf('sfPropelFinder: %s has no %s related table', $this->peerClass, $phpName));
  }
  
  /**
   * Finds a relation between two classes by introspection
   */
  protected function findRelation($phpName, $peerClass)
  {
    foreach (sfPropelFinderUtils::getColumnsForPeerClass($peerClass) as $c)
    {
      if ($c->isForeignKey())
      {
        if(!$this->databaseMap->containsTable($c->getRelatedTableName()))
        {
          $mapBuilder = call_user_func(array(sfPropelFinderUtils::getPeerClassFromClass($phpName), 'getMapBuilder')); 
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
        if($tableMap->getPhpName() == $phpName)
        {
          return array(
            constant($peerClass.'::'.$c->getColumnName()),
            $c->getRelatedName()
          );
        }

      }
    }
    
    return false;
  }
  
  /**
   * Behavior-like supplementary getter for supplementary columns added by way of withColumn()
   * Requires symfony and sfMixer enabled
   *
   * @param BaseObject $object Propel object
   * @param string $alias Supplementary column name
   *
   * @return mixed The value of the column set by setColumn()
   */
  public function getColumn($object, $alias)
  {
    $alias = 'a'.md5($alias);
    
    return $object->$alias;
  }

  /**
   * Behavior-like supplementary setter for supplementary columns added by way of withColumn()
   * Requires symfony and sfMixer enabled
   *
   * @param BaseObject $object Propel object
   * @param string $alias Supplementary column name
   * @param mixed The value of the column
   */
  public function setColumn($object, $alias, $value)
  {
    $alias = 'a'.md5($alias);
    $object->$alias = $value;
  }
  
  /**
   * Returns a unique key for this finder conditions - necessary for caching
   *
   * @return string
   */
  public function getUniqueIdentifier()
  {
    return __CLASS__ . md5(
      $this->getClass().
      serialize($this->getCriteria()).
      serialize($this->getWithClasses()).
      serialize($this->getWithColumns())
    );
  }
  
  /**
   * Enable or disable query caching
   *
   * @param Mixed $cacheInstance A Cache object implementing get(), set(), has() and clear() methods
   *                             Or a DbFinderCache instance
   * @param integer $lifetime    Cache lifetime, in seconds
   *
   * @return sfPropelFinder the current finder object
   */
  public function useCache($cacheInstance, $lifetime = 0)
  {
    if($cacheInstance)
    {
      if($cacheInstance instanceof sfPropelFinderCache)
      {
        $this->cache = $cacheInstance;
      }
      else
      {
        $this->cache = new sfPropelFinderCache($cacheInstance, $lifetime);
      }
    }
    else
    {
      $this->cache = null;
    }
    
    return $this;
  }
  
  /**
   * Makes an empty Criteria match all records
   * Some Propel Methods (like doDelete()) need a Criteria with at least one condition to execute
   * To match all records, this methods adds a condition which is always true
   * 
   * @param Criteria $c The Criteria object to make true
   *
   * @return Criteria A true Criteria
   */
  protected function addTrueCondition(Criteria $c)
  {
    $fieldNames = call_user_func(array($this->peerClass, 'getFieldNames'), BasePeer::TYPE_COLNAME);
    $firstFieldName = $fieldNames[0];
    $c->add($firstFieldName, '1=1', Criteria::CUSTOM);
    
    return $c;
  }
  
  protected function getClassAndAlias($class)
  {
    if(strpos($class, ' ') !== false)
    {
      list($class, $alias) = explode(' ', $class);
    }
    else
    {
      $alias = strtolower(substr($class, 0, 1));
      while(isset($this->relations[$alias]))
      {
        $alias .= '1';
      }
    }
    return array($class, $alias);
  }
  
  protected function getColName($phpName, $peerClass = null, $withPeerClass = false, $autoAddJoin = true)
  {
    if(array_key_exists($phpName, $this->withColumns))
    {
      return $phpName;
    }
    if(strpos($phpName, '.') !== false)
    {
      // Table.Column
      list($class, $phpName) = explode('.', $phpName);
      if(array_key_exists($class, $this->relations))
      {
        $peerClass = $this->relations[$class];
      }
      else
      {
        $peerClass = sfPropelFinderUtils::getPeerClassFromClass($class);
      }
    }
    else if(strpos($phpName, '_') !== false)
    {
      // Table_Column, or Table_Name_Column, so explode is not a solution here
      $limit = strrpos($phpName, '_');
      $class = substr($phpName, 0, $limit);
      $phpName = substr($phpName, $limit + 1);
      $peerClass = sfPropelFinderUtils::getPeerClassFromClass($class);
    }
    if(!$peerClass)
    {
      // Column
      $peerClass = $this->peerClass;
    }
    if($peerClass != $this->peerClass && !$this->hasRelation($peerClass) && $autoAddJoin)
    { 
      $this->join($class);
    }
    try
    {
      $column = call_user_func(array($peerClass, 'translateFieldName'), ucfirst($phpName), BasePeer::TYPE_PHPNAME, BasePeer::TYPE_COLNAME);
      return $withPeerClass ? array($peerClass, $column) : $column;
    }
    catch (PropelException $e)
    {
      throw new Exception(sprintf('sfPropelFinder: %s has no %s column', $peerClass, $phpName));
    }
  }
  
  /**
   * Cloning instance should clone its components.
   *  
   * Solves problems with sfPropelFinderPager.
   */ 
  public function __clone()
  { 
    $this->criteria = clone $this->criteria;
  }
}