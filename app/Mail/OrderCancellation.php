<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderCancellation extends Mailable
{
    use Queueable, SerializesModels;

    public $emailContent;

    public function __construct($emailContent)
    {
        $this->emailContent = $emailContent;
    }

    public function build()
    {
        return $this->subject('Order Cancelled - Orter Clothing')
                    ->html($this->emailContent);
    }
}