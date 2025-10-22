<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Attachment model
 *
 * Provides convenience helpers to attach/detach this Attachment to any
 * attachable model via the `attachmentables` polymorphic pivot table.
 */
class Attachment extends Model
{
    protected $fillable = [
        'path',
        'original_name',
        'mime_type',
        'status',
        'size',
        'user_id',
        'created_by',
    ];


    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    // Polymorphic many-to-many: models that 'use' attachments
    public function diaries()
    {
        return $this->morphedByMany(\App\Models\Diary::class, 'attachable', 'attachmentables');
    }

    public function events()
    {
        return $this->morphedByMany(\App\Models\Event::class, 'attachable', 'attachmentables');
    }

    public function messages()
    {
        return $this->morphedByMany(\App\Models\Message::class, 'attachable', 'attachmentables');
    }

    /**
     * Attach this Attachment to any Eloquent model via the attachmentables pivot.
     * Accepts either a Model instance or a class name + id combination.
     *
     * @param \Illuminate\Database\Eloquent\Model|object|string $model
     * @param int|null $id optional id when passing class name as first arg
     * @param array $meta optional pivot metadata (role, order, etc.)
     * @return bool true if inserted (or already existed)
     */
    public function attachTo($model, $id = null, array $meta = [])
    {
        if (is_string($model) && $id) {
            $attachableType = $model;
            $attachableId = intval($id);
        } elseif (is_object($model) && method_exists($model, 'getKey')) {
            $attachableType = get_class($model);
            $attachableId = $model->getKey();
        } else {
            return false;
        }

        $now = Carbon::now();
        $row = array_merge([
            'attachment_id' => $this->getKey(),
            'attachable_type' => $attachableType,
            'attachable_id' => $attachableId,
            'created_at' => $now,
            'updated_at' => $now,
        ], $meta ?: []);

        try {
            $inserted = DB::table('attachmentables')->insertOrIgnore([$row]);
            return (bool)$inserted;
        } catch (\Throwable $e) {
            logger()->warning('Attachment::attachTo failed', ['error' => $e->getMessage(), 'row' => $row]);
            return false;
        }
    }

    /**
     * Remove pivot rows linking this attachment to the given model/class+id
     *
     * @param \Illuminate\Database\Eloquent\Model|object|string $model
     * @param int|null $id
     * @return int number of deleted rows
     */
    public function detachFrom($model, $id = null)
    {
        if (is_string($model) && $id) {
            $attachableType = $model;
            $attachableId = intval($id);
        } elseif (is_object($model) && method_exists($model, 'getKey')) {
            $attachableType = get_class($model);
            $attachableId = $model->getKey();
        } else {
            return 0;
        }

        try {
            return DB::table('attachmentables')
                ->where('attachment_id', $this->getKey())
                ->where('attachable_type', $attachableType)
                ->where('attachable_id', $attachableId)
                ->delete();
        } catch (\Throwable $e) {
            logger()->warning('Attachment::detachFrom failed', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Check whether this attachment is linked to the given model/class+id
     *
     * @param \Illuminate\Database\Eloquent\Model|object|string $model
     * @param int|null $id
     * @return bool
     */
    public function isAttachedTo($model, $id = null)
    {
        if (is_string($model) && $id) {
            $attachableType = $model;
            $attachableId = intval($id);
        } elseif (is_object($model) && method_exists($model, 'getKey')) {
            $attachableType = get_class($model);
            $attachableId = $model->getKey();
        } else {
            return false;
        }

        try {
            return DB::table('attachmentables')
                ->where('attachment_id', $this->getKey())
                ->where('attachable_type', $attachableType)
                ->where('attachable_id', $attachableId)
                ->exists();
        } catch (\Throwable $e) {
            logger()->warning('Attachment::isAttachedTo failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
