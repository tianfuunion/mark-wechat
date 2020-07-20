<?php
    declare (strict_types=1);

    use mark\wechat\pay\WxPayApi;
    use mark\wechat\pay\WxPayConfigInterface;
    use mark\wechat\pay\WxPayNotify;
    use mark\wechat\pay\WxPayNotifyResults;
    use mark\wechat\pay\WxPayUnifiedOrder;
    use mark\wechat\wxpay\WxPayConfig;

    // 初始化日志
    $logHandler = new CLogFileHandler("../logs/" . date('Y-m-d') . '.log');
    $log = Log::Init($logHandler, 15);

    class NativeNotifyCallBack extends WxPayNotify
    {
        public function unifiedorder($openId, $product_id)
        {
            //统一下单
            $config = new WxPayConfig(
                Config('auth.stores.wechat.appid'),
                Config('auth.stores.wechat.merchantid'),
                Config('auth.stores.wechat.key'),
                Config('auth.stores.wechat.secret'),
                config_path() . '/cert/apiclient_cert.pem',
                config_path() . '/cert/apiclient_key.pem');
            $input = new WxPayUnifiedOrder();
            $input->SetBody("test");
            $input->SetAttach("test");
            $input->SetOut_trade_no($config->GetMerchantId() . date("YmdHis"));
            $input->SetTotal_fee("1");
            $input->SetTime_start(date("YmdHis"));
            $input->SetTime_expire(date("YmdHis", time() + 600));
            $input->SetGoods_tag("test");
            $input->SetNotify_url("http://paysdk.weixin.qq.com/notify.php");
            $input->SetTrade_type("NATIVE");
            $input->SetOpenid($openId);
            $input->SetProduct_id($product_id);
            try {
                $result = WxPayApi::unifiedOrder($config, $input);
                Log::DEBUG("unifiedorder:" . json_encode($result));
            } catch (Exception $e) {
                Log::ERROR(json_encode($e));
                $result ='';
            }
            return $result;
        }

        /**
         *
         * 回包前的回调方法
         * 业务可以继承该方法，打印日志方便定位
         * @param string $xmlData 返回的xml参数
         *
         **/
        public function LogAfterProcess($xmlData)
        {
            Log::DEBUG("call back， return xml:" . $xmlData);
            return;
        }

        /**
         * @param WxPayNotifyResults $objData 回调解释出的参数
         * @param WxPayConfigInterface $config
         * @param string $msg 如果回调处理失败，可以将错误信息输出到该方法
         * @return true 回调出来完成不需要继续回调，false回调处理未完成需要继续回调
         */
        public function NotifyProcess($objData, $config, &$msg)
        {
            $data = $objData->GetValues();
            //echo "处理回调";
            Log::DEBUG("call back:" . json_encode($data));
            //TODO 1、进行参数校验
            if (!array_key_exists("openid", $data) ||
                !array_key_exists("product_id", $data)) {
                $msg = "回调数据异常";
                Log::DEBUG($msg . json_encode($data));
                return false;
            }

            //TODO 2、进行签名验证
            try {
                $checkResult = $objData->CheckSign($config);
                if ($checkResult == false) {
                    //签名错误
                    Log::ERROR("签名错误...");
                    return false;
                }
            } catch (Exception $e) {
                Log::ERROR(json_encode($e));
            }

            $openid = $data["openid"];
            $product_id = $data["product_id"];

            //TODO 3、处理业务逻辑
            //统一下单
            $result = $this->unifiedorder($openid, $product_id);
            if (!array_key_exists("appid", $result) ||
                !array_key_exists("mch_id", $result) ||
                !array_key_exists("prepay_id", $result)) {
                $msg = "统一下单失败";
                Log::DEBUG($msg . json_encode($data));
                return false;
            }

            $this->SetData("appid", $result["appid"]);
            $this->SetData("mch_id", $result["mch_id"]);
            $this->SetData("nonce_str", WxPayApi::getNonceStr());
            $this->SetData("prepay_id", $result["prepay_id"]);
            $this->SetData("result_code", "SUCCESS");
            $this->SetData("err_code_des", "OK");
            return true;
        }
    }

    $config = new WxPayConfig();
    Log::DEBUG("begin notify!");
    $notify = new NativeNotifyCallBack();
    $notify->Handle($config, true);





