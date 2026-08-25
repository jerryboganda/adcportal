<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScreeningQuestion extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'screening_form_id', 'question_text', 'help_text', 'answer_type',
        'options', 'risk_value', 'is_risk_blocking', 'sort_order',
    ];

    protected $casts = [
        'options' => 'array',
        'is_risk_blocking' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function form()
    {
        return $this->belongsTo(ScreeningForm::class, 'screening_form_id');
    }

    /** Does the given answer raise the risk flag? */
    public function flagsRisk(?string $answer): bool
    {
        if ($answer === null || $answer === '' || $this->risk_value === null) {
            return false;
        }

        return strcasecmp(trim($answer), trim($this->risk_value)) === 0;
    }
}
