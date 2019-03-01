<?php

class RpcClient {
    protected $urlInfo = array();
    protected $path = '';

    public function __construct($url, $path) {
        //解析URL
        $this->urlInfo = parse_url($url);
        if(!$this->urlInfo) {
            exit("{$url} error \n");
        }
        $this->path = $path;
    }

    public function __call($method, $params) {
        //创建一个客户端
        $client = stream_socket_client("tcp://{$this->urlInfo['host']}:{$this->urlInfo['port']}", $errno, $errstr);
        if (!$client) {
            exit("{$errno} : {$errstr} \n");
        }
        //传递调用的类名
        $class = $this->path;
        $proto = "Rpc-Class: {$class};" . PHP_EOL;
        //传递调用的方法名
        $proto .= "Rpc-Method: {$method};" . PHP_EOL;
        //传递方法的参数
        $params = json_encode($params);
        $proto .= "Rpc-Params: {$params};" . PHP_EOL;
        //向服务端发送我们自定义的协议数据
        fwrite($client, $proto);
        //读取服务端传来的数据
        $buff = '';
        $data = '';
        //读取请求数据直到遇到\r\n结束符
        while (!preg_match('#' . PHP_EOL . '#', $buff)) {
            $buff = fread($client, 1024);
            $data .= preg_replace('#' . PHP_EOL . '#', '', $buff);
        }
        //关闭客户端
        fclose($client);
        return $data;
    }
}

$url = [
    's1' => 'http://127.0.0.1:8888/',
    's2' => 'http://127.0.0.1:8989/'
];

$cli = new RpcClient($url['s1'], 'test');
echo $cli->hehe();
echo $cli->hehe2(array('name' => 'test', 'age' => 27));