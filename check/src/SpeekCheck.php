<?php
namespace SpeekCheck;
/**
 * 极验行为式验证类
 *
 * @author Hnb 20190402
 */
class SpeekCheck
{
    /**
     * 版本
     */
    const GT_SDK_VERSION = 'php_3.0.0';
    /**
     * id
     *
     * @var string
     */
    protected $captchaId =  'b844a01451e1e7cf49dc66903ef9c47f';
    /**
     * key
     *
     * @var string
     */
    protected $privateKey = 'bd141ab17fbd24f1e36e180371617b18';

    /**
     * 连接时间
     *
     * @var integer
     */
    public static $connectTimeout = 1;
    /**
     * socket连接时间
     *
     * @var integer
     */
    public static $socketTimeout  = 1;

    /**
     * 回调
     *
     * @var [type]
     */
    private $response;

    /**
     * 判断极验服务器是否down机
     *
     * @param array $data
     * @return int
     */
    public function preProcess($param, $newCaptcha=1) {
        $data = array('gt'=>$this->captchaId,
                     'new_captcha'=>$newCaptcha
                );
        $data = array_merge($data,$param);
        $query = http_build_query($data);
        $url = "http://api.geetest.com/register.php?" . $query;
        $challenge = $this->sendRequest($url);
        if (strlen($challenge) != 32) {
            $this->failbackProcess();
            return 0;
        }
        $this->successProcess($challenge);
        return 1;
    }

    /**
     * @param $challenge
     */
    private function successProcess($challenge) {
        $challenge      = md5($challenge . $this->privateKey);
        $result         = array(
            'success'   => 1,
            'gt'        => $this->captchaId,
            'challenge' => $challenge,
            'new_captcha'=>1
        );
        $this->response = $result;
    }

    /**
     *
     */
    private function failbackProcess() {
        $rnd1           = md5(rand(0, 100));
        $rnd2           = md5(rand(0, 100));
        $challenge      = $rnd1 . substr($rnd2, 0, 2);
        $result         = array(
            'success'   => 0,
            'gt'        => $this->captchaId,
            'challenge' => $challenge,
            'new_captcha'=>1
        );
        $this->response = $result;
    }

    /**
     * @return mixed
     */
    public function getResponseStr() {
        return json_encode($this->response);
    }

    /**
     * 返回数组方便扩展
     *
     * @return mixed
     */
    public function getResponse() {
        return $this->response;
    }

    /**
     * 正常模式获取验证结果
     *
     * @param string $challenge
     * @param string $validate
     * @param string $seccode
     * @param array $param
     * @return int
     */
    public function successValidate($challenge, $validate, $seccode,$param, $json_format=1) {
        if (!$this->checkValidate($challenge, $validate)) {
            return 0;
        }
        $query = array(
            "seccode"       => $seccode,
            "timestamp"     =>time(),
            "challenge"     =>$challenge,
            "captchaid"     =>$this->captchaId,
            "json_format"   =>$json_format,
            "sdk"           => self::GT_SDK_VERSION
        );
        $query = array_merge($query,$param);
        $url = "http://api.geetest.com/validate.php";
        $codevalidate = $this->postRequest($url, $query);
        $obj = json_decode($codevalidate,true);
        if ($obj === false){
            return 0;
        }
        if ($obj['seccode'] == md5($seccode)) {
            return 1;
        } else {
            return 0;
        }
    }

    /**
     * 宕机模式获取验证结果
     *
     * @param $challenge
     * @param $validate
     * @param $seccode
     * @return int
     */
    public function failValidate($challenge, $validate, $seccode) {
        if(md5($challenge) == $validate){
            return 1;
        }else{
            return 0;
        }
    }

    /**
     * @param $challenge
     * @param $validate
     * @return bool
     */
    private function checkValidate($challenge, $validate) {
        if (strlen($validate) != 32) {
            return false;
        }
        if (md5($this->privateKey . 'geetest' . $challenge) != $validate) {
            return false;
        }

        return true;
    }

    /**
     * GET 请求
     *
     * @param $url
     * @return mixed|string
     */
    private function sendRequest($url) {

        if (function_exists('curl_exec')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::$connectTimeout);
            curl_setopt($ch, CURLOPT_TIMEOUT, self::$socketTimeout);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $data = curl_exec($ch);
            $curl_errno = curl_errno($ch);
            curl_close($ch);
            if ($curl_errno >0) {
                return 0;
            }else{
                return $data;
            }
        } else {
            $opts    = array(
                'http' => array(
                    'method'  => "GET",
                    'timeout' => self::$connectTimeout + self::$socketTimeout,
                )
            );
            $context = stream_context_create($opts);
            $data    = @file_get_contents($url, false, $context);
            if($data){ 
                return $data;
            }else{ 
                return 0;
            } 
        }
    }

    /**
     *
     * @param       $url
     * @param array $postdata
     * @return mixed|string
     */
    private function postRequest($url, $postdata = '') {
        if (!$postdata) {
            return false;
        }

        $data = http_build_query($postdata);
        if (function_exists('curl_exec')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::$connectTimeout);
            curl_setopt($ch, CURLOPT_TIMEOUT, self::$socketTimeout);

            //不可能执行到的代码
            if (!$postdata) {
                curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
            } else {
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
            $data = curl_exec($ch);

            if (curl_errno($ch)) {
                $err = sprintf("curl[%s] error[%s]", $url, curl_errno($ch) . ':' . curl_error($ch));
                $this->triggerError($err);
            }

            curl_close($ch);
        } else {
            if ($postdata) {
                $opts    = array(
                    'http' => array(
                        'method'  => 'POST',
                        'header'  => "Content-type: application/x-www-form-urlencoded\r\n" . "Content-Length: " . strlen($data) . "\r\n",
                        'content' => $data,
                        'timeout' => self::$connectTimeout + self::$socketTimeout
                    )
                );
                $context = stream_context_create($opts);
                $data    = file_get_contents($url, false, $context);
            }
        }

        return $data;
    }


    
    /**
     * @param $err
     */
    private function triggerError($err) {
        trigger_error($err);
    }

    public function getIp(){
        if( !empty($_SERVER["HTTP_ALI_CDN_REAL_IP"]) ){
           $cip = $_SERVER["HTTP_ALI_CDN_REAL_IP"];
        }elseif(!empty($_SERVER["HTTP_CLIENT_IP"])){
           $cip = $_SERVER["HTTP_CLIENT_IP"];
        }else if(!empty($_SERVER["HTTP_X_FORWARDED_FOR"])){
           $cip = $_SERVER["HTTP_X_FORWARDED_FOR"];
        }else if(!empty($_SERVER["REMOTE_ADDR"])){
           $cip = $_SERVER["REMOTE_ADDR"];
        }else{
           $cip = '';
        }
        preg_match("/[\d\.]{7,15}/", $cip, $cips);
        $cip = isset($cips[0]) ? $cips[0] : 'unknown';
        unset($cips);
        return $cip;
    }

    public function jsonOutput($data="",$is_success=true){
        header("Content-Type: application/json; charset=utf-8");
        //@header('Access-Control-Allow-Origin:*'); //*代表可访问的地址，可以设置指定域名
        header('Access-Control-Allow-Methods:POST,GET');
        exit(json_encode(array("success"=>$is_success,"data"=>$data)));
    }
}
