<?php

namespace App\Services;

use App\Models\AiConversation;
use App\Models\AiMessage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AiHistoryConsolidator
{
    /**
     * Run consolidation.
     * Options: dry (bool), threshold_minutes (int)
     * Returns summary array
     */
    public function run(array $options = [])
    {
        $dry = $options['dry'] ?? true;
        $threshold = $options['threshold_minutes'] ?? 30;

        // strategy: group by user_id and title prefix (first 20 chars). Within each group,
        // sort by created_at and if two conversations are within threshold minutes, merge
        // later conversation messages into the earliest and delete the later conversation.

        $groupsFound = 0;
        $merged = 0;

        // fetch conversations with minimal columns
        $convs = AiConversation::select('id', 'user_id', 'title', 'created_at')
            ->orderBy('user_id')
            ->orderBy('title')
            ->orderBy('created_at')
            ->get();

        // index by user and title prefix
        $buckets = [];
        foreach ($convs as $c) {
            $prefix = mb_substr($c->title ?? '', 0, 20);
            $key = ($c->user_id ?: 0) . '::' . $prefix;
            $buckets[$key][] = $c;
        }

        foreach ($buckets as $key => $list) {
            if (count($list) <= 1) continue;
            // walk list and merge adjacent ones within threshold
            $groupsFound++;
            $base = array_shift($list);
            foreach ($list as $later) {
                $baseTime = Carbon::parse($base->created_at);
                $laterTime = Carbon::parse($later->created_at);
                $diff = $laterTime->diffInMinutes($baseTime);
                if ($diff <= $threshold) {
                    // merge later into base
                    if (!$dry) {
                        DB::transaction(function () use ($base, $later, &$merged) {
                            // move messages
                            AiMessage::where('ai_conversation_id', $later->id)
                                ->update(['ai_conversation_id' => $base->id]);
                            // delete later conversation
                            AiConversation::where('id', $later->id)->delete();
                            $merged++;
                        });
                    } else {
                        $merged++;
                    }
                } else {
                    // set new base for next window
                    $base = $later;
                }
            }
        }

        return ['groups_found' => $groupsFound, 'merged_count' => $merged];
    }
}
