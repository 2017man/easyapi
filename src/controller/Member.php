<?php

namespace EasyApi\Controller;

use think\Controller;
use think\Db;

/**
 * 监测会员信息
 * Trait Member
 * @package app\api\controller
 */
trait Member
{

    //获取管理员信息
    protected function decode_admin_info()
    {
        $rd_session = input('user_rd_session');
        $string = cache("$rd_session");
       
        $arr = explode('#', $string);
        $name = $arr[0];
        $ret = Db::table("admin")->where('name', "$name")->find();
        if (empty($ret)) {
            return '';
        } else {
            $ret['status'] = 1;
            $ret['rd_session'] = $rd_session;
            return $ret;
        }
    }

    //获取用户信息
    protected function decode_member_info()
    {
        $rd_session = input('rd_session');
        $string = cache("$rd_session");
        $arr = explode('#', $string);
        $openid = $arr[0];
        $ret = Db::table("member")->where('openid', "$openid")->find();
        if (empty($ret)) {
            return '';
        } else {
            $ret['status'] = 1;
            $ret['rd_session'] = $rd_session;
            return $ret;
        }
    }


}

