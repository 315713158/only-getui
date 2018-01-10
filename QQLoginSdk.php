<?php
/**
 * Created by PhpStorm.
 * User: 亿捷网络
 * Date: 2018/1/10
 * Time: 15:03
 */


/**
 * 接口
 * @version v1
 */
class QQLoginSdk
{
    protected $codeUrl = 'https://graph.qq.com/oauth2.0/authorize?';
    protected $tokenUrl = 'https://graph.qq.com/oauth2.0/token?';
    protected $UserDataUrl = 'https://graph.qq.com/user/get_user_info?';
    protected $openUrl = 'https://graph.qq.com/oauth2.0/me?';
    protected $appId = '101456646';
    protected $appKey = '8ad1847c74689fb7832eaf6389ae5998';
    protected $uri = 'http://120.78.63.112/chenxin/weixin/2.php';

    /**
     * 获取qq登录页url
     * @method get
     */
    public function getQQLoginurl()
    {
        return $this->codeUrl . $this->getUrl([
                'response_type' => 'code',
                'client_id' => $this->appId,
                'redirect_uri' => $this->uri,
                'state' => app('sessionId')
            ]);

    }

    /**
     * 获取url参数
     * @param $array
     * @return string
     */
    protected function getUrl($array)
    {
        return http_build_query($array);
    }

    /**
     * 得到codeUrl 参数
     * @return array
     */
    protected function getCodeParams()
    {
        return [
            'grant_type' => 'authorization_code',
            'client_id' => $this->appId,
            'client_secret' => $this->appKey,
            'code' => $_GET['code'],
            'redirect_uri' => $this->uri
        ];
    }

    /**
     * 获取用户数据url的参数
     * @param $access_token
     * @param $arr
     * @return mixed
     */
    protected function getUserUrlParams($access_token, $arr)
    {
        $arrs['access_token'] = $access_token;
        $arrs['oauth_consumer_key'] = $arr['client_id'];
        $arrs['openid'] = $arr['openid'];
        return $arrs;
    }

    /**
     * 获取用户数据
     * @return bool|string
     */
    public function getQQUserData()
    {
        $codeUrl = $this->tokenUrl . $this->getUrl($this->getCodeParams());
        #参数转变量
        parse_str(file_get_contents($codeUrl));

        if (!isset($access_token)) header($this->testQQLogin());

        $openUrl = $this->openUrl . $this->getUrl(['access_token' => $access_token]);
        # 过滤
        preg_match('/\{(.*?)\}/', file_get_contents($openUrl), $json);

        $arr = json_decode($json[0], true);

        $userDataUrl = $this->UserDataUrl . $this->getUrl($this->getUserUrlParams($access_token, $arr));

        return file_get_contents($userDataUrl);
    }

}
