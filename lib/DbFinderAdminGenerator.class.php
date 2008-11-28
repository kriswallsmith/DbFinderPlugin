<?php

class DbFinderAdminGenerator extends sfGenerator
{
  const
    PROPEL     = 'Propel',
    DOCTRINE   = 'Doctrine';
  protected
    $generator = null,
    $orm       = null,
    $version   = null;
  
  /**
   * Generates classes and templates in cache.
   *
   * @param array The parameters
   *
   * @return string The data to put in configuration cache
   */
  public function generate($params = array())
  {
    if (!isset($params['model_class']))
    {
      $error = 'You must specify a "model_class"';
      $error = sprintf($error, $entry);

      throw new sfParseException($error);
    }
    $modelClass = $params['model_class'];

    if (!class_exists($modelClass))
    {
      $error = 'Unable to scaffold unexistant model "%s"';
      $error = sprintf($error, $modelClass);

      throw new sfInitializationException($error);
    }
    
    $tmp = new $modelClass;
    if($tmp instanceof BaseObject)
    {
      $this->generator = new sfPropelAdminGenerator($this->generatorManager);
      $this->orm = self::PROPEL;
    }
    elseif($tmp instanceof Doctrine_Record)
    {
      $this->generator = new sfDoctrineAdminGenerator($this->generatorManager);
      $this->orm = self::DOCTRINE;
    }
    // Manual initialization required for symfony 1.0
    $this->generator->initialize($this->generatorManager);
    $this->generator->setGeneratorClass('DbFinderAdmin');
    $this->generator->DbFinderAdminGenerator = $this;
    return $this->generator->generate($params);
  }
  

  const STRING = "STRING";
  const NUMERIC = "NUMERIC";
  const DECIMAL = "DECIMAL";
  const TINYINT = "TINYINT";
  const SMALLINT = "SMALLINT";
  const INTEGER = "INTEGER";
  const BIGINT = "BIGINT";
  const REAL = "REAL";
  const FLOAT = "FLOAT";
  const DOUBLE = "DOUBLE";
  const BINARY = "BINARY";
  const VARBINARY = "VARBINARY";
  const LONGVARBINARY = "LONGVARBINARY";
  const BLOB = "BLOB";
  const DATE = "DATE";
  const TIME = "TIME";
  const TIMESTAMP = "TIMESTAMP";
  const BU_DATE = "BU_DATE";
  const BU_TIMESTAMP = "BU_TIMESTAMP";
  const BOOLEAN = "BOOLEAN";
  
  private static $propel12ToDbFinderMap = array(
    1 => self::BOOLEAN,
    2 => self::BIGINT,
    3 => self::SMALLINT,
    4 => self::TINYINT,
    5 => self::INTEGER,
    6 => self::STRING,
    7 => self::STRING,
    8 => self::FLOAT,
    9 => self::DOUBLE,
    10 => self::DATE,
    11 => self::TIME,
    12 => self::TIMESTAMP,
    13 => self::VARBINARY,
    14 => self::NUMERIC,
    15 => self::BLOB,
    16 => self::STRING,
    17 => self::STRING,
    18 => self::DECIMAL,
    19 => self::REAL,
    20 => self::BINARY,
    21 => self::LONGVARBINARY,
    22 => self::INTEGER,
  );
  private static $propel13ToDbFinderMap = array(
    'CHAR'          => self::STRING,
    'VARCHAR'       => self::STRING,
    'LONGVARCHAR'   => self::STRING,
    'CLOB'          => self::STRING,
    'NUMERIC'       => self::NUMERIC,
    'DECIMAL'       => self::DECIMAL,
    'TINYINT'       => self::TINYINT,
    'SMALLINT'      => self::SMALLINT,
    'INTEGER'       => self::INTEGER,
    'BIGINT'        => self::BIGINT,
    'REAL'          => self::REAL,
    'FLOAT'         => self::FLOAT,
    'DOUBLE'        => self::DOUBLE,
    'BINARY'        => self::BINARY,
    'VARBINARY'     => self::VARBINARY,
    'LONGVARBINARY' => self::LONGVARBINARY,
    'BLOB'          => self::BLOB,
    'DATE'          => self::DATE,
    'TIME'          => self::TIME,
    'TIMESTAMP'     => self::TIMESTAMP,
    'BU_DATE'       => self::BU_DATE,
    'BU_TIMESTAMP'  => self::BU_TIMESTAMP,
    'BOOLEAN'       => self::BOOLEAN,
  );
  private static $doctrineToDbFinderMap = array(
    'enum'      => self::INTEGER,
    'text'      => self::STRING,
    'object'    => self::STRING,
    'array'     => self::STRING,
    'string'    => self::STRING,
    'char'      => self::STRING,
    'gzip'      => self::STRING,
    'varchar'   => self::STRING,
    'clob'      => self::STRING,
    'blob'      => self::BLOB,
    'integer'   => self::INTEGER,
    'boolean'   => self::BOOLEAN,
    'int'       => self::INTEGER,
    'date'      => self::DATE,
    'time'      => self::TIME,
    'timestamp' => self::TIMESTAMP,
    'float'     => self::FLOAT,
    'double'    => self::DOUBLE,
    'decimal'   => self::DECIMAL,
    
  );
  public function getColumnType($column)
  {
    if($this->orm == self::PROPEL)
    {
      if(is_null($this->version))
      {
        $this->version = method_exists('ColumnMap', 'getCreoleType') ? 12 : 13;
      }
      if($this->version == 12)
      {
        // Propel 1.2
        if($type = $column->getCreoleType())
        {
          $type = self::$propel12ToDbFinderMap[$type];
        }
      }
      else
      {
        // Propel 1.3
        if($type = $column->getType())
        {
          $type = self::$propel13ToDbFinderMap[$type];
        }
      }
    }
    else
    {
      // Doctrine
      if($type = $column->getType())
      {
        $type = self::$doctrineToDbFinderMap[$type];
      }
    }
    
    return $type;
  }
  
  public function getColumnSetter($column, $value, $singleQuotes = false, $prefix = 'this->')
  {
    if($this->orm == self::PROPEL)
    {
      if ($singleQuotes) $value = sprintf("'%s'", $value);
      return sprintf('$%s%s->set%s(%s)', $prefix, $this->getSingularName(), $column->getPhpName(), $value);
    }
    else
    {
      return $this->generator->getColumnSetter($column, $value, $singleQuotes, $prefix);
    }
  }
  
  /** 
   * Returns HTML code for an action option in a select tag.
   * 
   * @param string  The action name 
   * @param array   The parameters 
   * 
   * @return string HTML code 
   */ 
  public function getOptionToAction($actionName, $params) 
  { 
    $options = isset($params['params']) ? sfToolkit::stringToArray($params['params']) : array(); 
    
    // default values 
    if ($actionName[0] == '_') 
    { 
      $actionName = substr($actionName, 1); 
      if ($actionName == 'deleteSelected') 
      { 
        $params['name'] = 'Delete Selected'; 
      } 
    } 
    $name = isset($params['name']) ? $params['name'] : $actionName; 
    
    $options['value'] = $actionName; 
    
    $phpOptions = var_export($options, true); 
    
    return '[?php echo content_tag(\'option\', __(\''.$name.'\')'.($options ? ', '.$phpOptions : '').') ?]'; 
  }
  
  public function __call($method, $arguments)
  {
    return call_user_func_array(array($this->generator, $method), $arguments);
  }
}