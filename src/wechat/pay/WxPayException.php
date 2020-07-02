<?php

    declare (strict_types=1);

    namespace mark\wechat\pay;

    use Exception;

    /**
     *
     * 微信支付API异常类
     * @author widyhu
     *
     * Class WxPayException
     * @package mark\wechat\pay
     */
    class WxPayException extends Exception
    {
        public function errorMessage()
        {
            return $this->getMessage();
        }
    }
