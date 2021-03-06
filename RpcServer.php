<?php
class RpcServer {
    protected $serv = null;

    public function __construct($host, $port, $path) {
        // 创建一个tcp socket服务
        $this->serv = stream_socket_server("tcp://{$host}:{$port}", $errno, $errstr,STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);
        if (!$this->serv) {
            exit("{$errno} : {$errstr} \n");
        }
        // 阻塞模式
        stream_set_blocking($this->serv, 0);

        // 判断我们的RPC服务目录是否存在
        $realPath = realpath(__DIR__ . $path);
        if ($realPath === false || !file_exists($realPath)) {
            exit("{$path} error \n");
        }

        $connections = [];
        $read = [];
        $write = null;
        $except = null;

        while(1) {

            // look for new connections
            if ($c = @stream_socket_accept($this->serv, empty($connections) ? -1 : 0, $peer)) {
                echo $peer.' connected'.PHP_EOL;
//                fwrite($c, 'Hello '.$peer.PHP_EOL);
                $connections[$peer] = $c;
            }

            // wait for any stream data
            $read = $connections;
            if (false === ($num_changed_streams = stream_select($read, $write, $except, 5))) {
                echo "stream_select() failed\n";
            } elseif ($num_changed_streams > 0) {
                foreach ($read as $c) {
                    $peer = stream_socket_get_name($c, true);
                    if (feof($c)) {
                        echo 'Connection closed '.$peer.PHP_EOL.PHP_EOL;
                        fclose($c);
                        unset($connections[$peer]);
                    } else {
//                        $contents = fread($c, 1024);
//                        echo $peer.': '.trim($contents).PHP_EOL;
//                        fwrite($c, 'Hello '.$peer.PHP_EOL);

                        $buff = '';
                        $content = '';
                        //读取请求数据直到遇到\r\n结束符
//                        while (!preg_match('#' . PHP_EOL . '#', $buff)) {
//                            $buff = fread($c, 1024);
//                            $content .= preg_replace('#' . PHP_EOL . '#', '', $buff);
//                        }
//                        echo $peer.': '.($content).PHP_EOL;
//                        fwrite($c, 'Hello '.$peer.PHP_EOL);

                        while (($buff = fread($c, 1024)) != '') {
                            $content .= $buff;
                        }
                        echo $peer.': '.preg_replace('#' . PHP_EOL . '#', '', $content).PHP_EOL;

                        //解析客户端发送过来的协议
                        $classRet = preg_match('/Rpc-Class:\s(.*);' . PHP_EOL . '/i', $content, $class);
                        $methodRet = preg_match('/Rpc-Method:\s(.*);' . PHP_EOL . '/i', $content, $method);
                        $paramsRet = preg_match('/Rpc-Params:\s(.*);' . PHP_EOL . '/i', $content, $params);

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
                                        fwrite($c, $data . PHP_EOL);
                                    } else {
                                        fwrite($c, 'error: method not exists' . PHP_EOL);
                                    }
                                } else {
                                    fwrite($c, 'error: class not exists' . PHP_EOL);
                                }
                            } else {
                                fwrite($c, 'error: file not exists' . PHP_EOL);
                            }
                        } else {
                            fwrite($c, 'error: class or method not exists' . PHP_EOL);
                        }
                    }
                }
            }

        }


//        $listen_reads = [$this->serv];
//        $listen_writes = [];
//        $listen_excepts = NULL;
//        echo "开启服务...\n";
//
//        while (true) {
//
//            $can_reads = $listen_reads;
//            $can_writes = $listen_writes;
//            $num_streams = @stream_select($can_reads, $can_writes, $listen_excepts, 0);
//
//            if ($num_streams) {  // 有信号
//                foreach($can_reads as &$sock) {
//                    if ($this->serv == $sock) {
//                        $client = stream_socket_accept($this->serv, 5);  //此时一定存在客户端连接，不会有超时的情况
//                        if ($client) {
//                            // 把客户端连接加入监听
//                            $listen_reads[] = $client;
//                            $listen_writes[] = $client;
//                        }
//                    } else {
//                        // 此时一定是可读的
//                        $buff = '';
//                        $data = '';
//                        //读取请求数据直到遇到\r\n结束符
//                        while (!preg_match('#' . PHP_EOL . '#', $buff)) {
//                            $buff = fread($sock, 1024);
//                            $data .= preg_replace('#' . PHP_EOL . '#', '', $buff);
//                        }
//
//                        // 读取到0个字符，说明客户端关闭
//                        if (strlen($data) == 0) {
//                            fclose($sock);
//                            // 从sock监听中移除
//                            $key = array_search($sock, $listen_reads);
//                            unset($listen_reads[$key]);
//                            $key = array_search($sock, $listen_writes);
//                            unset($listen_writes[$key]);
//                            echo "客户端关闭\n";
//                        } else {
//                            // 是否可写
//                            if (in_array($sock, $can_writes)){
//                                //解析客户端发送过来的协议
//                                $classRet = preg_match('/Rpc-Class:\s(.*);' . PHP_EOL . '/i', $buff, $class);
//                                $methodRet = preg_match('/Rpc-Method:\s(.*);' . PHP_EOL . '/i', $buff, $method);
//                                $paramsRet = preg_match('/Rpc-Params:\s(.*);' . PHP_EOL . '/i', $buff, $params);
//
//                                if($classRet && $methodRet) {
//
//                                    echo "执行了 - ".$class[1]."\\".$method[1]."\n";
//
//                                    $class = ucfirst($class[1]);
//                                    $method = $method[1];
//                                    $params = $params[1];
//                                    $file = $realPath . '/' . $class . '.php';
//                                    //判断文件是否存在，如果有，则引入文件
//                                    if(file_exists($file)) {
//                                        require_once $file;
//                                        if (class_exists($class)) {
//                                            //实例化类，并调用客户端指定的方法
//                                            $obj = new $class();
//                                            if (method_exists($class, $method)) {
//                                                //如果有参数，则传入指定参数
//                                                if (!$params) {
//                                                    $data = $obj->$method();
//                                                } else {
//                                                    $data = $obj->$method(json_decode($params, true));
//                                                }
//                                                //把运行后的结果返回给客户端
//                                                fwrite($sock, $data . PHP_EOL);
//                                            } else {
//                                                fwrite($sock, 'error: method not exists' . PHP_EOL);
//                                            }
//                                        } else {
//                                            fwrite($sock, 'error: class not exists' . PHP_EOL);
//                                        }
//                                    } else {
//                                        fwrite($sock, 'error: file not exists' . PHP_EOL);
//                                    }
//                                } else {
//                                    fwrite($sock, 'error: class or method not exists' . PHP_EOL);
//                                }
//                            }
//                            // 关闭客户端
//                            fclose($sock);
//                        }
//
//                    }
//                }
//            }
//
//        }
    }

    public function __destruct() {
        fclose($this->serv);
    }
}

new RpcServer('127.0.0.1', 8888, '/service');