<?php

class DbFinderAdminGenerator extends sfGenerator
{
  const
    PROPEL     = 'Propel',
    DOCTRINE   = 'Doctrine';
  protected
    $generator = null,
    $orm       = null;
  
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
  
  public function getColumnType($column)
  {
    return DbFinderColumn::getType($column, $this->orm);
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