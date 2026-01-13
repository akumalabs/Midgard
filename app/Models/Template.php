<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Template extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_group_id',
        'name',
        'vmid',
        'visible',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'visible' => 'boolean',
            'order' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Template $template) {
            $template->uuid = $template->uuid ?? Str::uuid()->toString();
        });
    }

    /**
     * Get the template group this template belongs to.
     */
    public function templateGroup(): BelongsTo
    {
        return $this->belongsTo(TemplateGroup::class);
    }

    /**
     * Get the node this template is on (through template group).
     */
    public function node(): BelongsTo
    {
        return $this->templateGroup->node();
    }
}
