<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Icd9cmCode extends Model
{
    protected $fillable = [
        'kode',
        'deskripsi_id',
        'deskripsi_en',
        'kategori',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
