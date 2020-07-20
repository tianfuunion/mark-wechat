<?php

    declare (strict_types=1);

    namespace mark\wechat\notice;

    use think\facade\Cache;
    use think\facade\Request;
    use think\facade\Session;
    use mark\http\Curl;
    use mark\system\Os;
    use Exception;

    final class Notice
    {
        protected $appid;
        protected $appsecret;

        public function __construct($appid = null, $appsecret = null)
        {
            if (!empty($appid) && !empty($appsecret)) {
                $this->appid = $appid;
                $this->appsecret = $appsecret;
            } else {
                $this->appid = Config('auth.stores.wechat.appid');
                $this->appsecret = Config('auth.stores.wechat.secret');
            }
        }

        /**
         * 发送模板消息
         *
         * @param string $json_template
         *
         * @return mixed
         */
        public function send(string $json_template)
        {
            // 模板消息
            $url = 'https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=' . $this->access_token();
            $jsonResult = $this->curl_post($url, urldecode($json_template));
            // $jsonResult = $this->curl_post($url, $json_template);
            if (empty($jsonResult)) {
                return '';
            }
            $result = json_decode($jsonResult, true);
            // Log::info('Notice::Send()' . json_encode(urldecode($json_template), JSON_UNESCAPED_UNICODE));
            // Log::error('Notice::Result()' . json_encode($result, JSON_UNESCAPED_UNICODE));

            try {
                //若未登录则无法获取Uid，临时性固定写成12
                // Notice:send(Exception)SQLSTATE[23000]: Integrity constraint violation: 1048 Column "uid" cannot be null
                $post['uid'] = Session::get('uid', 12);

                // $post["wxid"] = $this->openid;
                $post['wxid'] = json_decode($json_template)->touser;
                // $post["chattype"] = $this->chattype;
                // $post["chattype"] = json_decode($json_template)->chattype;
                $post['msgid'] = $result['msgid'];
                $post['errcode'] = $result['errcode'];
                $post['errmsg'] = $result['errmsg'];
                $post['message'] = urldecode($json_template);
                $post['time'] = time();

                if ($result['errcode'] == 0) {
                    $post['status'] = 1;
                } else {
                    $post['status'] = 2;
                }
                // Db::name('message_template')->insertGetId($post);
            } catch (Exception $exception) {

            }

            return $result;
        }

        /**
         * 获取微信 Access_Token
         *
         * @return mixed|string
         */
        private function access_token()
        {
            // 获取access_token
            $token_url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=' . $this->appid . '&secret=' . $this->appsecret;
            if (Cache::has('access_token') && !empty(Cache::get('access_token'))) {
                return Cache::get('access_token');
            }

            $token = Curl::getInstance()->post($token_url)->toArray();
            if (!empty($token) && !empty($token['access_token'])) {
                Cache::set('access_token', $token['access_token'], 7000);
                return $token['access_token'];
            }

            $json_token = $this->curl_post($token_url);
            if (!empty($json_token)) {
                $token = json_decode($json_token, true);
                if (!empty($token) && !empty($token['access_token'])) {
                    Cache::set('access_token', $token['access_token'], 7000);

                    return $token['access_token'];
                }
            }

            return '';
        }

        /**
         * @param       $url
         * @param array $data
         *
         * @return mixed
         * curl请求
         */
        private function curl_post($url, $data = array())
        {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            // POST数据
            curl_setopt($ch, CURLOPT_POST, 1);
            // 把post的变量加上
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            $output = curl_exec($ch);
            curl_close($ch);

            return $output;
        }

        /**
         * 运行预警
         *
         * @param string $wxid 微信ID
         * @param string $account 账号
         * @param string $status 状态
         * @param string $content 内容
         * @param string $url Url
         * @param int $time 时间
         * @param null $first
         * @param null $remark 备注
         *
         * @return mixed
         */
        public static function runevent(
            string $wxid, string $account, string $status, string $content, $url = '', $time = 0, $first = null, $remark = null
        )
        {
            // Log::info('Notice::RunEvent()' . json_encode(func_get_args(), JSON_UNESCAPED_UNICODE));
            $notice = new Notice(Config('auth.stores.wechat.appid'), Config('auth.stores.wechat.secret'));
            if ($time == 0) {
                $time = time();
            }

            return $notice->send(self::eventMsg($wxid, $account, $status, $content, $url, $time, $first, $remark));
        }

        /**
         * 运行预警消息内容
         *
         * @param string $wxid
         * @param string $account
         * @param string $status
         * @param string $content
         * @param string $url
         * @param int $time
         * @param string $first
         * @param string $remark
         *
         * @return false|string
         */
        private static function eventMsg(string $wxid, string $account, string $status, string $content, $url = '', $time = 0, $first = '', $remark = '')
        {

            if (empty($account)) {
                $account = '佚名';
            }
            if (empty($first)) {
                $first = '运行预警';
            }
            if (empty($url)) {
                $url = Request::url(true);
            }
            if ($time === 0) {
                $time = time();
            }
            if (empty($remark)) {
                $remark = Os::getIpvs();
            }
            $template = array(
                'touser' => $wxid,
                'template_id' => 'iqX9h3RYvgia5i4g4ovvdPG4bGFDViEBypIFrozOhTI',
                'url' => $url,
                'topcolor' => '#FF0000',
                'data' => array(
                    'first' => array('value' => urlencode($first), 'color' => '#DC3545'),
                    'keyword1' => array('value' => urlencode($account), 'color' => '#FD7E14'),
                    'keyword2' => array('value' => urlencode($status), 'color' => '#0288D1'),
                    'keyword3' => array('value' => urlencode($content), 'color' => '#343A40'),
                    'keyword4' => array('value' => date('Y-m-d H:i:s', $time)),
                    'remark' => array('value' => urlencode($remark))),
                'chattype' => '运行报警'
            );

            // return json_encode($template, JSON_UNESCAPED_UNICODE);
            return json_encode($template);
        }

    }