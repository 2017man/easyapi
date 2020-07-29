<?php

namespace EasyApi\Controller;

use think\Controller;
use think\Request;
use EasyApi\Controller\Send;
use EasyApi\Controller\Oauth;

/**
 * api 入口文件基类，需要控制权限的控制器都应该继承该类
 */
class Api
{
    use Send;

    /**
     * @var \think\Request Request实例
     */
    protected $request;

    protected $clientInfo;

    /**
     * 不需要鉴权方法
     */
    protected $noAuth = [];

    protected $post;

    /**
     * 构造方法
     * @param Request $request Request对象
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->init();
        $this->uid = $this->clientInfo['uid'];
        $this->post = $request->post();

    }

    /**
     * 初始化
     * 检查请求类型，数据格式等
     */
    public function init()
    {
        //所有ajax请求的options预请求都会直接返回200，如果需要单独针对某个类中的方法，可以在路由规则中进行配置
        if ($this->request->isOptions()) {

            return self::returnMsg(200, 'success');
        }
        if (!Oauth::match($this->noAuth)) {               //请求方法白名单
            $oauth = app('EasyApi\controller\Oauth');   //tp5.1容器，直接绑定类到容器进行实例化
            return $this->clientInfo = $oauth->authenticate();
        }

    }

    /**
     * 空方法
     */
    public function _empty()
    {
        return self::returnMsg(404, 'empty method!');
    }


    // 检测登录状态
    public function check_online_action($exception_action)
    {
        $exception_action = array_merge($exception_action, [
            'login',
            'index',
            "get_upload",
            "upload_image",
        ]);

        if (!in_array(request()->action(), $exception_action)) {
            $rd_session = input('rd_session');
            if (empty($rd_session) || $rd_session == 'undefined') {
                $data = [
                    "result" => "error",
                    "message" => "login",
                    "info" => "请求url缺少rd_session参数",
                ];
                return self::returnMsg('200', 'login', $data);
            } else {
                $info = cache("$rd_session");
                if (empty($info)) {
                    $data = [
                        "result" => "error",
                        "message" => "login",
                        "info" => "登录状态已过期，请调用登录接口获取session",
                    ];
                    return self::returnMsg('200', 'login', $data);
                }
            }
        }

    }

    //检测管理员登录态
    public function check_user_online_action($exception_action)
    {
        $exception_action = array_merge($exception_action, [
            'login',
            'index',
            "get_upload",
            "upload_image",
        ]);

        if (!in_array(request()->action(), $exception_action)) {
            $rd_session = input('user_rd_session');
            if (empty($rd_session) || $rd_session == 'undefined') {
                $data = [
                    "result" => "error",
                    "message" => "login",
                    "info" => "请求url缺少user_rd_session参数",
                ];
                return self::returnMsg('200', 'login', $data);
            } else {
                $info = cache("$rd_session");
                if (empty($info)) {
                    $data = [
                        "result" => "error",
                        "message" => "login",
                        "info" => "登录状态已过期，请调用登录接口获取session",
                    ];
                    return self::returnMsg('200', 'login', $data);
                }
            }
        }

    }

}