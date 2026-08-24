<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->Category?->name,
            'category_id' => $this->category_id,
            'image' => check_file($this->image) ? get_file($this->image) : get_file('uploads/default/avatar.png'),
            'price' => currency_format_with_sym($this->price, $this->created_by, $this->business_id),
            'raw_price' => (float) $this->price,
            'duration' => $this->duration,
            'description' => $this->description ?: '',
            'is_free' => (bool) $this->is_free,
        ];
    }
}
