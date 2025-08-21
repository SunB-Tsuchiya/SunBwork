<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Compatibility model for older DiaryAttachment references.
 * Uses the unified 'attachments' table so existing code can continue
 * to call App\Models\DiaryAttachment::create([...]) without error.
 */
class DiaryAttachment extends Attachment
{
    use HasFactory;

    // Inherit $fillable and behavior from App\Models\Attachment.
    // Keep explicit table mapping to be explicit about legacy name.
    protected $table = 'attachments';
}

