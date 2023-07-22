<?php

namespace App\Http\Controllers;

use App\Models\App;
use App\Models\Classification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class BaiduController extends Controller{
    public function index(){
        header('X-Accel-Buffering: no'); // nginx要加这一行
        set_time_limit(0);
        ob_end_clean();//在循环输出前，要关闭输出缓冲区
        echo str_pad('',1024);
        //获取到所有的分类id
        $types=$this->curl('https://appc.baidu.com/marvel/board/app-types');
        $games=$this->curl('https://appc.baidu.com/marvel/board/game-types');
        $types=json_decode($types);
        $games=json_decode($games);
        $id=[];
        /*foreach($types->data as $v){
            if(!preg_match('/分类/',$v->name)){
                array_push($id,$v->boardId);
                $this->store($v->boardId,$v->name);
            }
        }*/
        foreach($games->data as $v){
            if(!preg_match('/分类/',$v->name)){
                array_push($id,$v->boardId);
                $this->store($v->boardId,$v->name);
            }
        }
        var_dump($id);
    }
    public function update(){
        header('X-Accel-Buffering: no'); // nginx要加这一行
        set_time_limit(0);
        ob_end_clean();//在循环输出前，要关闭输出缓冲区
        echo str_pad('',1024);
        App::chunk(200,function(Collection $apps){
            foreach($apps as $app){
                foreach($app->navigations as $navigation){
                    if($navigation->pivot->navigation_id===2){
                        $response=$this->curl($navigation->pivot->url);
                        preg_match('/[\s\S]*?(\d+)/',$navigation->pivot->url,$packageId);
                        $packageId=$packageId[1];
                        preg_match('/<script>window.__INITIAL_STATE__=([\s\S]*?)<\/script>/',$response,$matche);
                        $json=json_decode($matche[1]);
                        if(isset($json->detail->resMap->$packageId->data)){
                            $data=$json->detail->resMap->$packageId->data;
                            App::where('id',$navigation->pivot->app_id)
                                ->update([
                                    'name'=>$data->title,
                                    'image'=>$data->icon
                                ]);
                            $app->navigations()->updateExistingPivot(2,[
                                'version'=>$data->versionName,
                                'size'=>$data->size,
                                'newdate'=>$data->updatetime,
                                'download'=>$data->downloadInner,
                                'content'=>$data->brief,
                                'log'=>$data->changelog
                            ]);
                            $app->navigations()->updateExistingPivot(4,[
                                'version'=>$data->versionName,
                                'size'=>$data->size,
                                'newdate'=>$data->updatetime,
                                'download'=>$data->downloadInner,
                                'content'=>$data->brief,
                                'log'=>$data->changelog
                            ]);
                            echo $app->name."更新成功<br/>";
                            flush();
                        }
                    }
                }
            }
        });
    }
    public function store($typeId,$name){
        $num=0;
        while(true){
            $app=$this->curl("https://shouji.baidu.com/marvel/board/detail/$typeId?pn=$num&ps=10",'list');
            echo "https://shouji.baidu.com/marvel/board/detail/$typeId?pn=$num&ps=10<br/>";
            $num++;
            $app=json_decode($app);
            if(!$app->data->boards[0]->hasNextPage){
                break;
            }
            if(!isset($app->data->boards[0]->items)){
                echo '内容没有';flush();
                break;
            }
            $items=$app->data->boards[0]->items;
            foreach($items as $v){
                if(isset(Classification::where('name',$name)->where('navigation_id',2)->first()['id'])){
                    $classification_id=Classification::where('name',$name)->where('navigation_id',2)->first()['id'];
                    $packageId=$v->packageId;
                    $response=$this->curl('https://shouji.baidu.com/detail/'.$packageId);
                    preg_match('/<script>window.__INITIAL_STATE__=([\s\S]*?)<\/script>/',$response,$matche);
                    $json=json_decode($matche[1]);
                    $app=App::where('name',$v->title)->first();
                    if(!$app){
                        $app=App::create([
                            'name'=>$v->title,
                            'author'=>$v->developerName,
                            'image'=>$v->icon
                        ])->where('name',$v->title)->first();
                    }
                    $navigationTag=0;
                    $classificationTag=0;
                    foreach($app->navigations as $navigation){
                        if($navigation->pivot->navigation_id===2){
                            $navigationTag=1;
                            break;
                        }
                    }
                    //每个navigation_id 只能对应一个APP
                    if(!$navigationTag){
                        $app->navigations()->attach([
                            2=>[
                                'version'=>$v->versionName,
                                'size'=>$v->size,
                                'newdate'=>$json->detail->resMap->$packageId->data->updatetime,
                                'url'=>'https://shouji.baidu.com/detail/'.$packageId,
                                'download'=>$json->detail->resMap->$packageId->data->downloadInner,
                                'content'=>$v->brief,
                                'log'=>$v->changelog,
                                'operatingsystem'=>'安卓最新版'
                            ],
                            4=>[
                                'version'=>$v->versionName,
                                'size'=>$v->size,
                                'newdate'=>$json->detail->resMap->$packageId->data->updatetime,
                                'url'=>'https://shouji.baidu.com/detail/'.$packageId,
                                'download'=>$json->detail->resMap->$packageId->data->downloadInner,
                                'content'=>$v->brief,
                                'log'=>$v->changelog,
                                'operatingsystem'=>'鸿蒙最新版'
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
                    echo $v->title."<br/>";flush();
                }
            }
        }
    }
    public function curl($url,$list=''){
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
        //如果是列表页抓取，那么判断
        if($list){
            $app=json_decode($response);
            if(!isset($app->data->boards[0]->hasNextPage)){
                echo '列表有问题重新抓';flush();//刷新输出缓冲
                return $this->curl($url,'list');
            }
        }
        if(!strlen($response)){
            echo $url.'抓取有问题重新抓取';
            flush();//刷新输出缓冲
            return $this->curl($url);
        }else{
            if(preg_match('/<title>undefined[\s\S]*?<\/title>/',$response)){
                echo $url.'页面内容没响应出来重新抓取';
                flush();//刷新输出缓冲
                sleep(1);
                return $this->curl($url);
            }
            return $response;
        }
    }
}
