<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeetingRoom extends Model
{
    protected $fillable = ['company_id', 'name', 'capacity', 'description', 'color', 'active', 'sort_order', 'available_from', 'available_to'];

    protected $casts = ['active' => 'boolean'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function reservations()
    {
        return $this->hasMany(RoomReservation::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
