<?php
/**
 * Created by PhpStorm.
 * User: 亿捷网络
 * Date: 2017/12/25
 * Time: 16:54
 */

namespace App\Http\Controllers;

use Exception;

/**
 * 发送短信
 * @version v1
 */
class ChitSdk
{
    protected $url = 'http://api2.santo.cc/submit?';
    # API　key
    protected $key = '0tohwv';
    # API　secret
    protected $secret = '6Sq7IWjW';
    # 短信方式（语音VO_REQUEST、短信MT_REQUEST）
    protected $command = 'MT_REQUEST';

    public function __construct($command = 'MT_REQUEST')
    {
        $this->command = $command;
    }

    /**发送短信
     * @param $phone
     * @param $sm
     * @return bool|string
     */
    public function testChit($phone, $sm)
    {
        # 获取url采纳数
        $params = $this->getParams($phone, $sm);
        # 获取完整url
        $url = $this->getUrlParam($params);
        # 发送请求
        try {
            return file_get_contents($url);
        } catch (Exception $e) {
            return 'error Chit Send';
        }
    }

    /**
     * 获取参数
     * @param $phone
     * @param $sm
     * @return mixed
     */
    public function getParams($phone, $sm)
    {
        $params['cpid'] = $this->key;
        $params['cppwd'] = $this->secret;
        $params['command'] = $this->command;
        # 回复内容
        $params['sm'] = '申请短信验证码：' . $sm . '，请勿提供给他人，切勿转发。做数学、说数学、玩数学尽在超脑麦斯';
        # 请求手机号
        $params['da'] = $this->getPhone($phone);

        return $params;
    }

    /**
     * 获取手机号
     * @param $phone
     * @return string
     */
    protected function getPhone($phone)
    {
        # 判断单目标发送｜多目标发送
        if (is_string($phone)) return $phone;

        return implode($phone, ',');
    }

    /**
     * 获取url的参数
     * @param array $arr
     * @return string
     */
    protected function getUrlParam(Array $arr)
    {
        return $this->url.http_build_query ($arr);

    }
}