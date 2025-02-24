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
            <h1 style="color: #333;">Order Delivered!</h1>
            <p>Hello {{ $orderData['name'] }},</p>
            <p>Your order #{{ $orderData['order_number'] }} has been delivered successfully.</p>
        </div>
        <div style="background-color: #f8f8f8; padding: 20px; border-radius: 5px;">
            <h3 style="margin-top: 0;">Delivery Details</h3>
            <p><strong>Delivery Address:</strong><br>
               {{ $orderData['delivery_address'] }}<br>
               {{ $orderData['delivery_city'] }}</p>
        </div>
        <div style="margin-top: 30px; text-align: center; color: #666;">
            <p>Thank you for shopping with Orter Clothing!</p>
            <p>If you have any questions, please contact our customer service.</p>
        </div>
    </div>
    <div style="text-align: center; margin-top: 20px; font-size: 12px; color: #666;">
        © 2024 Orter Clothing. All rights reserved.
    </div>
</body>