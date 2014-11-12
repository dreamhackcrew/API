<?php
/**
 * EpiRoute master file
 *
 * This contains the EpiRoute class as wel as the EpiException abstract class
 * @author  Jaisen Mathai <jaisen@jmathai.com>
 * @version 1.0  
 * @package EpiRoute
 */

/**
 * This is the EpiRoute class.
 * @name    EpiRoute
 * @author  Jaisen Mathai <jaisen@jmathai.com>
 * @final
 */
class EpiRoute
{
  private static $instance;
  private $routes = array();
  private $regexes= array();
  private $route = null;
  const routeKey= '__route__';
  const httpGet = 'GET';
  const httpPost= 'POST';
  const httpPut = 'PUT';
  const httpDelete = 'DELETE';

  /**
   * get('/', 'function');
   * @name  get
   * @author  Jaisen Mathai <jaisen@jmathai.com>
   * @param string $route
   * @param mixed $callback
   */
  public function get($route, $callback, $access, $isApi = false)
  {
    $this->addRoute($route, $callback, self::httpGet, $access, $isApi);
  }

  /**
   * post('/', 'function');
   * @name  post
   * @author  Jaisen Mathai <jaisen@jmathai.com>
   * @param string $route
   * @param mixed $callback
   */
  public function post($route, $callback, $access, $isApi = false)
  {
    $this->addRoute($route, $callback, self::httpPost, $access, $isApi);
  }

  /**
   * put('/', 'function');
   * @name  put
   * @author  Sandro Meier <sandro.meier@fidelisfactory.ch>
   * @param string $route
   * @param mixed $callback
   */
  public function put($route, $callback, $access, $isApi = false)
  {
    $this->addRoute($route, $callback, self::httpPut, $access, $isApi);
  }
  
  /**
   * delete('/', 'function');
   * @name  delete
   * @author  Sandro Meier <sandro.meier@fidelisfactory.ch>
   * @param string $route
   * @param mixed $callback
   */
  public function delete($route, $callback, $access, $isApi = false)
  {
    $this->addRoute($route, $callback, self::httpDelete, $access, $isApi);
  }

  /**
   * NOT YET IMPLEMENTED
   * request('/', 'function', array(EpiRoute::httpGet, EpiRoute::httpPost));
   * @name  request
   * @author  Jaisen Mathai <jaisen@jmathai.com>
   * @param string $route
   * @param mixed $callback
   */
  /*public function request($route, $callback, $httpMethods = array(self::httpGet, self::httpPost))
  {
  }*/

  /**
   * load('/path/to/file');
   * @name  load
   * @author  Jaisen Mathai <jaisen@jmathai.com>
   * @param string $file
   */
  public function load($file)
  {
    $file = Epi::getPath('config') . "/{$file}";
    if(!file_exists($file))
    {
      EpiException::raise(new EpiException("Config file ({$file}) does not exist"));
      break; // need to simulate same behavior if exceptions are turned off
    }

    $parsed_array = parse_ini_file($file, true);
    foreach($parsed_array as $route)
    {
      $method = strtolower($route['method']);
      if(isset($route['class']) && isset($route['function']))
        $this->$method($route['path'], array($route['class'], $route['function']));
      elseif(isset($route['function']))
        $this->$method($route['path'], $route['function']);
    }
  }
  
  /**
   * EpiRoute::run($_GET['__route__'], $_['routes']); 
   * @name  run
   * @author  Jaisen Mathai <jaisen@jmathai.com>
   * @param string $route
   * @param array $routes
   * @method run
   * @static method
   */
  public function run($route = false, $httpMethod = null)
  {
    if($route === false)
      $route = isset($_GET[self::routeKey]) ? $_GET[self::routeKey] : '/';

    if($httpMethod === null)
      $httpMethod = $_SERVER['REQUEST_METHOD'];

    if ( !$routeDef = $this->getRoute($route, $httpMethod) )
        return;

	/*
	 * max: if value access is set to secure, and $_SESSION['user_id'] is not set, trigger error. 
	 */

	if ( $routeDef['access'] == "secure" ) {
		if ( !isset($_SESSION['id']) ) {
			http_response_code(401);
            header('WWW-Authenticate: Basic realm="API"');
            header('HTTP/1.0 401 Unauthorized');
			trigger_error('Unauthorized'); 
		}
	}	

    unset($_GET[self::routeKey]);

    // https://www.dropbox.com/developers/core/docs

    if ( $httpMethod == 'PUT' || $httpMethod == 'POST' ) {
        $rawPostData = file_get_contents('php://input');
        if ( !$rawPostData ) {
            http_response_code(400);
            trigger_error('Missing POST body',E_USER_ERROR);
        }

        $json = json_decode($rawPostData,true);

        if ( $json === null ) {
            http_response_code(400);
            trigger_error('Syntax error (json) POST body',E_USER_ERROR);
        }

        array_push($routeDef['args'], $json);
    }

    $response = call_user_func_array($routeDef['callback'], $routeDef['args']);
    if(!$routeDef['postprocess'])
      return $response;
    else
    {
      // Only echo the response if it's not null. 
      if (!is_null($response))
      {
        //$response = json_encode($response);
        //if(isset($_GET['callback']))
          //$response = "{$_GET['callback']}($response)";
        //else
          //header('Content-Type: application/json');

        //header('Content-Length:' . strlen($response));
		return $response;
      }

	  //header('Content-Type: application/json');
	  return(array('error'=>'Not implemented correctly, backend returned null'));
    }
  }

  /**
   * EpiRoute::getRoute($route); 
   * @name  getRoute
   * @author  Jaisen Mathai <jaisen@jmathai.com>
   * @param string $route
   * @method getRoute
   * @static method
   */
  public function getRoute($route = false, $httpMethod = null)
  {
    if($route)
      $this->route = $route;
    else
      $this->route = isset($_GET[self::routeKey]) ? $_GET[self::routeKey] : '/';

    if($httpMethod === null)
      $httpMethod = $_SERVER['REQUEST_METHOD'];

    foreach($this->regexes as $ind => $regex)
    {
      if(preg_match($regex, $this->route, $arguments))
      {
        array_shift($arguments);
        $def = $this->routes[$ind];
        if($httpMethod != $def['httpMethod'])
        {
          continue;
		}

		if ( is_array($def['callback']) ) {
			include_once('api/2/modules/'.$def['callback'][0].'.php');
		}

        if(is_array($def['callback']) && method_exists($def['callback'][0], $def['callback'][1]))
        {
          if(Epi::getSetting('debug'))
            getDebug()->addMessage(__CLASS__, sprintf('Matched %s : %s : %s : %s', $httpMethod, $this->route, json_encode($def['callback']), json_encode($arguments)));
          return array('callback' => $def['callback'], 'args' => $arguments, 'postprocess' => $def['postprocess'], 'access' => $def['access']);
        }
        else if(!is_array($def['callback']) && function_exists($def['callback']))
        {
          if(Epi::getSetting('debug'))
            getDebug()->addMessage(__CLASS__, sprintf('Matched %s : %s : %s : %s', $httpMethod, $this->route, json_encode($def['callback']), json_encode($arguments)));
          return array('callback' => $def['callback'], 'args' => $arguments, 'postprocess' => $def['postprocess'], 'access' => $def['access']);
        }

        EpiException::raise(new EpiException('Could not call ' . json_encode($def) . " for route {$regex}"));
      }
	}
    EpiException::raise(new EpiException("Could not find route {$this->route} from {$this->route}"));
  }

  /**
   * EpiRoute::redirect($url); 
   * @name  redirect
   * @author  Jaisen Mathai <jaisen@jmathai.com>
   * @param string $url
   * @method redirect
   * @static method
   */
  public function redirect($url, $code = null, $offDomain = false)
  {
    $continue = !empty($url);
    if($offDomain === false && preg_match('#^https?://#', $url))
      $continue = false;

    if($continue)
    {
      if($code != null && (int)$code == $code)
        header("Status: {$code}");
      header("Location: {$url}");
      die();
    }
    EpiException::raise(new EpiException("Redirect to {$url} failed"));
  }

  public function route()
  {
    return $this->route;
  }

  /*
   * EpiRoute::getInstance
   */
  public static function getInstance()
  {
    if(self::$instance)
      return self::$instance;

    self::$instance = new EpiRoute;
    return self::$instance;
  }

  /**
   * addRoute('/', 'function', 'GET');
   * @name  addRoute
   * @author  Jaisen Mathai <jaisen@jmathai.com>
   * @param string $route
   * @param mixed $callback
   * @param mixed $method
   * @param string $callback
   */
  private function addRoute($route, $callback, $method, $access ,$postprocess = false)
  {
    /*
	if ( $access == "secure" ) {
		// user needs to be logged in
		if ( isset($_SESSION['sessionId']) ) {

  		  $this->routes[] = array('httpMethod' => $method, 'path' => $route, 'callback' => $callback, 'postprocess' => $postprocess);
		  $this->regexes[]= "#^{$route}\$#";
    	  if(Epi::getSetting('debug'))
     	  getDebug()->addMessage(__CLASS__, sprintf('Found %s : %s : %s', $method, $route, json_encode($callback)));

		} else {

			//return 401
			http_response_code(401);
			trigger_error('Access is denied');

		}


	} else if ( $access == "insecure" ) {
		// user can see this without being logged in

  		  $this->routes[] = array('httpMethod' => $method, 'path' => $route, 'callback' => $callback, 'postprocess' => $postprocess);
		  $this->regexes[]= "#^{$route}\$#";
    	  if(Epi::getSetting('debug'))
     	  getDebug()->addMessage(__CLASS__, sprintf('Found %s : %s : %s', $method, $route, json_encode($callback)));

    }*/

      $this->routes[] = array('httpMethod' => $method, 'path' => $route, 'callback' => $callback, 'postprocess' => $postprocess, 'access'=>$access);
    $this->regexes[]= "#^{$route}\$#";
    if(Epi::getSetting('debug'))
      getDebug()->addMessage(__CLASS__, sprintf('Found %s : %s : %s', $method, $route, json_encode($callback)));
  }
}

function getRoute()
{
  return EpiRoute::getInstance();
}
