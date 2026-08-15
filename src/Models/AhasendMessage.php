<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Persists outgoing email details and inbound webhook status updates.
 *
 * @property int             $id
 * @property string          $message_id
 * @property string          $recipient
 * @property string          $subject
 * @property string          $status
 * @property array           $payload
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 */
class AhasendMessage extends Model
{
    protected $table = 'ahasend_messages';

    protected $fillable = [
        'message_id',
        'recipient',
        'subject',
        'status',
        'payload',
    ];

    public function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
