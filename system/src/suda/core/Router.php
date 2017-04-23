<?php
namespace suda\core;

use suda\tool\Command;
use suda\tool\Json;
use suda\tool\ArrayHelper;

// TODO:路由强化
// TODO:路由模块化（添加命名空间）

class Router
{
    protected $mapper;
    protected $matchs=[];
    protected $types;
    protected static $urltype=['int'=>'\d+','string'=>'[^\/]+','url'=>'.+'];
    protected static $router=null;
    protected $routers=[];
    
    private function __construct()
    {
        Hook::listen('system:404', 'Router::error404');
        Hook::listen('Router:dispatch::error', 'Router::error404');
        self::loadModulesRouter();
    }

    public static function getInstance()
    {
        if (is_null(self::$router)) {
            self::$router=new Router;
        }
        return self::$router;
    }

    public function load(string $module)
    {
        $simple_routers=[];
        $admin_routers=[];
        $module_dir=Application::getModuleDir($module);
        _D()->trace(_T('启用模块：%s %s',$module,Application::getModuleFillName($module)));
        $prefix= conf('module-prefix.'.$module, null);
        $admin_prefix='';
        if (is_array($prefix)) {
            $admin_prefix=$prefix['admin'] ?? array_shift($prefix);
            $prefix=$prefix['simple'] ?? array_shift($prefix);
        }
        if (Storage::exist(MODULES_DIR.'/'.$module_dir.'/resource/config/router.json')) {
            $simple_routers= self::loadModuleJson($module, MODULES_DIR.'/'.$module_dir.'/resource/config/router.json');
            array_walk($simple_routers, function (&$router) use ($module, $prefix) {
                if (!is_null($prefix)) {
                    $router['visit']=$prefix.$router['visit'];
                }
                $router['visit']='/'.trim($router['visit'], '/');
                $router['module']=$module;
            });
        }

        // 加载后台路由
        if (Storage::exist(MODULES_DIR.'/'.$module_dir.'/resource/config/router_admin.json')) {
            $admin_routers= self::loadModuleJson($module, MODULES_DIR.'/'.$module_dir.'/resource/config/router_admin.json');
            array_walk($admin_routers, function (&$router) use ($module, $admin_prefix) {
                $prefix= conf('app.admin', '/admin');
                if (!is_null($admin_prefix)) {
                    $prefix = $prefix . $admin_prefix;
                }
                $router['visit']=$prefix.$router['visit'];
                $router['visit']='/'.trim($router['visit'], '/');
                $router['module']=$module;
            });
        }
       
        $this->routers=array_merge($this->routers, $admin_routers, $simple_routers);
    }
    
    protected function loadModuleJson(string $module, string $jsonfile)
    {
        $routers=Json::loadFile($jsonfile);
        $router=[];
        foreach ($routers as $name => $value) {
            $router[$module.':'.$name]=$value;
        }
        return $router;
    }

    
    protected function loadFile()
    {
        $this->routers=require TEMP_DIR.'/router.cache.php';
        $this->matchs=require TEMP_DIR.'/matchs.cache.php';
    }
    protected function saveFile()
    {
        ArrayHelper::export(TEMP_DIR.'/router.cache.php', '_router', $this->routers);
        ArrayHelper::export(TEMP_DIR.'/matchs.cache.php', '_matchs', $this->matchs);
    }
    protected function loadModulesRouter()
    {
        if (conf('debug')) {
            $modules=Config::get('app.modules', []);
            
            foreach ($modules as $module) {
                self::load($module);
            }
            self::buildRouterMap();
            // 缓存路由信息
            self::saveFile();
        } else {
            // 提取路由信息
            self::loadFile();
        }
    }

    public function watch(string $name, string $url)
    {
        $this->matchs[$name]=self::buildMatch($name, $url);
    }
    protected function matchRouterMap()
    {
        $request=Request::getInstance();
        foreach ($this->matchs as $name=>$preg) {
            // _D()->d('url:'.$request->url().'; preg:'.'/^'.$preg.'$/');
            if (preg_match('/^'.$preg.'$/', $request->url(), $match)) {
                // 检验接口参数
                if (isset($this->routers[$name]['method']) && count($this->routers[$name]['method'])>0) {
                    array_walk($this->routers[$name]['method'], function ($value) {
                        return strtoupper($value);
                    });
                    // 方法不匹配
                    if (!in_array(strtoupper($request->method()), $this->routers[$name]['method'])) {
                        continue;
                    }
                }
                
                array_shift($match);
                if (count($match)>0) {
                    foreach ($this->types[$name] as $param_name =>$type) {
                        $value=array_shift($match);
                        if ($type==='int') {
                            $value=intval($value);
                        } else {
                            $value=urldecode($value);
                        }
                        // 填充$_GET
                        $_GET[$param_name]=$value;
                        $request->set($param_name, $value);
                    }
                }
                return $name;
            }
        }
        return false;
    }

    protected function buildRouterMap()
    {
        foreach ($this->routers as $name => $router) {
            self::watch($name, $router['visit']);
        }
    }

    public static function visit(array $method, string $url, string $router, string $tag=null, bool $ob =true, bool $admin=false, bool $json=false)
    {
        $params=self::getParams($url);
        if (!preg_match('/^(.+?)@(.+?)$/', $router, $matchs)) {
            return false;
        }
        $return=[];
        // 解析变量
        list($router, $class_short, $module)=$matchs;
        // 激活模块
        Application::activeModule($module);
        $module_dir=Application::getModuleDir($module);
        // 路由位置
        $router_file=MODULES_DIR.'/'.$module_dir.'/resource/config/router'.($admin?'_admin':'').'.json';
        $namespace=conf('module.namespace', conf('app.namespace'));
        // 类名
        $class=$namespace.'\\response\\'.$class_short;
        $params_str=array();
        $params_mark='';
        $value_get='array(';
        foreach ($params as $param_name=>$param_type) {
            $params_str[]="\${$param_name}=\$request->get()->{$param_name}(".(preg_match('/int/i', $param_type)?'0':"'{$param_name}'").')';
            $params_mark.="{$param_name}:{$param_type},";
            $value_get.="'{$param_name}'=>\$request->get()->{$param_name}(".(preg_match('/int/i', $param_type)?'0':"'{$param_name}'")."),";
        }
        $value_get.=')';
        $params_str=implode(";\r\n\t\t", $params_str);
        
        $pos=strrpos($class, '\\');
        $class_namespace=substr($class, 0, $pos);
        $class_name=substr($class, $pos+1);
        $class_path=MODULES_DIR.'/'.$module_dir.'/src/'.$class_namespace;
        $class_file=$class_path.'/'.$class_name.'.php';
        $return['class']=$class_file;
        $template_name=self::createTplName($class_short);
        $template_file=MODULES_DIR.'/'.$module_dir.'/resource/template/default/'.$template_name.'.tpl.html';
        $class_template= Storage::get(SYS_RES.($json?'/class_json.php':($ob?'/class_template.php':'/class_obcache.php')));
        $tagname=strtolower(is_null($tag)?preg_replace('/[\\\\]+/', '_', $class_short):$tag);
        $parent=$admin? conf('module.response.admin', conf('app.response.admin', 'suda\\core\\Response')): conf('module.response.normal', conf('app.response.normal', 'suda\\core\\Response'));
        $class_template=str_replace(
            [
                '__class_namespace__',
                '__class_name__',
                '__params_str__',
                '__module__',
                '__template_name__',
                '__create_url__',
                '__template_path__',
                '__router_name__',
                '__param_mark__',
                '__param_array__',
                '__methods__',
                '__parent__'
            ],
            [
                $class_namespace,
                $class_name,
                $params_str,
                $module,
                $template_name,
                $url,
                'default:'.$template_name.'.tpl.html',
                $tagname,
                $params_mark,
                $value_get,
                count($method)>0?implode(',', $method):'all',
                $parent,
            ], $class_template);
        $template=Storage::get(SYS_RES.'/view_template.html');
        $template=str_replace('__create_url__', $url, $template);
        // 写入Class
        Storage::path($class_path);
        if (Storage::exist($class_file)) {
            Storage::move($class_file, $class_file.'.bk');
        }
        Storage::put($class_file, $class_template);
        
        if (!$json ||  !$ob) {
            // 写入模板
            if (Storage::exist($template_file)) {
                Storage::move($template_file, $template_file.'.bk');
            }
            Storage::path(dirname($template_file));
            Storage::put($template_file, $template);
            $return['template']=$template_file;
        }


        // 更新路由
        Storage::path(dirname($router_file));
        if (Storage::exist($router_file)) {
            $json=Json::loadFile($router_file);
        } else {
            $json=[];
        }
        $item=array(
            'class'=>$class,
            'visit'=>$url,
        );
        if (!$ob) {
            $item['ob']=false;
        }
        if (count($method)) {
            $item['method']=$method;
        }
        $json[$tagname]=$item;
        Json::saveFile($router_file, $json);
        
        return $return;
    }
    protected static function createTplName(string $name)
    {
        $name=strtolower(preg_replace('/([A-Z])/', '_$1', $name));
        $names=explode('\\', $name);
        foreach ($names as $index=>$piece) {
            $names[$index]=trim($piece, '_');
        }
        return  implode('/', $names);
    }
    public static function getParams(string $url)
    {
        $urltype=self::$urltype;
        $types=array();
        $url=preg_replace('/([\/\.\\\\\+\*\[\^\]\$\(\)\=\!\<\>\-])/', '\\\\$1', $url);
        $url=preg_replace_callback('/\{(?:(\w+)(?::(\w+))?)\}/', function ($match) use (&$types, $urltype) {
            $param_name=$match[1]!==''?$match[1]:count($types);
            $param_type= $match[2] ?? 'string';
            $types[$param_name]=$param_type;
        }, $url);
        return $types;
    }

    protected function buildMatch(string $name, string $url)
    {
        $types=&$this->types;
        $urltype=self::$urltype;
        $url=preg_replace('/([\/\.\\\\\+\*\(\^\)\?\$\=\!\<\>\-])/', '\\\\$1', $url);
        $url=preg_replace('/\[(\S+)\]/', '(?:$1)?', $url);
        $url=preg_replace_callback('/\{(?:(\w+)(?::(\w+))?)\}([?])?/', function ($match) use ($name, &$types, $urltype) {
            $size=isset($types[$name])?count($types[$name]):0;
            $param_name=$match[1]!==''?$match[1]:$size;
            $param_type=  $match[2] ?? 'string';
            $ignore=isset($match[3])?'?':'';
            $types[$name][$param_name]=$param_type;
            if (isset($urltype[$param_type])) {
                return '('.$urltype[$param_type].')'.$ignore;
            } else {
                return '(.+)'.$ignore;
            }
        }, $url);
        return $url;
    }

    public function buildUrl(string $name, array $values=[])
    {
        preg_match('/^(?:(.+?)[:])?(.+)$/', $name, $match);
        $name=$match[2];
        $module=$match[1]?:Application::getActiveModule();
        $name=$module.':'.$name;
        $url= '';
        if (isset($this->routers[$name])) {
            // 路由存在
            $url.=preg_replace('/[?|]/', '\\\1', $this->routers[$name]['visit']);
            $url=preg_replace_callback('/\{(?:(\w+)(?::(\w+))?)\}/', function ($match) use ($name, & $values) {
                $param_name=$match[1];
                $param_type= $match[2] ?? 'url';
                if (isset($values[$param_name])) {
                    if ($param_type==='int') {
                        $val= intval($values[$param_name]);
                    }
                    $val=$values[$param_name];
                    unset($values[$param_name]);
                    return $val;
                } else {
                    return '';
                }
            }, preg_replace('/\[(.+?)\]/', '$1', $url));
        } else {
            return '/?undefine_router';
        }
        if (count($values)) {
            return $url.'?'.http_build_query($values, 'v', '&', PHP_QUERY_RFC3986);
        }
        return Request::getInstance()->baseUrl(). ltrim($url, '/');
    }


    public function dispatch()
    {
        self::buildRouterMap();
        // Hook前置路由（自定义过滤器|自定义路由）
        if (Hook::execIf('Router:dispatch::before', [Request::getInstance()], true)) {
            if (($router_name=self::matchRouterMap())!==false) {
                Response::setName($router_name);
                self::runRouter($this->routers[$router_name]);
            } else {
                Hook::exec('system:404');
            }
        } else {
            Hook::execTail('Router:dispatch::error');
        }
    }
    protected static function runRouter(array $router)
    {
        if (! (isset($router['ob']) && $router['ob']===false)) {
            Response::obStart();
        }
        (new \suda\tool\Command(Config::get('app.application', 'suda\\core\\Application').'::activeModule'))->exec([$router['module']]);
        if ((new \suda\tool\Command($router['class'].'->onPreTest'))->exec([$router])) {
            (new \suda\tool\Command($router['class'].'->onRequest'))->exec([Request::getInstance()]);
        } else {
            (new \suda\tool\Command($router['class'].'->onPreTestError'))->exec([$router]);
        }
    }

    public static function error404()
    {
        $render=new class extends Response {
            public   function onRequest(Request $request)
            {
                self::$name='404page';
                $this->state(404);
                $this->display('suda:error404', ['path'=>$request->url()]);
            }
        };
        $render->onRequest(Request::getInstance());
    }
}
