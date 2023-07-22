<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Classification extends Model{
    use HasFactory;
    public $timestamps=false;
    protected $fillable = [
        'navigation_id',
        'name'
    ];
    //应用软件分类所拥有的app信息
    public function apps():BelongsToMany{
        return $this->belongsToMany(App::class)->as('classifications');
    }
    public function navigation():BelongsTo{
        return $this->belongsTo(Navigation::class);
    }
    public function navigationone():HasOne{
        return $this->hasOne(Navigation::class);
    }
}
