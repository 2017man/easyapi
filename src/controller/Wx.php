<?php

namespace EasyApi\Controller;

use EasyApi\Controller\Api;
use EasyApi\Controller\Send;
use Think\Controller;
use EasyWeChat\Factory;

class Wx extends Api
{
    use Send;
    use Member;

    protected $noAuth = ["check_login", 'decode_phone'];

    /**
     *
     * @name 微信登录
     */
    public function wxlogin()
    {
        $config = [
            'app_id' => config('WXAPPID'),
            'secret' => config('WXSECRET'),
            // 下面为可选项
            // 指定 API 调用返回结果的类型：array(default)/collection/object/raw/自定义类名
            'response_type' => 'array',
            'log' => [
                'level' => 'debug',
                'file' => __DIR__ . '/wechat.log',
            ],
        ];
        $app = Factory::miniProgram($config);
        $session = $app->auth->session($this->post['code']);

        $decryptedData = $app->encryptor->decryptData($session['session_key'], $this->post['iv'], $this->post['encryptedData']);
        $member_map['openid'] = $decryptedData['openId'];

        $member_count = db("member")->where($member_map)->find();
        if (empty($member_count)) {
            $member_data['nickname'] = $decryptedData['nickName'];
            $member_data['openid'] = $decryptedData['openId'];
            $member_data['avatar'] = $decryptedData['avatarUrl'];
            $member_data['regtime'] = time();
            $member_id = db("member")->insert($member_data);
        } else {
            $member_data['nickname'] = $decryptedData['nickName'];
            $member_data['avatar'] = $decryptedData['avatarUrl'];
            db("member")->where($member_map)->update($member_data);
            $member_id = $member_count['openid'];
        }
        // 把3rd session缓存到redis中
        $session3rd = md5(uniqid(md5(microtime(true)), true));
        cache("$session3rd", $decryptedData['openId'] . '#' . $session3rd);
        // 返回session信息数据
        $data['openId'] = $member_id;
        $data['rd_session'] = $session3rd;
        $data['member_info'] = $decryptedData;
        $data['message'] = 'success';
        $data['result'] = 'success';
        return self::returnMsg('200', '认证成功', $data);
    }

    // 从小程序发起检测登录状态
    public function check_login()
    {
        $rd_session = $this->post['rd_session'];
        $info = cache("$rd_session");
        if (!empty($info)) {
            $member_info = self::decode_member_info();
            if ($member_info) {
                $data = array(
                    "result" => "success",
                    "message" => "online"
                );
                return self::returnMsg('200', 'online', $data);
            } else {
                $data = array(
                    "result" => "success",
                    "message" => "offline"
                );
                return self::returnMsg('200', 'offline', $data);
            }
        } else {
            $data = array(
                "result" => "success",
                "message" => "offline"
            );
            return self::returnMsg('200', 'offline', $data);
        }
    }

    /**
     *
     * @name 获取手机号
     */
    public function decode_phone()
    {
        $config = [
            'app_id' => config('WXAPPID'),
            'secret' => config('WXSECRET'),
            // 下面为可选项
            // 指定 API 调用返回结果的类型：array(default)/collection/object/raw/自定义类名
            'response_type' => 'array',

        ];
        $member_info = $this->decode_member_info();
        $app = Factory::miniProgram($config);
        $session = $app->auth->session($this->post['code']);
        $decryptedData = $app->encryptor->decryptData($session['session_key'], $this->post['iv'], $this->post['encryptedData']);
        $member_map['openid'] = $member_info['openid'];
        db("member")->where($member_map)->update([
            'phone' => $decryptedData['phoneNumber']
        ]);
        $data['data']['phone'] = $decryptedData['phoneNumber'];
        $data['result'] = 'success';
        return_json($data);
    }

}