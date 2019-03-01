<?php
class RpcServer {
    protected $serv = null;

    public function __construct($host, $port, $path) {
        //创建一个tcp socket服务
        $this->serv = stream_socket_server("tcp://{$host}:{$port}", $errno, $errstr);
        if (!$this->serv) {
            exit("{$errno} : {$errstr} \n");
        }
        //判断我们的RPC服务目录是否存在
        $realPath = realpath(__DIR__ . $path);
        if ($realPath === false || !file_exists($realPath)) {
            exit("{$path} error \n");
        }

        echo "开启服务...\n";

        while (true) {
            $client = stream_socket_accept($this->serv, -1);

            if ($client) {
                $buff = '';
                $data = '';
                //读取请求数据直到遇到\r\n结束符
                while (!preg_match('#' . PHP_EOL . '#', $buff)) {
                    $buff = fread($client, 1024);
                    $data .= preg_replace('#' . PHP_EOL . '#', '', $buff);
                }
                //解析客户端发送过来的协议
                $classRet = preg_match('/Rpc-Class:\s(.*);' . PHP_EOL . '/i', $buff, $class);
                $methodRet = preg_match('/Rpc-Method:\s(.*);' . PHP_EOL . '/i', $buff, $method);
                $paramsRet = preg_match('/Rpc-Params:\s(.*);' . PHP_EOL . '/i', $buff, $params);

                echo "执行了 - ".$class[1]."\\".$method[1]."\n";

                if($classRet && $methodRet) {
                    $class = ucfirst($class[1]);
                    $method = $method[1];
                    $params = $params[1];
                    $file = $realPath . '/' . $class . '.php';
                    //判断文件是否存在，如果有，则引入文件
                    if(file_exists($file)) {
                        require_once $file;
                        if (class_exists($class)) {
                            //实例化类，并调用客户端指定的方法
                            $obj = new $class();
                            if (method_exists($class, $method)) {
                                //如果有参数，则传入指定参数
                                if (!$params) {
                                    $data = $obj->$method();
                                } else {
                                    $data = $obj->$method(json_decode($params, true));
                                }
                                //把运行后的结果返回给客户端
                                fwrite($client, $data . PHP_EOL);
                            } else {
                                fwrite($client, 'error: method not exists' . PHP_EOL);
                            }
                        } else {
                            fwrite($client, 'error: class not exists' . PHP_EOL);
                        }
                    } else {
                        fwrite($client, 'error: file not exists' . PHP_EOL);
                    }
                } else {
                    fwrite($client, 'error: class or method not exists' . PHP_EOL);
                }
                //关闭客户端
                fclose($client);
            }
        }
    }

    public function __destruct() {
        fclose($this->serv);
    }
}

new RpcServer('127.0.0.1', 8888, '/service');