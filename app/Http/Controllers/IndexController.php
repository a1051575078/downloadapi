<?php

namespace App\Http\Controllers;

use App\Models\About;
use App\Models\App;
use App\Models\Classification;
use App\Models\Config;
use App\Models\Navigation;
use App\Models\News;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Response;

class IndexController extends Controller{
    public function test(Request $request){
        set_time_limit(0);
        $client=\OpenAI::client('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');
        $result = $client->completions()->create([
            'model' => 'text-davinci-003',
            'prompt' => '根据instant lines Ipad为标题。写一篇字数偏长的文章',
            'max_tokens'=>4000
        ]);
        echo $result['choices'][0]['text']; // an open-source, widely-used, server-side scripting language.
    }
    //搜索这个app并返回id和名字
    public function search(Request $request){
        return $this->encode(App::where('name','like',"%$request->name%")->first(['id','name','popular']));
    }
    //abous表的相关信息内容
    public function abouts(){
        return $this->encode(About::get());
    }
    //获取about里面的网站地图
    public function map(){
        $navigations=Navigation::get();
        foreach($navigations as $navigation){
            $data[str_replace('/','',$navigation->route)]=$navigation->classifications;
        }
        $data['apps']=$data[str_replace('/','',$navigations[0]->route)];
        return $this->encode($data);
    }
    //首页根据每个分类所需要的数据按照火热程度排序
    public function popular(){
        $navigations=Navigation::get();
        foreach($navigations as $navigation){
            $apps=$navigation->apps10()->with(['classifications'=>function($query) use($navigation){
                return $query->where('navigation_id',$navigation->id)->select(['classifications.id','name']);
            }])->get(['apps.id','good','author','name','popular','weights'])->toArray();
            $t=[];
            foreach($apps as $app){
                $temp=$app;
                $temp['classifications']=$app['classifications'][0];
                array_push($t,$temp);
            }
            $data[str_replace('/','',$navigation->route)]=$t;
        }
        return $this->encode($data);
    }
    //首页根据每个分类所需要的数据按时间排序
    public function home(){
        $navigations=Navigation::get();
        foreach($navigations as $navigation){
            $apps=$navigation->apps40()->with(['classifications'=>function($query) use($navigation){
                return $query->where('navigation_id',$navigation->id)->select(['classifications.id','name']);
            }])->get(['apps.id','good','author','name','popular','weights'])->toArray();
            $t=[];
            foreach($apps as $app){
                $temp=$app;
                $temp['classifications']=$app['classifications'][0];
                array_push($t,$temp);
            }
            $data[str_replace('/','',$navigation->route)]=$t;
        }
        $apps=App::orderBy('time','desc')->limit(20)->get(['apps.id','good','author','name','popular','weights']);
        $t=[];
        foreach($apps as $app){
            $temp=$app;
            $temp->classifications=$app->classifications()->first();
            $temp->navigations=$app->navigations()->first(['version','size','newdate','url','content','log','operatingsystem']);
            array_push($t,$temp);
        }
        $data['app']=$t;
        return $this->encode($data);
    }
    //新闻列表
    public function news(){
        return $this->encode(News::orderBy('time','desc')->paginate(10));
    }
    public function new(Request $request){
        //传过来的值是 /news/id.html
        preg_match('/.*?\/(\d+)/',$request->data,$match);
        if(!$match){
            return $this->encode(0,'参数不正确',404);
        }
        $id=$match[1];
        $new=News::where('id',$id)->first();
        if(!$new){
            return $this->encode(0,'未找到资讯',404);
        }
        $new->app=App::where('id',$new->app_id)->first('name')->name;
        $array=News::where('id','!=',$id)->where('app_id',$new->app_id)->take(8)->orderBy('time','desc')->get(['id','title']);
        $temp=[];
        foreach($array as $v){
            array_push($temp,$v);
        }
        if(count($array)<8){
            $news=News::where('id','!=',$id)->where('app_id','!=',$new->app_id)->orderBy('time','desc')->get(['id','title']);
            if(count($news)>7){
                $num=0;
                while(count($temp)<8){
                    array_push($temp,$news[$num++]);
                }
            }
        }
        $new->other=$temp;
        $new->content=preg_replace("/$new->app/i",'<b><a href="/app/'.$new->app_id.'.html" style="text-decoration: none">'.$new->app.'</a></b>',$new->content);
        return $this->encode($new);
    }
    //返回操作系统分类的相关信息
    public function list(Request $request){
        $navigations=Navigation::get();
        for($i=0;$i<count($navigations);$i++){
            if(!$i){
                $navigation=$navigations[$i];
            }else{
                if(strstr($request->data,$navigations[$i]->route)){
                    $navigation=$navigations[$i];
                    break;
                }
            }
        }
        $typeId=0;
        if(preg_match('/.*?_\d+.*?/',$request->data)){
            preg_match('/.*?_(\d+).*?/',$request->data,$match);
            $typeId=$match[1];
        }
        if(preg_match('/.*?app.*?/',$request->data)&&$typeId){
            if($temp=Classification::where('id',$typeId)->first()){
                $navigation=$temp->navigation()->first();
            }else{
                return $this->encode(0,'分类不存在',404);
            }
        }
        $navigation=Navigation::where('id',$navigation->id)->first();
        if($typeId){
            if($temp=$navigation->classifications()->where('id',$typeId)->first()){
                $datas=$temp->apps()->with(['navigations'=>function($query) use($navigation){
                    $query->where('navigations.id',$navigation->id)->select(['app_id','navigation_id','newdate','size','version']);
                }])->get(['apps.id','good','author','name','popular','weights'])->toArray();
                $t=[];
                foreach($datas as $v){
                    $temp=$v;
                    $temp['navigations']=$v['navigations'][0];
                    array_push($t,$temp);
                }
                $data['apps']=$t;
            }else{
                return $this->encode(0,'分类不存在',404);
            }
        }else{
            /*$datas=$navigation->apps()->with(['classifications'=>function($query) use($navigation){
                $query->where('navigation_id',$navigation->id)->get(['classifications.id']);
            }])->get(['apps.id','good','author','name','popular','weights'])->toArray();//获取当前操作系统所有的app*/
            $data['apps']=$navigation->apps()->get(['apps.id','good','author','name','popular','weights'])->toArray();//获取当前操作系统所有的app
        }
        for($i=0;$i<18;$i++){
            $data['likes'][]=$data['apps'][mt_rand(0,count($data['apps'])-1)];
        }
        $data['classification']=$navigation->classifications;
        return $this->encode($data);
    }
    //点赞或者踩
    public function goodornot(Request $request){
        $app=App::find($request->id);
        if($app->ip===$_SERVER['REMOTE_ADDR']){
            return $this->encode(0,'您的频率太快,请稍候再试',201);
        }
        if($request->type==='good'||$request->type==='nogood'){
            App::where('id',$request->id)->update([
                'ip'=>$_SERVER['REMOTE_ADDR']
            ]);
            return $this->encode(App::where('id',$request->id)->increment($request->type),'操作成功');
        }
        return $this->encode(0,'操作失败',201);
    }
    //返回某个app的所有信息
    public function info(Request $request){
        //传过来的是path /android/id.html
        preg_match('/.*?\/(\d+)/',$request->data,$match);
        if(!$match){
            return $this->encode(0,'参数不正确',404);
        }
        $path=$match[0];
        $id=$match[1];
        $data=App::where('id',$id)->first(['id','name','author','good','nogood']);
        if(!$data){
            return $this->encode(0,'未找到APP',404);
        }
        for($i=0;$i<count($data->navigations);$i++){
            if(!$i){
                $navigation=$data->navigations[$i];
            }else{
                if(strstr($path,$data->navigations[$i]->route)){
                    $navigation=$data->navigations[$i];
                    break;
                }
            }
        }
        $navigation=$data->navigations()->where('navigations.id',$navigation->id)->first();
        $temp=[];
        $temp['id']=$navigation->id;
        $temp['route']=$navigation->route;
        $temp['name']=$navigation->name;
        $temp['content']=$navigation->pivot->content;
        $temp['log']=$navigation->pivot->log;
        $temp['newdate']=$navigation->pivot->newdate;
        $temp['operatingsystem']=$navigation->pivot->operatingsystem;
        $temp['size']=$navigation->pivot->size;
        $temp['version']=$navigation->pivot->version;
        $data['typeinfo']=$temp;
        if($data->author){
            $data['otherApp']=App::where('author',$data->author)->where('id','!=',$id)->take(9)->get(['id','name']);
        }else{
            $data['otherApp']=App::where('author',$data->author)->where('id','!=',$id)->take(9)->inRandomOrder()->get(['id','name']);
        }
        $data['classification']=$data->classifications()->where('navigation_id',$navigation->id)->first();
        $data['classification']['type']=$data->navigations()->get();
        $classifications=$data->classifications()->get();
        foreach($classifications as $classification){
            foreach($classification->navigation as $navigation){
                $navigation;
            }
        }
        $data['classification']['classifications']=$classifications;
        $data['news']=$data->news()->take(6)->get(['id','title']);
        return $this->encode($data);
    }
    //下载地址
    public function download(Request $request){
        $app=App::where('id',$request->id)->first();
        $app->navigations()->get();
        $url=$request->url();
        $navigations=$app->navigations()->get();
        foreach($navigations as $v){
            if(strstr($url,$v->route)){
                switch($v->route){
                    case '/ios';
                    header("Location: ".$v->pivot->download,true,301);
                    exit();
                    case '/android';
                    $suffix='apk';break;
                    case '/computer';
                    $suffix='exe';break;
                    case '/harmonyos';
                    $suffix='hap';break;
                }
                file_put_contents('D:\Code\download\public\download.txt',"资源采集至互联网，请前往官方下载地址：".$v->pivot->download);
                $file=fopen('D:\Code\download\public\download.txt','rb');
                //用来告诉浏览器，文件是可以当做附件被下载，下载后的文件名称为$app->name该变量的值。
                header("Content-Disposition:attachment;filename=$app->name.$suffix");
                //读取文件内容并直接输出到浏览器
                echo fread($file,filesize('D:\Code\download\public\download.txt'));
                fclose($file);
                break;
            }
        }
    }
    //访问任意图片
    public function png(Request $request){
        $app=App::where('id',$request->id)->first();
        if(empty($app->time)){
            $app->time='1970-01-01';
        }
        if(substr($app->image,0,2)=='//'){
            $app->image=str_replace('//','',$app->image);
        }
        return \response($this->curl($app->image))->setExpires(new \DateTime(date(DATE_RFC7231, time() + 31536000)))->setClientTtl(31536000);
        /*$response=Response::make($this->curl($app->image));
        $response->header('Content-Type', 'image/png');
        $response->header('Content-Disposition', 'inline; filename="'.$request->id.'.png'.'"');
        $response->header('Content-Transfer-Encoding', 'binary');
        $response->header('Cache-Control', 'public, max-age=10800, pre-check=10800');
        $response->header('Pragma', 'public');
        $response->header('Expires', date(DATE_RFC822,strtotime(" 2 day")) );
        $response->header('Content-Length', strlen($response));
        return $response;
        $response=Http::get($url);
        return $this->curl($url);*/
    }
    public function curl($url){
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => str_replace([" ","\t","\r","\n"],'',$url),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_CONNECTTIMEOUT=>3,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_SSL_VERIFYPEER=>false,
            CURLOPT_FOLLOWLOCATION=>1,
            CURLOPT_MAXREDIRS=>8
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }
    //获取所有APPS的名字
    public function apps(){
        return $this->encode(App::orderByDesc('popular')->get(['id','name','popular','time']));
    }
    //获取config配置相关信息
    public function configs(){
        return $this->encode(Config::get());
    }
    public function navigations(){
        return $this->encode(Navigation::get());
    }
    public function encode($data,$msg='获取数据成功',$code=200){
        $array['code']=$code;
        $array['msg']=$msg;
        $array['data']=$data;
        return \response()->json($array)->setExpires(new \DateTime(date(DATE_RFC7231, time() + 86400)))->setClientTtl(86400);
    }
}
