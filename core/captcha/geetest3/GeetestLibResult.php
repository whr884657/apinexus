<?php
/**
 * 文件：core/captcha/geetest3/GeetestLibResult.php
 * 作用：极验 3 代 SDK 返回结果（自官方 demo 精简，无框架依赖）
 */

class GeetestLibResult
{
    private $status = 0;
    private $data = '';
    private $msg = '';

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($status)
    {
        $this->status = $status;
    }

    public function getData()
    {
        return $this->data;
    }

    public function setData($data)
    {
        $this->data = $data;
    }

    public function getMsg()
    {
        return $this->msg;
    }

    public function setMsg($msg)
    {
        $this->msg = $msg;
    }

    public function setAll($status, $data, $msg)
    {
        $this->setStatus($status);
        $this->setData($data);
        $this->setMsg($msg);
    }
}
