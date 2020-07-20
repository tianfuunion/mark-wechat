<?php

    declare (strict_types=1);

    namespace mark\wechat\wxpay;

    use mark\wechat\pay\WxPayApi;
    use mark\wechat\pay\WxPayBizPayUrl;
    use mark\wechat\pay\WxPayUnifiedOrder;

    /**
     *
     * 刷卡支付实现类
     * @author widyhu
     *
     */
    class NativePay
    {
        /**
         *
         * 生成扫描支付URL,模式一
         * @param BizPayUrlInput $bizUrlInfo
         */
        public function GetPrePayUrl($productId)
        {
            $biz = new WxPayBizPayUrl();
            $biz->SetProduct_id($productId);
            try {
                $config = new WxPayConfig(
                    Config('auth.stores.wechat.appid'),
                    Config('auth.stores.wechat.merchantid'),
                    Config('auth.stores.wechat.key'),
                    Config('auth.stores.wechat.secret'),
                    config_path() . '/cert/apiclient_cert.pem',
                    config_path() . '/cert/apiclient_key.pem'
                );
                $values = WxpayApi::bizpayurl($config, $biz);
                return "weixin://wxpay/bizpayurl?" . $this->ToUrlParams($values);
            } catch (\Exception $e) {
                // Log::ERROR(json_encode($e));
                return "weixin://wxpay/bizpayurl";
            }
        }

        /**
         *
         * 参数数组转换为url参数
         * @param array $urlObj
         */
        private function ToUrlParams($urlObj)
        {
            $buff = "";
            foreach ($urlObj as $k => $v) {
                $buff .= $k . "=" . $v . "&";
            }

            $buff = trim($buff, "&");
            return $buff;
        }

        /**
         *
         * 生成直接支付url，支付url有效期为2小时,模式二
         * @param WxPayUnifiedOrder $input
         */
        public function GetPayUrl($input)
        {
            if ($input->GetTrade_type() == "NATIVE") {
                try {
                    $config = new WxPayConfig(
                        Config('auth.stores.wechat.appid'),
                        Config('auth.stores.wechat.merchantid'),
                        Config('auth.stores.wechat.key'),
                        Config('auth.stores.wechat.secret'),
                        config_path() . '/cert/apiclient_cert.pem',
                        config_path() . '/cert/apiclient_key.pem'
                    );
                    $result = WxPayApi::unifiedOrder($config, $input);
                    return $result;
                } catch (\Exception $e) {
                    // Log::ERROR(json_encode($e));
                }
            }
            return false;
        }
    }