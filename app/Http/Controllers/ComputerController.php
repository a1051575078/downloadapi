<?php

namespace App\Http\Controllers;

use App\Models\App;
use App\Models\Classification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class ComputerController extends Controller{
    public function index(){
        header('X-Accel-Buffering: no'); // nginx要加这一行
        set_time_limit(0);
        ob_end_clean();//在循环输出前，要关闭输出缓冲区
        echo str_pad('',1024);
        $num=0;
        while(true){
            $frequency=$num*28;
            $response=$this->curl("https://s.pcmgr.qq.com/tapi/web/softlistcgi.php?callback=loadList&c=0&sort=0&offset=$frequency&limit=28&noplugin=0");
            $num++;
            $response=str_replace('loadList(','',$response);
            $response=str_replace(');','',$response);
            $response=json_decode($response);
            if(!$response->list){
                break;
            }
            foreach($response->list as $v){
                $url='https://pc.qq.com'.$v->detailUrl;
                $res=$this->curl($url);
                preg_match('/cat-curr[\s\S]*?data-classname="(.*?)" class/',$res,$type);
                if(isset(Classification::where('name',$type[1])->where('navigation_id',1)->first()['id'])){
                    $classification_id=Classification::where('name',$type[1])->where('navigation_id',1)->first()['id'];
                }
                preg_match('/detail-other clearfix">[\s\S]*?(\d+-\d+-\d+)<\/li>/',$res,$time);
                preg_match('/detail-other clearfix">[\s\S]*?大小：(.*?)<\/li>/',$res,$size);
                preg_match('/detail-other clearfix">[\s\S]*?版本：(.*?)<\/li>/',$res,$version);
                preg_match('/detail-system clearfix">[\s\S]*?>(.*?)<\/li>[\s\S]*?>(.*?)<\/li>/',$res,$operatingsystem);
                preg_match('/<ul class="whatnews">([\s\S]*?)<\/ul>/',$res,$log);
                preg_match('/class="cont-content">([\s\S]*?)<\/p>/',$res,$content);
                preg_match('/class="detail-install-normal" href="(.*?)"/',$res,$download);
                $log=preg_replace('/<a[\s\S]*?>.*?<\/a>/','',$log[1]);
                $log=preg_replace('/<li[\s\S]*?>/','',$log);
                $log=preg_replace('/<span[\s\S]*?>.*?<\/span>/','',$log);
                $log=preg_replace('/&nbsp;/','',$log);
                $log=preg_replace('/\s+/','',$log);
                $log=str_replace('</li>','<br/>',$log);
                $content=str_replace('<p>','',$content[1]);
                $content=str_replace('</p>','<br/>',$content);
                $app=App::where('name',$v->sn)->first();
                if(!$app){
                    $app=App::create([
                        'name'=>$v->sn,
                        'image'=>$v->lg
                    ])->where('name',$v->sn)->first();
                }
                $navigationTag=0;
                $classificationTag=0;
                foreach($app->navigations as $navigation){
                    if($navigation->pivot->navigation_id===1){
                        $navigationTag=1;
                        break;
                    }
                }
                //每个navigation_id 只能对应一个APP
                if(!$navigationTag){
                    $app->navigations()->attach([
                        1=>[
                            'version'=>$version[1],
                            'size'=>$size[1],
                            'newdate'=>$time[1],
                            'url'=>$url,
                            'download'=>$download[1],
                            'content'=>trim($content),
                            'log'=>$log,
                            'operatingsystem'=>$operatingsystem[1].'|'.$operatingsystem[2]
                        ]]);
                }
                foreach($app->classifications as $classification){
                    if($classification->pivot->classification_id===$classification_id){
                        $classificationTag=1;
                        break;
                    }
                }
                if(!$classificationTag){
                    $app->classifications()->attach([$classification_id]);
                }
                echo $v->sn.$num."<br/>";flush();
            }
        }
    }
    public function update(){
        header('X-Accel-Buffering: no'); // nginx要加这一行
        set_time_limit(0);
        ob_end_clean();//在循环输出前，要关闭输出缓冲区
        echo str_pad('',1024);
        App::chunk(200,function(Collection $apps){
            foreach($apps as $app){
                foreach($app->navigations as $navigation){
                    if($navigation->pivot->navigation_id===1){
                        $res=$this->curl($navigation->pivot->url);
                        preg_match('/detail-other clearfix">[\s\S]*?(\d+-\d+-\d+)<\/li>/',$res,$time);
                        preg_match('/detail-other clearfix">[\s\S]*?大小：(.*?)<\/li>/',$res,$size);
                        preg_match('/detail-other clearfix">[\s\S]*?版本：(.*?)<\/li>/',$res,$version);
                        preg_match('/detail-system clearfix">[\s\S]*?>(.*?)<\/li>[\s\S]*?>(.*?)<\/li>/',$res,$operatingsystem);
                        preg_match('/<ul class="whatnews">([\s\S]*?)<\/ul>/',$res,$log);
                        preg_match('/class="cont-content">([\s\S]*?)<\/p>/',$res,$content);
                        preg_match('/class="detail-install-normal" href="(.*?)"/',$res,$download);
                        preg_match('/<h2 class="detail-name ellipsis">(.*?)<\/h2>/',$res,$name);
                        preg_match('/<a[\s\S]*?>[\s\S]*?<img onerror="imgError\(this\);" src="(.*?)"/',$res,$img);
                        $log=preg_replace('/<a[\s\S]*?>.*?<\/a>/','',$log[1]);
                        $log=preg_replace('/<li[\s\S]*?>/','',$log);
                        $log=preg_replace('/<span[\s\S]*?>.*?<\/span>/','',$log);
                        $log=preg_replace('/&nbsp;/','',$log);
                        $log=preg_replace('/\s+/','',$log);
                        $log=str_replace('</li>','<br/>',$log);
                        $content=str_replace('<p>','',$content[1]);
                        $content=str_replace('</p>','<br/>',$content);
                        App::where('id',$navigation->pivot->app_id)
                            ->update([
                                'name'=>$name[1],
                                'image'=>$img[1]
                            ]);
                        $app->navigations()->updateExistingPivot(1,[
                            'version'=>$version[1],
                            'size'=>$size[1],
                            'newdate'=>$time[1],
                            'download'=>$download[1],
                            'content'=>trim($content),
                            'log'=>$log,
                            'operatingsystem'=>$operatingsystem[1].'|'.$operatingsystem[2]
                        ]);
                        echo $name[1]."更新成功<br/>";
                        flush();
                    }
                }
            }
        });
    }
    public function curl($url){
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => str_replace([" ","\t","\r","\n"],'',$url),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_CONNECTTIMEOUT=>6,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_SSL_VERIFYPEER=>false
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        if(!strlen($response)){
            echo $url.'抓取有问题重新抓取';
            flush();//刷新输出缓冲
            return $this->curl($url);
        }else{
            return $response;
        }
    }
}
