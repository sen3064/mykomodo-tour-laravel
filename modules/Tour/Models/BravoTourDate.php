<?php

namespace Modules\Tour\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BravoTourDate extends Model
{
    use HasFactory;

    protected $table = 'bravo_tour_dates';

    protected $fillable = [
        'target_id',
        'price_date',
        'active',
    ];

    public function tour()
    {
        return $this->belongsTo(Tour::class, 'target_id');
    }
}
