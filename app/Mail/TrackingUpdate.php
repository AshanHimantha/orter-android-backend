<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TrackingUpdate extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function build()
    {
        return $this->subject('Your Order Has Been Shipped')
            ->html($this->generateEmailContent());
    }

    private function generateEmailContent()
    {
        return '
        <style>
            body {
                font-family: Arial, sans-serif;
                background-color: white;
                margin: 0;
                padding: 5px;
                font-family: "Poppins", sans-serif;
            }
        </style>
        <body>
            <div style="max-width: 600px; margin: 20px auto; background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1);">
                <div style="text-align: center; margin-bottom: 20px;">
                    <img src="https://orterclothing.com/assets/orterlogo.png" alt="Orter Logo" style="max-width: 150px;">
                </div>
                <div style="text-align: center; margin-bottom: 30px;">
                    <h1 style="color: #333;">Your Order Has Been Shipped!</h1>
                    <p>Hello ' . $this->data['name'] . ',</p>
                    <p>Your order #' . $this->data['order_number'] . ' has been shipped via ' . $this->data['service'] . '.</p>
                    <p>Tracking Number: <strong>' . $this->data['tracking_number'] . '</strong></p>
                </div>

<div style="text-align: center;">
    <a href="   ' .$this->data['courier'].$this->data['tracking_number'] . '" style="background-color: #000000; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none;">Track Your Order</a>
</div>

                <div style="margin-top: 30px; text-align: center; color: #666;">
                    <p>If you have any questions, please contact our customer service.</p>
                </div>
            </div>
            <div style="text-align: center; margin-top: 20px; font-size: 12px; color: #666;">
                © 2024 Orter Clothing. All rights reserved.
            </div>
        </body>';
    }
}