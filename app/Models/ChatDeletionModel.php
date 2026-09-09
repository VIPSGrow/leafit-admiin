<?php

namespace App\Models;

use CodeIgniter\Model;

class ChatDeletionModel extends Model
{
    protected $table = 'chat_deletions';
    protected $primaryKey = 'id';
    protected $useTimestamps = false;
    protected $allowedFields = ['user_id', 'e_id', 'booking_id', 'chat_id', 'created_at'];

    /**
     * Conversation-level "delete for me": hides every message in this thread,
     * as it exists right now, for $userId only. Upserts a single marker row
     * per (user_id, e_id, booking_id) so repeated deletes don't grow the table —
     * each call just moves the cutoff forward to "now".
     */
    public function clearConversationForUser(int $userId, int $eId, ?int $bookingId): void
    {
        $builder = $this->where('user_id', $userId)
            ->where('e_id', $eId)
            ->where('chat_id', null);

        if ($bookingId === null) {
            $builder->where('booking_id', null);
        } else {
            $builder->where('booking_id', $bookingId);
        }

        $existing = $builder->first();
        $now = date('Y-m-d H:i:s');

        if ($existing) {
            $this->update($existing['id'], ['created_at' => $now]);
            return;
        }

        $this->insert([
            'user_id'    => $userId,
            'e_id'       => $eId,
            'booking_id' => $bookingId,
            'chat_id'    => null,
            'created_at' => $now,
        ]);
    }
}

