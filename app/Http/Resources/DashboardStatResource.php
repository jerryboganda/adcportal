<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardStatResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'total_business' => (int) ($this->resource['total_business'] ?? 0),
            'total_appointment' => (int) ($this->resource['total_appointment'] ?? 0),
            'total_revenue' => $this->resource['total_revenue'] ?? '0',
            'business_url' => $this->resource['business_url'] ?? null,
            'chart' => $this->resource['chart'] ?? [],
        ];
    }
}
