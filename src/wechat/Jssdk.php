<?php
    declare (strict_types=1);

    namespace mark\wechat;

    use think\facade\Cache;

    final class Jssdk
    {

        private $appId;
        private $appSecret;
        private $jsapi_ticket_key;

        public function __construct($appId, $appSecret)
        {
            $this->appId = $appId;
            $this->appSecret = $appSecret;

            $this->jsapi_ticket_key = md5('jsapi_ticket.php');
        }

        /**
         * 获取SignPackage
         * @return array
         */
        public function getSignPackage()
        {
            $jsapiTicket = $this->getJsApiTicket();

            // 注意 URL 一定要动态获取，不能 hardcode.
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $url = "$protocol$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

            $timestamp = time();
            $nonceStr = $this->createNonceStr();

            // 这里参数的顺序要按照 key 值 ASCII 码升序排序
            $string = "jsapi_ticket=" . $jsapiTicket . "&noncestr=" . $nonceStr . "&timestamp=" . $timestamp . "&url=" . $url;

            $signature = sha1($string);

            return array(
                "appId" => $this->appId,
                "nonceStr" => $nonceStr,
                "timestamp" => $timestamp,
                "url" => $url,
                "signature" => $signature,
                "rawString" => $string
            );
        }

        /**
         * 生成签名的随机串
         * @param int $length
         * @return string
         */
        private function createNonceStr($length = 16)
        {
            $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
            $str = "";
            for ($i = 0; $i < $length; $i++) {
                $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
            }

            return $str;
        }

        /**
         *获取JsApiTicket
         * @return mixed
         */
        private function getJsApiTicket()
        {
            // jsapi_ticket 应该全局存储与更新，以下代码以写入到文件中做示例
            // $data = json_decode($this->get_php_file("jsapi_ticket.php"));
            if (Cache::has($this->jsapi_ticket_key)) {
                $data = json_decode(Cache::get($this->jsapi_ticket_key), true);
            }
            $ticket = '';
            if (!empty($data) && isset($data["jsapi_ticket"]) && isset($data["expire_time"]) && $data["expire_time"] > time()) {
                $ticket = $data["jsapi_ticket"];
            } else {
                $accessToken = $this->getAccessToken();
                // 如果是企业号用以下 URL 获取 ticket
                // $url = "https://qyapi.weixin.qq.com/cgi-bin/get_jsapi_ticket?access_token=$accessToken";
                $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=" . $accessToken;
                $res = json_decode($this->httpGet($url), true);
                if (!empty($res) && isset($res["ticket"]) && !empty($res["ticket"])) {
                    $data["expire_time"] = time() + 7000;
                    $ticket = $data["jsapi_ticket"] = $res["ticket"];
                    // $this->set_php_file("jsapi_ticket.php", json_encode($data));
                    Cache::set($this->jsapi_ticket_key, json_encode($data, JSON_UNESCAPED_UNICODE));
                }
            }
            return $ticket;
        }

        /**
         * 获取AccessToken
         * @return mixed
         */
        private function getAccessToken()
        {
            // access_token 应该全局存储与更新，以下代码以写入到文件中做示例
            // $data = json_decode($this->get_php_file($this->jsapi_ticket_key));
            $data = null;
            if (Cache::has($this->jsapi_ticket_key)) {
                $data = json_decode(Cache::get($this->jsapi_ticket_key), true);
            }
            $access_token = '';
            if (!empty($data) && isset($data["access_token"]) && isset($data["expire_time"]) && $data["expire_time"] > time()) {
                $access_token = $data["access_token"];
            } else {
                // 如果是企业号用以下URL获取access_token
                // $url = "https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid=$this->appId&corpsecret=$this->appSecret";
                $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . $this->appId . "&secret=" . $this->appSecret;
                $res = json_decode($this->httpGet($url), true);
                if (!empty($res) && isset($res["access_token"])) {
                    $data["expire_time"] = time() + 7000;
                    $data["access_token"] = $access_token = $res["access_token"];
                    // $this->set_php_file($this->jsapi_ticket_key, json_encode($data));
                    Cache::set($this->jsapi_ticket_key, json_encode($data));
                }
            }

            return $access_token;
        }

        /**
         * @param $url
         * @return bool|string
         */
        private function httpGet($url)
        {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_TIMEOUT, 500);
            // 为保证第三方服务器与微信服务器之间数据传输的安全性，所有微信接口采用https方式调用，必须使用下面2行代码打开ssl安全校验。
            // 如果在部署过程中代码在此处验证失败，请到 http://curl.haxx.se/ca/cacert.pem 下载新的证书判别文件。
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_URL, $url);

            $res = curl_exec($curl);
            curl_close($curl);

            return $res;
        }

        /**
         * 获取缓存文件
         * @param $filename
         * @return string
         * @deprecated
         */
        private function get_php_file($filename)
        {
            return trim(substr(file_get_contents($filename), 15));
        }

        /**
         * 设置缓存文件
         * @param $filename
         * @param $content
         * @deprecated
         */
        private function set_php_file($filename, $content)
        {
            $fp = fopen($filename, "w");
            fwrite($fp, "<?php exit();?>" . $content);
            fclose($fp);
        }

    }

