<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public $bodyContent;

    public function __construct($bodyContent)
    {
        $this->bodyContent = $bodyContent;
    }

    public function build()
    {
        return $this->subject('Order Confirmation - Orter Clothing')
                    ->html($this->bodyContent);
    }
}
