<?php

namespace App\Mail;

use App\Models\Business;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CommonEmailTemplate extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;
    public $template;
    public $user_id;
    public $business_id;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($template,$user_id,$business_id)
    {
        $this->template = $template;
        $this->user_id = $user_id;
        $this->business_id = $business_id;
    }
    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $business   = Business::where('id', $this->business_id)->first();
        return  $this->from(company_setting('mail_from_address',$this->user_id,$this->business_id), $this->template->from)
                ->markdown('email.common_email_template')
                ->subject($this->template->subject)
                ->with([
                'content' => $this->template->content,
                'business' => $business,
            ]);
    }
}
