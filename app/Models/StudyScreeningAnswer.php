<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudyScreeningAnswer extends Model
{
    protected $fillable = [
        'appointment_id', 'screening_question_id', 'answer_value',
        'is_risk', 'override_reason', 'answered_by',
    ];

    protected $casts = ['is_risk' => 'boolean'];

    public function question()
    {
        return $this->belongsTo(ScreeningQuestion::class, 'screening_question_id');
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }
}
