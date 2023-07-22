<?php

namespace App\Http\Controllers;

use App\Models\App;
use App\Models\Classification;
use App\Models\Navigation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class AppleController extends Controller{
    //删除某一个苹果app并且所有有关联的中间表
    public function delete(){
        $app=App::find(5909);
        if(!empty($app)){
            $app->delete();
            $app->navigations()->detach();
            $app->classifications()->detach();
        }
    }
    //更新所有苹果app的内容
    public function update(){
        header('X-Accel-Buffering: no'); // nginx要加这一行
        set_time_limit(0);
        ob_end_clean();//在循环输出前，要关闭输出缓冲区
        echo str_pad('',1024);
        App::chunk(200,function(Collection $apps){
            foreach($apps as $app){
                foreach($app->navigations as $navigation){
                    if($navigation->pivot->navigation_id===3){
                        $this->store($navigation->pivot->url,$navigation->pivot->app_id);
                        echo '更新app:'.$navigation->pivot->url.'<br/>';
                        flush();
                    }
                }
            }
        });
    }
    //抓取苹果所有分类收费和免费的TOP榜100名
    public function index(){
        header('X-Accel-Buffering: no'); // nginx要加这一行
        set_time_limit(0);
        ob_end_clean();//在循环输出前，要关闭输出缓冲区
        echo str_pad('',1024);
        //$pinyin=app('pinyin');
        //获取所有分类的链接
        $classification=$this->curl('https://apps.apple.com/cn/charts/iphone/%E5%95%86%E5%8A%A1-apps/6000');
        preg_match('/<script type="fastboot\/shoebox" id="shoebox-media-api-cache-apps">(.*?)<\/script>/',$classification,$matche);
        $json=json_decode($matche[1],true);
        $array=[];
        foreach($json as $v){
            $json=json_decode($v,true);
            if(empty($json['d']['categories'][0]['children'])){
                continue;
            }
            $url=$json['d']['categories'][0]['children'];
            foreach($url as $u){
                if(!empty($u['chartUrl'])){
                    if($u['chartUrl']!=='https://apps.apple.com/cn/charts/iphone/%E5%84%BF%E7%AB%A5-games/36?ageId=0'){
                        array_push($array,$u['chartUrl']);
                    }
                }
            }
        }
        foreach($array as $domain){
            echo '-------------------------------------------'.$domain."<br>";
            if($domain==='https://apps.apple.com/cn/charts/iphone/%E5%84%BF%E7%AB%A5-apps/36?ageId=0'){
                $domain=$domain.'&chart=top-';
            }else{
                $domain=$domain.'?chart=top-';
            }
            $type=['paid','free'];
            foreach($type as $v){
                $url=$this->curl($domain.$v);
                preg_match_all('/<a href="(.*)" class="we-lockup targeted-link.*">/',$url,$matche);
                foreach($matche[1] as $url){
                    $this->store($url);
                }
            }
        }
    }
    public function store($url,$appId=''){
        $response=$this->curl($url);
        preg_match('/大小<\/dt>[\s\S]*?">(.*?)<\/dd>/',$response,$match);
        preg_match('/product-hero__artwork[\s\S]*?<source srcset="(.*?) \w+,/',$response,$img);
        preg_match('/<script type="fastboot\/shoebox" id="shoebox-media-api-cache-apps">(.*"})<\/script>/',$response,$details);
        if(empty($details[1])){
            $this->store($url);
            echo '内容有问题'.$response."<br>";
        }else{
            $details=json_decode($details[1],true);
            foreach($details as $v){
                $details=json_decode($v,true);
                break;
            }
            $data=$details['d'][0];
            $name=$data['attributes']['name'];//app名称
            if(empty($data['attributes']['genreDisplayName'])){
                Classification::create([
                    'name'=>$data['attributes']['genreDisplayName']
                ]);
            }
            if(isset(Classification::where('name',$data['attributes']['genreDisplayName'])->where('navigation_id',3)->first()['id'])){
                $classification_id=Classification::where('name',$data['attributes']['genreDisplayName'])->where('navigation_id',3)->first()['id'];
                if(empty($data['attributes']['platformAttributes']['ios']['seller'])){
                    $author=$data['attributes']['seller'];
                }else{
                    $author=$data['attributes']['platformAttributes']['ios']['seller'];//开发者
                }
                $img=$img[1];
                $version='1.0.0';//版本
                $newdate='2008-01-01';//更新时间
                $log='';//更新日志
                if(!empty($data['attributes']['platformAttributes']['ios']['versionHistory'])){
                    for($i=0;$i<count($data['attributes']['platformAttributes']['ios']['versionHistory']);$i++){
                        if(!$i){
                            $version=$data['attributes']['platformAttributes']['ios']['versionHistory'][$i]['versionDisplay'];
                            $newdate=$data['attributes']['platformAttributes']['ios']['versionHistory'][$i]['releaseDate'];
                        }
                        $temp=$data['attributes']['platformAttributes']['ios']['versionHistory'][$i];
                        $log=$log.'版本：'.$temp['versionDisplay'].'更新时间'.$temp['releaseDate'].'更新信息：'.$temp['releaseNotes']."<br/>";
                    }
                }else{
                    $log='无更新日志';
                }
                if(empty($match[1])){
                    $size='未知';
                }else{
                    $size=$match[1];//大小
                }
                $url=$data['attributes']['url'];//爬取的url链接
                if(empty($data['attributes']['platformAttributes']['ios']['description']['standard'])){
                    $content=$data['attributes']['description']['standard'];//app描述
                }else{
                    $content=$data['attributes']['platformAttributes']['ios']['description']['standard'];//app描述
                }
                $operatingsystem=$data['attributes']['platformAttributes']['ios']['requirementsString'];//操作系统
                if($appId){
                    App::where('id',$appId)
                        ->update([
                            'name'=>$name,
                            'image'=>$img
                        ]);
                    $app=App::find($appId);
                }else{
                    $app=App::where('name',$name)->first();
                    if(!$app){
                        $app=App::create([
                            'name'=>$name,
                            'author'=>$author,
                            'image'=>$img
                        ])->where('name',$name)->first();
                    }
                }
                $navigationTag=0;
                $classificationTag=0;
                foreach($app->navigations as $navigation){
                    if($navigation->pivot->navigation_id===3){
                        $navigationTag=1;
                        break;
                    }
                }
                if(!$navigationTag){
                    $app->navigations()->attach([3=>[
                        'version'=>$version,
                        'size'=>$size,
                        'newdate'=>$newdate,
                        'url'=>$url,
                        'download'=>$url,
                        'content'=>$content,
                        'log'=>$log,
                        'operatingsystem'=>$operatingsystem
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
                echo $name."<br>";
                flush();//刷新输出缓冲
            }
        }
    }
    public function curl($url){
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => str_replace([" ","\t","\r","\n"],'',$url),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_CONNECTTIMEOUT=>10,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_SSL_VERIFYPEER=>false
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        if(strlen($response)<1000){
            echo $url.'抓取有问题重新抓取';
            flush();//刷新输出缓冲
            return $this->curl($url);
        }else{
            return $response;
        }
    }
}
