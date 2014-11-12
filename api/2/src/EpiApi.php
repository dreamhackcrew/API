<?php
class EpiApi
{
  private static $instance;
  private $routes = array();
  private $regexes= array();

  const internal = 'private';
  const external = 'public';

  /**
   * get('/', 'function');
   * @name  get
   * @author  Jaisen Mathai <jaisen@jmathai.com>
   * @param string $path
   * @param mixed $callback
   */
  public function get($route, $callback, $access, $visibility = self::internal)
  {
    $this->addRoute($route, $callback, EpiRoute::httpGet, $access);
    if($visibility === self::external)
      getRoute()->get($route, $callback, $access, true);
  }

  public function post($route, $callback, $access, $visibility = self::internal)
  {
    $this->addRoute($route, $callback, EpiRoute::httpPost, $access);
    if($visibility === self::external)
      getRoute()->post($route, $callback, $access, true);
  }
  
  public function put($route, $callback, $access, $visibility = self::internal)
  {
    $this->addRoute($route, $callback, EpiRoute::httpPut, $access);
    if($visibility === self::external)
      getRoute()->put($route, $callback, $access, true);
  }
  
  public function delete($route, $callback, $access, $visibility = self::internal)
  {
    $this->addRoute($route, $callback, EpiRoute::httpDelete, $access);
    if($visibility === self::external)
      getRoute()->delete($route, $callback, $access, true);
  }

  public function invoke($route, $httpMethod = EpiRoute::httpGet, $params = array())
  {
    $routeDef = $this->getRoute($route, $httpMethod);

    // this is ugly but required if internal and external calls are to work
    $tmps = array();
    foreach($params as $type => $value)
    {
      $tmps[$type] = $GLOBALS[$type];
      $GLOBALS[$type] = $value;
    }

    $retval = call_user_func_array($routeDef['callback'], $routeDef['args']);

    // restore sanity
    foreach($tmps as $type => $value)
      $GLOBALS[$type] = $value; 

    return $retval;
  }

  /**
   * EpiApi::getRoute($route); 
   * @name  getRoute
   * @author  Jaisen Mathai <jaisen@jmathai.com>
   * @param string $route
   * @method getRoute
   * @static method
   */
  public function getRoute($route, $httpMethod)
  {
    foreach($this->regexes as $ind => $regex)
    {
      if(preg_match($regex, $route, $arguments))
      {
        array_shift($arguments);
        $def = $this->routes[$ind];
        if($httpMethod != $def['httpMethod'])
        {
          continue;
        }
        else if(is_array($def['callback']) && method_exists($def['callback'][0], $def['callback'][1]))
        {
          if(Epi::getSetting('debug'))
            getDebug()->addMessage(__CLASS__, sprintf('Matched %s : %s : %s : %s', $httpMethod, $this->route, json_encode($def['callback']), json_encode($arguments)));
          return array('callback' => $def['callback'], 'args' => $arguments, 'postprocess' => true);
        }
        else if(function_exists($def['callback']))
        {
          if(Epi::getSetting('debug'))
            getDebug()->addMessage(__CLASS__, sprintf('Matched %s : %s : %s : %s', $httpMethod, $this->route, json_encode($def['callback']), json_encode($arguments)));
          return array('callback' => $def['callback'], 'args' => $arguments, 'postprocess' => true);
        }

        EpiException::raise(new EpiException('Could not call ' . json_encode($def) . " for route {$regex}"));
      }
    }
    EpiException::raise(new EpiException("Could not find route {$this->route} from {$route}"));
  }

  /**
   * addRoute('/', 'function', 'GET');
   * @name  addRoute
   * @author  Jaisen Mathai <jaisen@jmathai.com>
   * @param string $path
   * @param mixed $callback
   * @param mixed $method
   */
  private function addRoute($route, $callback, $method,  $access)
  {
    $this->routes[] = array('httpMethod' => $method, 'path' => $route, 'callback' => $callback, 'access' => $access);
    $this->regexes[]= "#^{$route}\$#";
  }

  public function listRoutes() {
    return $this->routes;
  }

  public function checkFields($post, $fields) {
    $allFields = array();

    // Check the required fields
    if ( isset($fields['required']) ) {
        $missing = array();
        foreach($fields['required'] as $key) {
            if ( !isset($post[$key]) ) {
                $missing[] = $key;
            }
            unset($post[$key]);
            $allFields[$key] = $key;
        }

        if ( $missing ) {
            http_response_code(400);
            trigger_error("Missing fields: ".implode(', ',$missing));
        }
    }

    // Remove the optional fields
    if ( isset($fields['optional']) ) {
        foreach($fields['optional'] as $key) {
            unset($post[$key]);
            $allFields[$key] = $key;
        }
    }

    // Check if there are any fields left..
    if ( $post ) {
        http_response_code(400);
        trigger_error("Unrecognized fields: ".implode(', ',array_keys($post)));
    }

    return $allFields;
  }
}

function getApi()
{
  static $api;
  if(!$api)
    $api = new EpiApi();

  return $api;
}
