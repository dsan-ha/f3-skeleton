<?php

namespace App\Http;

use App\F4;
use App\Http\Response;
use App\Http\Request;

class Router {
    const
        VERBS='GET|HEAD|POST|PUT|PATCH|DELETE|CONNECT|OPTIONS';

    const
        E_Pattern='Invalid routing pattern: %s',
        E_Named='Named route does not exist: %s',
        E_Alias='Invalid named route alias: %s',
        E_Onreroute='Router ONREROUTE method busy',
        E_Handler = 'Invalid route handler. Use either [$className, $method] or a callable function.',
        E_Routes='No routes specified';

    const
        REQ_SYNC=1,
        REQ_AJAX=2,
        REQ_CLI=4;

    private $alias;
    private $aliases;
    private $routes;
    private const TYPES = ['sync','ajax','cli'];
    private $globalMiddleware;
    private F4 $f3;
    private Request $req;
    private Response $res;

    public function __construct(F4 $f3, Request $req, Response $res) {
        $this->f3 = $f3;
        $this->req = $req;
        $this->res = $res;
        $this->globalMiddleware = new MiddlewareDispatcher();
        $base = $f3->get('BASE');
        $uri = $f3->get('URI');
        if (PHP_SAPI=='cli-server' &&
            preg_match('/^'.preg_quote($base,'/').'$/',$uri))
            $this->reroute('/');
    }

    /**
    *   Replace tokenized URL with available token values
    *   @return string
    *   @param $url array|string
    *   @param $addParams boolean merge default PARAMS from hive into args
    *   @param $args array
    **/
    function build($url, $args=[], $addParams=TRUE) {
        $params = $this->f3->get('PARAMS');
        if ($addParams)
            $args+=$this->f3->recursive($params, function($val) {
                return implode('/', array_map('urlencode', explode('/', $val)));
            });
        if (is_array($url))
            foreach ($url as &$var) {
                $var=$this->build($var,$args, false);
                unset($var);
            }
        else {
            $i=0;
            $url=preg_replace_callback('/(\{)?@(\w+)(?(1)\})|(\*)/',
                function($match) use(&$i,$args) {
                    if (isset($match[2]) &&
                        array_key_exists($match[2],$args))
                        return $args[$match[2]];
                    if (isset($match[3]) &&
                        array_key_exists($match[3],$args)) {
                        if (!is_array($args[$match[3]]))
                            return $args[$match[3]];
                        ++$i;
                        return $args[$match[3]][$i-1];
                    }
                    return $match[0];
                },$url);
        }
        return $url;
    }

    /**
    *   Mock HTTP request
    *   @return mixed
    *   @param $pattern string
    *   @param $args array
    *   @param $headers array
    *   @param $body string
    **/
    function mock($pattern,
        ?array $args=NULL,?array $headers=NULL,$body=NULL) {
        $f3 = $this->f3;
        if (!$args)
            $args=[];
        $types=['sync','ajax','cli'];
        preg_match('/([\|\w]+)\h+(?:@(\w+)(?:(\(.+?)\))*|([^\h]+))'.
            '(?:\h+\[('.implode('|',$types).')\])?/',$pattern,$parts);
        $verb=strtoupper($parts[1]);
        if ($parts[2]) {
            if (empty($this->aliases[$parts[2]]))
                user_error(sprintf(self::E_Named,$parts[2]),E_USER_ERROR);
            $parts[4]=$this->aliases[$parts[2]];
            $parts[4]=$this->build($parts[4],
                isset($parts[3])?$this->f3->parse($parts[3]):[]);
        }
        if (empty($parts[4]))
            user_error(sprintf(self::E_Pattern,$pattern),E_USER_ERROR);
        $url=parse_url($parts[4]);
        parse_str(isset($url['query'])?$url['query']:'',$GLOBALS['_GET']);
        if (preg_match('/GET|HEAD/',$verb))
            $GLOBALS['_GET']=array_merge($GLOBALS['_GET'],$args);
        $GLOBALS['_POST']=$verb=='POST'?$args:[];
        $GLOBALS['_REQUEST']=array_merge($GLOBALS['_GET'],$GLOBALS['_POST']);
        foreach ($headers?:[] as $key=>$val)
            $_SERVER['HTTP_'.strtr(strtoupper($key),'-','_')]=$val;
        $f3->set('VERB',$verb);
        $f3->set('PATH',$url['path']);
        $f3->set('URI',$f3->get('BASE').$url['path']);
        $uri = $f3->get('URI');
        if ($GLOBALS['_GET'])
            $uri.='?'.http_build_query($GLOBALS['_GET']);
        $f3->set('URI', $uri);
        $b='';
        if (!preg_match('/GET|HEAD/',$verb)){
            $b=$body?:http_build_query($args);
        }
        $f3->set('BODY', $b);
        $ajax=isset($parts[5]) &&
            preg_match('/ajax/i',$parts[5]);
        $cli=isset($parts[5]) &&
            preg_match('/cli/i',$parts[5]);
        $f3->set('AJAX',$ajax);
        $f3->set('CLI',$cli);
        return $this->f3->run();
    }

    /**
    *   Bind handler to route pattern
    *   @return NULL* 
    *   @param string|array $pattern
    *   @param array|callable $handler  // только [$className, $method] или callable
    *   @param int $ttl
    *   @param int $kbps
    **/
    public function route($pattern,$handler,$ttl=0,$kbps=0) {
        $alias=null;
        preg_match('/([\|\w]+)\h+(?:(?:@?(.+?)\h*:\h*)?(@(\w+)|[^\h]+))'.
            '(?:\h+\[('.implode('|',self::TYPES).')\])?/u',$pattern,$parts);
        if (isset($parts[2]) && $parts[2]) {
            if (!preg_match('/^\w+$/',$parts[2]))
                user_error(sprintf(self::E_Alias,$parts[2]),E_USER_ERROR);
            $this->aliases[$alias=$parts[2]]=$parts[3];
        }
        elseif (!empty($parts[4])) {
            if (empty($this->aliases[$parts[4]]))
                user_error(sprintf(self::E_Named,$parts[4]),E_USER_ERROR);
            $parts[3]=$this->aliases[$alias=$parts[4]];
        }
        if (empty($parts[3]))
            user_error(sprintf(self::E_Pattern,$pattern),E_USER_ERROR);
        $type=empty($parts[5])?0:constant('self::REQ_'.strtoupper($parts[5]));
        $validArrayForm =
            is_array($handler)
            && count($handler) === 2
            && is_string($handler[0])
            && is_string($handler[1]);
        if (!is_callable($handler) && !$validArrayForm) {
            user_error(self::E_Handler, E_USER_ERROR);
        }
        $routes = new RoutesCollection();
        $uri = $this->f3->get('URI');
        foreach ($this->f3->split($parts[1]) as $verb) {
            if (!preg_match('/'.self::VERBS.'/',$verb))
                $this->f3->error(501,$verb.' '.$uri);
            $route = new Route([$handler,$ttl,$kbps,$alias]);
            $this->routes[$parts[3]][$type][strtoupper($verb)] = &$route;
            $routes->addRoute($route);
        }
        return $routes;
    }

    public function addMiddleware(callable $mw) {
        $this->globalMiddleware->add($mw);
        return $this;
    }

    /**
    *   Assemble url from alias name
    *   @return string
    *   @param $name string
    *   @param $params array|string
    *   @param $query string|array
    *   @param $fragment string
    **/
    function alias($name,$params=[],$query=NULL,$fragment=NULL) {
        if (!is_array($params))
            $params=$this->f3->parse($params);
        if (empty($this->aliases[$name]))
            user_error(sprintf(self::E_Named,$name),E_USER_ERROR);
        $url=$this->build($this->aliases[$name],$params);
        if (is_array($query))
            $query=http_build_query($query);
        return $url.($query?('?'.$query):'').($fragment?'#'.$fragment:'');
    }

    /**
    *   Reroute to specified URI
    *   @return NULL
    *   @param $url array|string
    *   @param $permanent bool
    *   @param $die bool
    **/
    function reroute($url=NULL,$permanent=FALSE,$die=TRUE) {
        if (!$url)
            $url=$this->req->getUri();
        if (is_array($url))
            $url=call_user_func_array([$this,'alias'],$url);
        elseif (preg_match('/^(?:@([^\/()?#]+)(?:\((.+?)\))*(\?[^#]+)*(#.+)*)/',
            $url,$parts) && isset($this->aliases[$parts[1]]))
            $url=$this->build($this->aliases[$parts[1]],
                    isset($parts[2])?$this->f3->parse($parts[2]):[]).
                (isset($parts[3])?$parts[3]:'').(isset($parts[4])?$parts[4]:'');
        else
            $url=$this->build($url);

        if (($handler=$this->f3->get('ONREROUTE')) &&
            $this->f3->call($handler,[$url,$permanent,$die])!==FALSE)
            return;
        if ($url[0]!='/' && !preg_match('/^\w+:\/\//i',$url))
            $url='/'.$url;
        if ($url[0]=='/' && (empty($url[1]) || $url[1]!='/')) {
            $port=$this->req->getPort();
            $port=in_array($port,[80,443])?'':(':'.$port);
            $url=$this->req->getScheme().'://'.
                $this->req->getHost().$port.$this->f3->get('BASE').$url;
        }
        $cli = $this->f3->get('CLI');
        if ($cli)
            $this->mock('GET '.$url.' [cli]');
        else {
            header('Location: '.$url);
            $this->f3->status($permanent?301:302);
            if ($die)
                die;
        }
    }

    /**
    *   Redirect a route to another URL
    *   @return NULL
    *   @param $pattern string|array
    *   @param $url string
    *   @param $permanent bool
    */
    function redirect($pattern,$url,$permanent=TRUE) {
        if (is_array($pattern)) {
            foreach ($pattern as $item)
                $this->redirect($item,$url,$permanent);
            return;
        }
        $this->route($pattern,function($fw) use($url,$permanent) {
            $fw->reroute($url,$permanent);
        });
    }

    /**
    *   Applies the specified URL mask and returns parameterized matches
    *   @return $args array
    *   @param $pattern string
    *   @param $url string|NULL
    **/
    function mask($pattern,$url=NULL) {
        if (!$url)
            $url=$this->f3->rel($this->f3->get('URI'));
        $case=$this->f3->get('CASELESS')?'i':'';
        $wild=preg_quote($pattern,'/');
        $i=0;
        while (is_int($pos=strpos($wild,'\*'))) {
            $wild=substr_replace($wild,'(?P<_'.$i.'>[^\?]*)',$pos,2);
            ++$i;
        }
        preg_match('/^'.
            preg_replace(
                '/((\\\{)?@(\w+\b)(?(2)\\\}))/',
                '(?P<\3>[^\/\?]+)',
                $wild).'\/?$/'.$case.'um',$url,$args);
        foreach (array_keys($args) as $key) {
            if (preg_match('/^_\d+$/',$key)) {
                if (empty($args['*']))
                    $args['*']=$args[$key];
                else {
                    if (is_string($args['*']))
                        $args['*']=[$args['*']];
                    array_push($args['*'],$args[$key]);
                }
                unset($args[$key]);
            }
            elseif (is_numeric($key) && $key)
                unset($args[$key]);
        }
        return $args;
    }

    /**
    *   Match routes against incoming URI
    *   @return mixed
    **/
    function run() {
        $f3   = $this->f3;
        $req  = $this->req;
        $res  = $this->res;
        $verb   = $req->getMethod();
        $path   = $req->getPath();
        $query  = $req->getQueryStr() ?? '';
        $uri    = $req->getUri();
        $cli    = $req->isCli();
        $origin = $req->getHeader('Origin');
        $acrm   = $req->getHeader('Access-Control-Request-Method');
        $cors   = $f3->get('CORS');
        if (!$this->routes)
            // No routes defined
            user_error(self::E_Routes,E_USER_ERROR);
        // Match specific routes first
        $paths=[];
        foreach ($keys=array_keys($this->routes) as $key) {
            $p=preg_replace('/@\w+/','*@',$key);
            if (substr($p,-1)!='*')
                $p.='+';
            $paths[]=$p;
        }
        $vals=array_values($this->routes);
        array_multisort($paths,SORT_DESC,$keys,$vals);
        $this->routes=array_combine($keys,$vals);
        // Convert to BASE-relative URL
        $req_url=urldecode($path);
        $preflight=FALSE;
        if ($cors = ($origin && $cors['origin'])) {
            $res = $res
                ->withHeader('Access-Control-Allow-Origin', $cors['origin'])
                ->withHeader('Access-Control-Allow-Credentials', $f3->export($cors['credentials']));
            $preflight = (bool)$acrm;
        }
        $allowed=[];
        foreach ($this->routes as $pattern=>$routes) {
            $args=$this->mask($pattern,$req_url);
            if (!$args=$this->mask($pattern,$req_url))
                continue;
            ksort($args);
            $route=NULL;
            $ptr=$cli?self::REQ_CLI:$req->isAjax()+1;
            if (isset($routes[$ptr][$verb]) ||
                ($preflight && isset($routes[$ptr])) ||
                isset($routes[$ptr=0]))
                $route=$routes[$ptr];
            if (!$route)
                continue;
            if (isset($route[$verb]) && !$preflight) {
                if ($f3->get('REROUTE_TRAILING_SLASH')===TRUE &&
                    $verb=='GET' &&
                    preg_match('/.+\/$/',$path))
                    $this->reroute(substr($path,0,-1).
                        ($query?('?'.$query):''));
                $fullMiddleware = clone $this->globalMiddleware;
                $cur_route = $route[$verb];
                foreach ($cur_route->middleware->getQueue() as $mw) {
                    $fullMiddleware->add($mw);
                }
                list($handler,$ttl,$kbps,$alias)=$cur_route->getParams();
                // Capture values of route pattern tokens
                $f3->set('PARAMS',$args);
                // Save matching route
                $f3->set('ALIAS',$alias);
                $f3->set('PATTERN',$pattern);
                if ($cors && $cors['expose']) {
                    $res = $res->withHeader(
                        'Access-Control-Expose-Headers',
                        is_array($cors['expose']) ? implode(',', $cors['expose']) : $cors['expose']
                    );
                }
                // Process request
                $result=NULL;
                $body='';
                $now=microtime(TRUE);
                if (preg_match('/GET|HEAD/',$verb) && $ttl) {
                    // Only GET and HEAD requests are cacheable
                    $cached=$f3->cache_exists(
                        $hash=$f3->hash($verb.' '.
                            $uri).'.url',$data);
                    if ($cached) {
                        if (isset($headers['If-Modified-Since']) &&
                            strtotime($headers['If-Modified-Since'])+
                                $ttl>$now) {
                            $f3->status(304);
                            die;
                        }
                        // Retrieve from cache backend
                        list($headers,$body,$result)=$data;
                        if (!$cli)
                            array_walk($headers,'header');
                        $res = $f3->expire($req, $res, $cached[0]+$ttl-$now);
                    }
                    else
                        // Expire HTTP client-cached page
                        $res = $f3->expire($req, $res, $ttl);
                }
                else
                    $res = $f3->expire($req, $res, 0);
                if (!strlen($body)) {
                    ob_start();
                    $final = function () use ($args, $handler, $f3, $req, &$res) {
                        // Новый контракт контроллеров: ($req, $res, $params) 
                        return $f3->call($handler, [$req, $res, $args], 'beforeroute,afterroute');
                    };
                    $result = $fullMiddleware->dispatch($req, $res, $args, $final);
                    $body   = ob_get_clean();
                    if (isset($cache) && !error_get_last()) {
                        // Save to cache backend
                        $f3->cache_set($hash,[
                            // Remove cookies
                            preg_grep('/Set-Cookie\:/',headers_list(),
                                PREG_GREP_INVERT),$body,$result],$ttl);
                    }
                }
                $f3->set('RESPONSE', $body);
                if (!$f3->get('QUIET')) {
                    if ($kbps) {
                        // Если нужен троттлинг — выводим частями
                        $buffer = '';
                        $ctr=0; $now=microtime(true);
                        foreach (str_split($body,1024) as $part) {
                            $ctr; $buffer .= $part;
                            if ($ctr/$kbps > ($elapsed=microtime(true)-$now) && !connection_aborted()) {
                                usleep((int)round(1e6*($ctr/$kbps-$elapsed)));
                            }
                        }
                        $res = $res->withBody($buffer);
                    } else {
                        $res = $res->withBody($body);
                    }

                }
                $this->res = $res;
                $this->res->send($cli);      
        
                if ($result || $verb!='OPTIONS')
                    return $result;
            }
            $allowed=array_merge($allowed,array_keys($route));
        }
        if (!$allowed){
            // URL doesn't match any route
            $f3->error(404);
        } elseif (!$cli) {
            if (!preg_grep('/Allow:/',$headers_send=headers_list()))
                // Unhandled HTTP method
                $res = $res->withHeader('Allow', implode(',', array_unique($allowed)));
            if ($cors) {
                $res = $res->withHeader('Access-Control-Allow-Methods', 'OPTIONS,'.implode(',', $allowed));
                if ($cors['headers']) {
                    $res = $res->withHeader('Access-Control-Allow-Headers',
                        is_array($cors['headers']) ? implode(',', $cors['headers']) : $cors['headers']);
                }
                if ($cors['ttl']) {
                    $res = $res->withHeader('Access-Control-Max-Age', (string)$cors['ttl']);
                }
            }
            if ($verb!='OPTIONS')
                $f3->error(405);
        }
        return FALSE;
    }

    /**
    *   Return TRUE if IPv4 address exists in DNSBL
    *   @return bool
    *   @param $ip string
    **/
    function blacklisted($ip) {
        $f3 = $this->f3;
        $exempt = $f3->get('EXEMPT');
        $dnsbl = $f3->get('DNSBL');
        if ($dnsbl &&
            !in_array($ip,
                is_array($exempt)?
                    $exempt:
                    $f3->split($exempt))) {
            // Reverse IPv4 dotted quad
            $rev=implode('.',array_reverse(explode('.',$ip)));
            foreach (is_array($dnsbl)?
                $dnsbl:
                $f3->split($dnsbl) as $server)
                // DNSBL lookup
                if (checkdnsrr($rev.'.'.$server,'A'))
                    return TRUE;
        }
        return FALSE;
    }

    function addOnReroute(callable $handler){
        if(!empty($this->hive['ONREROUTE']))
            user_error(self::E_Onreroute,E_USER_ERROR);
        $this->hive['ONREROUTE'] = $handler;
    }
}