<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class App extends Model
{
    use HasFactory;
    public $timestamps=false;
    protected $fillable = [
        'name',
        'popular',
        'author',
        'ip',
        'weights',
        'nogood',
        'good',
        'image',
        'time'
    ];
    public function navigations():BelongsToMany{
        return $this->belongsToMany(Navigation::class)->withPivot('version','size','newdate','url','content','log','operatingsystem');
    }
    public function classifications():BelongsToMany{
        return $this->belongsToMany(Classification::class);
    }
    public function news():HasMany{
        return $this->hasMany(News::class)->orderBy('time', 'desc');
    }
}
