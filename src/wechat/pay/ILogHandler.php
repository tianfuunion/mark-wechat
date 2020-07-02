<?php

declare (strict_types=1);

namespace mark\wechat\pay;

interface ILogHandler {

    public function write($msg);

}
