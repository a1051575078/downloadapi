<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Navigation extends Model
{
    use HasFactory;
    public $timestamps=false;
    protected $fillable = [
        'name',
        'route'
    ];
    //应用软件分类所拥有的app信息
    public function apps():BelongsToMany{
        return $this->belongsToMany(App::class)->as('navigations')->withPivot('version','size','newdate')->orderByPivot('newdate', 'desc');
    }
    public function classifications():HasMany{
        return $this->hasMany(Classification::class);
    }
    public function apps40():BelongsToMany{
        return $this->belongsToMany(App::class)->as('navigations')->withPivot('version','size','newdate')->orderByPivot('newdate', 'desc')->limit(40);
    }
    public function apps10():BelongsToMany{
        return $this->belongsToMany(App::class)->as('navigations')->withPivot('version','size','newdate')->orderBy('popular', 'desc')->limit(10);
    }
}
