<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Models;

use GraystackIT\Ahasend\Data\Route as RouteData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An inbound message route provisioned on the Ahasend account.
 *
 * Each route carries its own signing secret, which Ahasend returns only once —
 * when the route is created. That secret is what the inbound endpoint verifies
 * incoming payloads against, which is why the endpoint takes the route id in
 * its path rather than sharing one account-wide secret.
 *
 * @property int         $id
 * @property string      $public_id
 * @property string      $ahasend_route_id
 * @property int|null    $ahasend_domain_id
 * @property string      $name
 * @property string      $recipient
 * @property string      $url
 * @property string|null $secret
 * @property bool        $attachments
 * @property bool        $headers
 * @property bool        $strip_replies
 * @property bool        $group_by_message_id
 * @property bool        $enabled
 * @property Carbon      $created_at
 * @property Carbon      $updated_at
 */
class AhasendRoute extends Model
{
    protected $table = 'ahasend_routes';

    protected $fillable = [
        'public_id',
        'ahasend_route_id',
        'ahasend_domain_id',
        'name',
        'recipient',
        'url',
        'secret',
        'attachments',
        'headers',
        'strip_replies',
        'group_by_message_id',
        'enabled',
    ];

    protected $hidden = [
        'secret',
    ];

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'secret'              => 'encrypted',
            'attachments'         => 'boolean',
            'headers'             => 'boolean',
            'strip_replies'       => 'boolean',
            'group_by_message_id' => 'boolean',
            'enabled'             => 'boolean',
        ];
    }

    /**
     * Bind by the opaque public id, never by the auto-increment key.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * The domain this route was provisioned for.
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(AhasendDomain::class, 'ahasend_domain_id');
    }

    /**
     * Copy the API representation onto this record without saving.
     *
     * The secret is only written when the API actually returned one, so a later
     * fetch never clears the copy stored at creation time.
     */
    public function fillFromApi(RouteData $data): self
    {
        $this->fill([
            'ahasend_route_id'    => $data->id,
            'name'                => $data->name,
            'recipient'           => $data->recipient,
            'url'                 => $data->url,
            'attachments'         => $data->attachments,
            'headers'             => $data->headers,
            'strip_replies'       => $data->stripReplies,
            'group_by_message_id' => $data->groupByMessageId,
            'enabled'             => $data->enabled,
        ]);

        if ($data->secret !== null) {
            $this->secret = $data->secret;
        }

        return $this;
    }
}
