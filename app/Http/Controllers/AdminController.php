<?php

namespace App\Http\Controllers;

use App\Models\Spider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller{
    //登陆用户的详情
    public function userinfo(Request $request){
        $a['avatar']=[
            'https://i.gtimg.cn/club/item/face/img/2/15922_100.gif'
        ];
        $a['permissions']=['admin'];
        $a['username']='admin';
        return $this->encode($a);
    }
    //获取蜘蛛的统计信息
    public function spiders(){
        $spiders=Spider::get();
        foreach($spiders as $spider){
            $logs=$spider->logs;
        }
        return $this->encode($spiders);
    }
    //退出
    public function logout(Request $request){
        return $this->encode($request->user()->token()->delete());
    }
    //登录
    public function login(Request $request){
        $request->validate([
            'username' => ['required'],
            'password' => ['required'],
        ]);
        if (Auth::attempt(['name'=>$request->username,'password'=>$request->password])){
            $request->session()->regenerate();
            $token['token']=$request->user()->createToken('user')->accessToken;
            return $this->encode($token);
        }
        return $this->encode(0,'帐号或密码错误',201);
    }
    public function encode($data,$msg='获取数据成功',$code=200){
        $array['code']=$code;
        $array['msg']=$msg;
        $array['data']=$data;
        return json_encode($array);
    }
}
