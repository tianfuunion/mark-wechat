<?php

    declare (strict_types=1);

    namespace mark\wechat\pay;

    /**
     * 回调回包数据基类
     *
     * Class WxPayNotifyResults
     * @package mark\wechat\pay
     */
    class WxPayNotifyResults extends WxPayResults
    {
        /**
         * 将xml转为array
         * @param WxPayConfigInterface $config
         * @param string $xml
         * @return WxPayNotifyResults
         * @throws WxPayException
         */
        public static function Init($config, $xml)
        {
            $obj = new self();
            $obj->FromXml($xml);
            //失败则直接返回失败
            $obj->CheckSign($config);
            return $obj;
        }
    }
