<?php

namespace EasyApi\Controller\v1;

use think\Controller;
use EasyApi\Controller\Api;
use EasyApi\Controller\Send;
use think\Db;

/**
 * 管理员登录
 * Class User
 * @package EasyApi\Controller\v1
 */
class User extends Api
{
    use Send;

    protected $noAuth = [];

    public function __construct()
    {
        $exp = [];
        $this->check_online_action($exp);
    }

    //登录
    public function login()
    {
        $post = request()->post();
        /* $mem = $this->decode_member_info();*/
        $name = $post['name'];
        $password = md5($post['password']);
        $c_n = Db::table('admin')->where(['name' => $name])->count();
        if ($c_n < 1) {
            $err_meg = ['result' => 'error', 'message' => '用户名不存在'];
            return self::returnMsg('200', '用户名不存在', $err_meg);
        }
        $user = Db::table('admin')->where(['name' => $name, 'password' => $password])->find();
        if (empty($user)) {
            $err_meg = ['result' => 'error', 'message' => '密码错误'];
            return self::returnMsg('200', '密码错误', $err_meg);
        } else {
            $session3rd = md5(uniqid(md5(microtime(true)), true));

            cache("$session3rd", $name . '#' . $session3rd);
            // 返回session信息数据
            $data['user_id'] = $user['id'];
            $data['role_id'] = $user['role_id'];
            $data['user_rd_session'] = $session3rd;
            $ret = ['result' => 'success', 'data' => $data];
            return self::returnMsg('200', '登陆成功', $ret);
        }


    }
    

}
