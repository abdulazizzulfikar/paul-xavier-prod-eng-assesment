<?php

namespace App\Models;

use App\Enums\GenerationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money columns are integer nanodollars (1 USD = 1,000,000,000).
 *
 * @property string $id
 * @property int $user_id
 * @property string|null $pixelforge_id
 * @property GenerationStatus $status
 * @property string $prompt
 * @property string $size
 * @property string $quality
 * @property array<int, string> $reference_images
 * @property int $estimated_min_nano
 * @property int $estimated_max_nano
 * @property array<string, string>|null $rates
 * @property array<string, int>|null $usage
 * @property int|null $provider_cost_nano
 * @property int|null $charged_nano
 * @property string|null $image_url
 * @property string|null $error_code
 * @property string|null $error_message
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Generation extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => GenerationStatus::class,
            'reference_images' => 'array',
            'rates' => 'array',
            'usage' => 'array',
            'estimated_min_nano' => 'integer',
            'estimated_max_nano' => 'integer',
            'provider_cost_nano' => 'integer',
            'charged_nano' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
