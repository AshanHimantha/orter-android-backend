<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Cart;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Http\Controllers\NotificationController;

class OrderController extends Controller
{
    protected $notificationController;

    public function __construct(NotificationController $notificationController)
    {
        $this->notificationController = $notificationController;
    }

    public function index(Request $request)
    {
        $firebaseUid = $request->firebase_uid;
        
        $orders = Order::where('firebase_uid', $firebaseUid)
            ->with(['items', 'branch','courier'])
            ->latest()
            ->get();

        return response()->json([
            'status' => true,
            'data' => $orders
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'delivery_type' => 'required|in:delivery,pickup',
            'branch_name' => 'required_if:delivery_type,pickup|string',  // Changed from branch_id
            'delivery_name' => 'required',
            'delivery_phone' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:10',
            'delivery_address' => 'required_if:delivery_type,delivery',
            'delivery_city' => 'required_if:delivery_type,delivery',
            'payment_method' => 'required|in:cash,card',
            'email' => 'required|email'  // Add this line
        ]);

        $firebaseUid = $request->firebase_uid;

        // Find branch by name if pickup delivery type
        $branchId = null;
        if ($request->delivery_type === 'pickup') {
            $branch = Branch::where('name', $request->branch_name)->first();
            if (!$branch) {
                return response()->json([
                    'status' => false,
                    'message' => 'Branch not found'
                ], 404);
            }
            $branchId = $branch->id;
        }
        
        $cartItems = Cart::where('firebase_uid', $firebaseUid)
            ->with(['stock.product'])
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Cart is empty'
            ], 400);
        }

        // Generate pickup ID if needed
        $pickupId = null;
        if ($request->delivery_type === 'pickup') {
            $pickupId = 'PU-' . strtoupper(Str::random(6));
        }

        // Calculate total weight and shipping
        $totalWeight = $cartItems->sum(function($item) {
            return $item->quantity * $item->stock->product->weight;
        });

        $weightInKg = $totalWeight / 1000;
        
        // Calculate shipping with base charge 400 and 100 per extra kg
        $courier = \App\Models\curriers::where('is_active', true)->first();
        $shippingFee = 400; // Base charge

        if ($weightInKg > 1) {
            $extraKgs = ceil($weightInKg - 1);
            $shippingFee += ($extraKgs * 100); // 100 per extra kg
        }

        // Set shipping fee to 0 if pickup
        if ($request->delivery_type === 'pickup') {
            $shippingFee = 0;
        }

        $order = Order::create([
            'order_number' => 'ORD-' . strtoupper(Str::random(8)),
            'pickup_id' => $pickupId,
            'firebase_uid' => $firebaseUid,
            'delivery_type' => $request->delivery_type,
            'branch_id' => $branchId,  // Use the found branch ID
            'delivery_name' => $request->delivery_name,
            'delivery_phone' => $request->delivery_phone,
            'delivery_address' => $request->delivery_address,
            'delivery_city' => $request->delivery_city,
            'payment_method' => $request->payment_method,
            'payment_status' => 'pending',
            'status' => 'pending',
            'email' => $request->email  // Add this line
        ]);

        $subTotal = 0;
        foreach ($cartItems as $item) {
            $itemTotal = $item->quantity * $item->stock->product->price;
            $subTotal += $itemTotal;
            
            OrderItem::create([
                'order_id' => $order->id,
                'stock_id' => $item->stock_id,
                'product_name' => $item->stock->product->name,
                'product_image' => $item->stock->product->main_image,
                'size' => $item->size,
                'quantity' => $item->quantity,
                'selling_price' => $item->stock->product->price,
                'cost_price' => $item->stock->product->cost_price ?? 0,
                'total' => $itemTotal
            ]);

            // Decrease stock quantity
            $stock = $item->stock;
            $sizeColumn = strtolower($item->size) . '_quantity';
            $stock->$sizeColumn -= $item->quantity;
            $stock->save();
        }

        // Clear cart after order creation
        // Cart::where('firebase_uid', $firebaseUid)->delete();

        return response()->json([
            'status' => true,
            'message' => 'Order created successfully',
            'data' => [
                'order' => $order->load(['items', 'branch']),
                'summary' => [
                    'subtotal' => (int)$subTotal,
                    'shipping_fee' => (int)$shippingFee,
                    'total' => (int)($subTotal + $shippingFee)
                ]
            ]
        ], 201);
    }

    public function show(Order $order)
    {
        // Load order relations
        $order->load(['items.stock.product', 'branch', 'courier']);

        // Calculate shipping fee using the private method
        $shippingFee = $this->calculateShippingFee($order);

        // Calculate subtotal from order items
        $subtotal = $order->items->sum('total');

        return response()->json([
            'status' => true,
            'data' => [
                'order' => $order,
                'summary' => [
                    'subtotal' => $subtotal,
                    'shipping_fee' => $shippingFee,
                    'total' => $subtotal + $shippingFee
                ]
            ]
        ]);
    }

    public function updateStatus(Order $order, Request $request)
    {
        $request->validate([
            'status' => 'required|in:pending,confirmed,processing,shipped,delivered,ready_for_pickup,picked_up,completed,cancelled,returned'
        ]);

        $order->update([
            'status' => $request->status
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Order status updated successfully',
            'data' => $order->fresh()
        ]);
    }

       public function destroy(Order $order)
    {
        if ($order->payment_status === 'paid') {
            return response()->json([
                'status' => false,
                'message' => 'Paid orders cannot be deleted'
            ], 403);
        }
    
        $order->delete();
    
        return response()->json([
            'status' => true,
            'message' => 'Order deleted successfully'
        ]);
    }


    public function updatePaymentStatus(Request $request)
    {
        try {
            // Log the entire request for debugging
            Log::info('PayHere Callback Data:', $request->all());
            $paymentData = json_encode($request->all(), JSON_PRETTY_PRINT);
            // Validate the request with order_id matching order_number
            $request->validate([
                'order_id' => 'required|exists:orders,order_number',
                'merchant_id' => 'required',
                'payhere_amount' => 'required|numeric',
                'payhere_currency' => 'required|string',
                'status_code' => 'required|integer',
                'md5sig' => 'required|string',
                'payment_id' => 'required|string',
                
            ]);

            // Find order by order_number instead of id
            $order = Order::where('order_number', $request->order_id)->first();

            if (!$order) {
                Log::error('Order not found:', ['order_number' => $request->order_id]);
                return response()->json([
                    'status' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            // Verify MD5 signature
            $merchant_secret = env('PAYHERE_MERCHANT_SECRET');
            $md5sig = strtoupper(md5(
                $request->merchant_id .
                $request->order_id .
                $request->payhere_amount .
                $request->payhere_currency .
                $request->status_code .
                strtoupper(md5($merchant_secret))
            ));

            if ($md5sig != $request->md5sig) {
                Log::error('MD5 Signature Mismatch! Possible fraud.');
                return response()->json([
                    'status' => false,
                    'message' => 'MD5 signature mismatch'
                ], 400);
            }

            if ($request->status_code == 2) { // Payment Success
                // Update order status
                $order->update([
                    'transaction_id' => $request->payment_id,
                    'payment_status' => 'paid',
                    'status' => 'confirmed',
                    'notes' => $paymentData
                ]);

                // Send confirmation email
                try {
                    $orderItems = $order->items()->with('stock.product')->get();
                    
                    // Calculate total weight and shipping
                    $totalWeight = $orderItems->sum(function($item) {
                        return $item->quantity * $item->stock->product->weight;
                    });

                    $weightInKg = $totalWeight / 1000;
                    
                    // Get base charge from curriers table
                    $courier = \App\Models\curriers::where('is_active', true)->first();
                    $baseCharge = $courier ? $courier->price : 400; // Default to 400 if no active courier
                    
                    // Calculate shipping with base charge and 100 per extra kg
                    $shippingFee = $order->delivery_type === 'pickup' ? 0 : $baseCharge;

                    if ($order->delivery_type === 'delivery' && $weightInKg > 1) {
                        $extraKgs = ceil($weightInKg - 1);
                        $shippingFee += ($extraKgs * 100); // 100 per extra kg
                    }

                    // Get branch name if it's a pickup order
                    $branchName = '';
                    if ($order->delivery_type === 'pickup' && $order->branch_id) {
                        $branch = Branch::find($order->branch_id);
                        $branchName = $branch ? $branch->name : '';
                    }

                    $total = 0;
                    $productsHtml = '';
                    
                    foreach ($orderItems as $item) {
                        $itemTotal = $item->quantity * $item->selling_price;
                        $total += $itemTotal;
                        
                        $productsHtml .= '
                        <tr>
                            <td style="padding: 10px; border-bottom: 1px solid #ddd; color: #333;">
                                <div style="display: flex;">
                                    <div>
                                        <img src="https://testapi.ashanhimantha.com/storage/' . $item->product_image . '" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">
                                    </div>
                                    <div style="margin-left: 10px;">
                                        <div>' . $item->product_name . ' × ' . $item->quantity . '</div>
                                        <div style="text-decoration: none; color: gray;">' . $item->size . '</div>
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: right; color: #333;">Rs. ' . $itemTotal . '</td>
                        </tr>';
                    }

                    $shippingFee = $order->delivery_type === 'pickup' ? 0 : 400;
                    $orderData = [
                        'name' => $order->delivery_name,
                        'address1' => $order->delivery_address,
                        'town' => $order->delivery_type === 'pickup' ? $branchName : $order->delivery_city,
                        'zip' => '',
                        'country' => 'Sri Lanka',
                        'order_id' => $order->order_number,
                        'date' => $order->created_at->format('Y-m-d'),
                        'method' => ucfirst($order->payment_method),
                        'delivery_type' => $order->delivery_type,
                        'pickup_id' => $order->pickup_id
                    ];

                    $bodyContent = $this->generateEmailTemplate($orderData, $productsHtml, $total, $shippingFee);

                    try {
                        // Use the email stored in the order
                        if ($order->email) {
                            Mail::to($order->email)->send(new \App\Mail\OrderConfirmation($bodyContent));
                            
                            Log::info('Order confirmation email sent successfully', [
                                'order_number' => $order->order_number,
                                'email' => $order->email
                            ]);
                        } else {
                            Log::error('Email not found for order', [
                                'order_number' => $order->order_number
                            ]);
                      }
                    } catch (\Exception $e) {
                        Log::error('Error sending email', [
                            'order_number' => $order->order_number,
                            'email' => $order->email,
                            'error' => $e->getMessage()
                        ]);
                    }

                    Log::info('Order confirmation email sent successfully', [
                        'order_number' => $order->order_number,
                        'email' => $order->email
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to send order confirmation email', [
                        'order_number' => $order->order_number,
                        'error' => $e->getMessage()
                    ]);
                }

                // Clear cart items and log the operation
                $deletedCount = Cart::where('firebase_uid', $order->firebase_uid)->delete();
                Log::info('Cart items deleted:', [
                    'order_number' => $order->order_number,
                    'firebase_uid' => $order->firebase_uid,
                    'items_deleted' => $deletedCount
                ]);

                return response()->json([
                    'status' => true,
                    'message' => 'Payment completed successfully'
                ]);
            
            } else { // Failed or Cancelled
                // Restore stock quantities before deleting the order
                foreach ($order->items as $item) {
                    $stock = $item->stock;
                    $sizeColumn = strtolower($item->size) . '_quantity';
                    $stock->$sizeColumn += $item->quantity;
                    $stock->save();
                }

                // Delete order items and order
                $order->items()->delete();
                $order->delete();

                Log::info('Order deleted due to failed payment:', [
                    'order_number' => $order->order_number,
                    'payment_status_code' => $request->status_code
                ]);

                return response()->json([
                    'status' => false,
                    'message' => 'Payment failed, order removed'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Payment processing error:', [
                'message' => $e->getMessage(),
                'order_id' => $request->order_id ?? null
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    private function generateEmailTemplate($order, $productsHtml, $total, $shippingFee)
    {
        // Determine if it's pickup or delivery
        $isPickup = isset($order['delivery_type']) && $order['delivery_type'] === 'pickup';
        
        // Change title and message based on delivery type
        $title = $isPickup ? 'Your Order is Ready for Pickup!' : 'Your Order is Being Shipped!';
        $message = $isPickup ? 'Your order has been confirmed and will be ready for pickup soon.' : 'Thanks a lot for your purchase.';

        // Generate shipping/pickup details section
        $detailsSection = $isPickup ? '
            <div style="width: 65%; border-width: 1px; border: 1px solid #ddd; padding: 10px; border-radius: 5px; margin: 3px;">
                <div style="font-weight: 600;font-size: medium;">Pickup Details</div>
                <hr style="border-top: #333;">
                <div><span style="font-size: 10px; color: gray;"> Name</span><br><span>' . $order['name'] . '</span></div>
                <div><span style="font-size: 10px; color: gray;"> Branch</span><br><span>' . $order['town'] . '</span></div>
                <div><span style="font-size: 10px; color: gray;"> Pickup ID</span><br><span>' . $order['pickup_id'] . '</span></div>
            </div>' : '
            <div style="width: 65%; border-width: 1px; border: 1px solid #ddd; padding: 10px; border-radius: 5px; margin: 3px;">
                <div style="font-weight: 600;font-size: medium;">Shipping Details</div>
                <hr style="border-top: #333;">
                <div><span style="font-size: 10px; color: gray;"> Name</span><br><span>' . $order['name'] . '</span></div>
                <div><span style="font-size: 10px; color: gray;"> Address</span><br><span>' . $order['address1'] . '</span><br><span>' . $order['town'] . '</span>, <span>' . $order['zip'] . '</span><br><span>' . $order['country'] . '</span></div>
            </div>';

        return '
        <style>
            body {
                font-family: Arial, sans-serif;
                background-color: white;
                margin: 0;
                padding: 5px;
                font-family: "Poppins", sans-serif;
                font-weight: 400;
                font-style: normal;
            }
        </style>

        <body>
        <div style="max-width: 600px; margin: 20px auto; background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1);">
            <div style="text-align: center; margin-bottom: 20px;">
                <img src="https://orterclothing.com/assets/orterlogo.png" alt="Orter Logo" style="max-width: 150px; margin-bottom: 10px;">
            </div>
            <div style="width: 100%; height: 200px;">
                <img src="https://orterclothing.com/assets/original-54e6c1d18d61f8f8aa2ed95caaf197ae.gif" style="width: 100%; height: 100%; object-fit: cover;">
            </div>
            <div style="text-align: center;font-size: 15px;font-weight: 600;">
                <h1>' . $title . '</h1>
                <p style="margin-top: -20px; font-weight: 400;">Hey ' . $order['name'] . '! ' . $message . '</p>
            </div>
            <div style="display: flex; justify-content: space-between; padding-bottom: 10px;">
                ' . $detailsSection . '
                <div style="width: 35%; border-width: 1px; border: 1px solid #ddd; padding: 10px; border-radius: 5px; margin: 3px;">
                    <div style="font-weight: 600;font-size: medium;">Order Details</div>
                    <hr style="border-top: #333;">
                    <div><span style="font-size: 10px; color: gray;"> Order :</span><br><span>' . $order['order_id'] . '</span></div>
                    <div><span style="font-size: 10px; color: gray;"> Date :</span><br><span>' . $order['date'] . '</span></div>
                    <div><span style="font-size: 10px; color: gray;"> Payment :</span><br><span class="font-medium ">' . $order['method'] . '</span></div>
                </div>
            </div>
            <!-- ... rest of the template remains the same ... -->
            ' . $this->getOrderSummarySection($productsHtml, $total, $shippingFee) . '
        </div>
        <div style="width: 100%; text-align: center; margin-top: 50px; font-size: 10px; "><a href="https://orterclothing.com" style="text-decoration: none; color: gray;" target="_blank" >© 2024 Orter Clothing. All rights reserved. </a></div>
        </body>';
    }

    private function getOrderSummarySection($productsHtml, $total, $shippingFee)
    {
        return '
        <div style="text-align: center;font-size: 15px;font-weight: 500;">
            <div style="background-color: #333; color: #f4f4f4; padding-top: 5px; padding-bottom: 5px; width: 100%; border-top-right-radius: 3px; border-top-left-radius: 3px; font-size: 15px;"><span>Order Summary</span></div>
        </div>
        <table style="width: 100%; border-collapse: collapse; border-width: 1px; border: 1px solid #ddd;">
            <tbody>
            ' . $productsHtml . '
            </tbody>
        </table>
        <div style="width:75%;margin-left:auto;margin-top:10px;">
            <div style="width: 80%; padding-left: 15%; display: flex;"> 
                <div style="width: 50%;">
                    <p>Sub Total :</p>
                    <p>Shipping :</p>
                    <hr style="border-top:1px solid #ddd; ">
                    <p style="font-size: 20px;">Total :</p>
                </div> 
                <div style="width: 50%;text-align: end;"> 
                    <p> Rs.' . $total . ' </p>
                    <p> Rs.' . $shippingFee . ' </p>
                    <hr style="border-top:1px solid #ddd; ">
                    <p style="font-weight:bold; font-size: 20px;"> Rs.' . ($total + $shippingFee) . '</p>
                </div>  
            </div>  
        </div>';
    }



    public function getUserOrders(Request $request)
    {
        try {
            // Validate firebase_uid
            $request->validate([
                'firebase_uid' => 'required|string'
            ]);
    
            $orders = Order::where('firebase_uid', $request->firebase_uid)
                ->with(['items' => function($query) {
                    $query->select(
                        'id', 
                        'order_id', 
                        'product_name', 
                        'product_image', 
                        'quantity', 
                        'selling_price',
                        'size',
                        'total'
                    );
                }, 'courier']) // Add courier relationship
                ->latest()
                ->get();
    
            if ($orders->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No orders found'
                ], 404);
            }
    
            // Format response
                       $response = [
                'status' => true,
                'data' => $orders->map(function($order) {
                    return [
                        'orderID' => (string)$order->id,
                        'orderNumber' => $order->order_number,
                        'orderDate' => $order->created_at->format('Y-m-d'),
                        'orderStatus' => $order->status,
                        'orderTotal' => (string)($order->items->sum('total') + $this->calculateShippingFee($order)),
                        'deliveryType' => $order->delivery_type,
                        'tracking' => $order->courier && $order->tracking_number ? 
                            $order->courier->description . $order->tracking_number : 
                            'empty',
                        'items' => $order->items->map(function($item) {
                            return [
                                'productName' => $item->product_name,
                                'productImage' => $item->product_image,
                                'quantity' => (string)$item->quantity,
                                'price' => (string)$item->selling_price,
                                'size' => $item->size
                            ];
                        })
                    ];
                })
            ];
    
            return response()->json($response);
    
        } catch (\Exception $e) {
            Log::error('Error fetching user orders:', [
                'firebase_uid' => $request->firebase_uid ?? 'not provided',
                'error' => $e->getMessage()
            ]);
    
            return response()->json([
                'status' => false,
                'message' => 'Error fetching orders'
            ], 500);
        }
    }

public function getAllOrders()
{
    try {
        $orders = Order::with(['items', 'branch'])
            ->latest()
            ->get()
            ->map(function($order) {
                // Calculate shipping fee
                $shippingFee = $this->calculateShippingFee($order);

                return [
                    'id' => $order->id,
                    'orderNumber' => $order->order_number,
                    'orderDate' => $order->created_at->format('Y-m-d H:i:s'),
                    'status' => $order->status,
                    'paymentStatus' => $order->payment_status,
                    'paymentMethod' => $order->payment_method,
                    'deliveryType' => $order->delivery_type,
                    'pickupId' => $order->pickup_id,
                    'customerDetails' => [
                        'name' => $order->delivery_name,
                        'phone' => $order->delivery_phone,
                        'email' => $order->email,
                        'address' => $order->delivery_address,
                        'city' => $order->delivery_city,
                    ],
                    'branch' => $order->branch ? [
                        'id' => $order->branch->id,
                        'name' => $order->branch->name
                    ] : null,
                    'items' => $order->items->map(function($item) {
                        return [
                            'productName' => $item->product_name,
                            'productImage' => $item->product_image,
                            'size' => $item->size,
                            'quantity' => $item->quantity,
                            'price' => $item->selling_price,
                            'total' => $item->total
                        ];
                    }),
                    'summary' => [
                        'subtotal' => $order->items->sum('total'),
                        'shippingFee' => $shippingFee,
                        'total' => $order->items->sum('total') + $shippingFee
                    ]
                ];
            });

        return response()->json([
            'status' => true,
            'data' => $orders
        ]);

    } catch (\Exception $e) {
        Log::error('Error fetching all orders:', [
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'status' => false,
            'message' => 'Error fetching orders',
            'error' => $e->getMessage()
        ], 500);
    }
}

// Create a private method to calculate shipping fee
private function calculateShippingFee($order, $totalWeight = null)
{
    // Return 0 if pickup delivery type
    if ($order->delivery_type === 'pickup') {
        return 0;
    }

    // Get base charge from active courier
    $courier = \App\Models\curriers::where('is_active', true)->first();
    
    if (!$courier) {
        return 0; // Return 0 if no active courier found
    }

    // Make sure order items are loaded with their related products
    if (!$order->relationLoaded('items')) {
        $order->load(['items.stock.product']);
    }

    // Calculate total weight if not provided
    if ($totalWeight === null) {
        $totalWeight = 0;
        foreach ($order->items as $item) {
            $weight = $item->stock->product->weight ?? 0;
            $totalWeight += ($weight * $item->quantity);
        }
    }

    // Convert to kg and calculate shipping
    $weightInKg = $totalWeight / 1000;
    $shippingFee = $courier->charge; // Base charge
    $extraKgs = 0; // Initialize extraKgs

    if ($weightInKg > 1) {
        $extraKgs = ceil($weightInKg - 1);
        $shippingFee += ($extraKgs * $courier->extra_per_kg);
    }

    Log::info('Shipping calculation details:', [
        'order_id' => $order->id,
        'total_weight_g' => $totalWeight,
        'weight_kg' => $weightInKg,
        'base_charge' => $courier->charge,
        'extra_kgs' => $extraKgs,
        'extra_charge' => $extraKgs * $courier->extra_per_kg,
        'total_shipping_fee' => $shippingFee
    ]);

    return $shippingFee;
}



public function cancelOrder(Request $request, $id)
{
    try {
        $order = Order::with(['items.stock', 'user'])->findOrFail($id);

        // Check if order can be cancelled
        $nonCancellableStatuses = ['delivered', 'completed', 'picked_up'];
        if (in_array($order->status, $nonCancellableStatuses)) {
            return response()->json([
                'status' => false,
                'message' => 'Order cannot be cancelled in its current status'
            ], 400);
        }

        DB::beginTransaction();

        // Update order status and set processed_by
        $order->update([
            'status' => 'cancelled',
            'processed_by' => $request->user()->id
        ]);

        // Restore stock quantities
        foreach ($order->items as $item) {
            $stock = $item->stock;
            if ($stock) {
                $sizeColumn = strtolower($item->size) . '_quantity';
                $stock->increment($sizeColumn, $item->quantity);
                
                Log::info('Stock restored:', [
                    'order_id' => $order->id,
                    'product' => $item->product_name,
                    'size' => $item->size,
                    'quantity' => $item->quantity
                ]);
            }
        }

        DB::commit();

        // Send cancellation email
        try {
            if ($order->email) {
                $orderData = [
                    'name' => $order->delivery_name,
                    'order_number' => $order->order_number,
                    'date' => $order->created_at->format('Y-m-d'),
                    'items' => $order->items->map(function($item) {
                        return [
                            'name' => $item->product_name,
                            'quantity' => $item->quantity,
                            'size' => $item->size,
                            'price' => $item->selling_price
                        ];
                    })
                ];

                $emailContent = $this->generateCancellationEmail($orderData);
                Mail::to($order->email)->send(new \App\Mail\OrderCancellation($emailContent));

                Log::info('Order cancellation email sent:', [
                    'order_number' => $order->order_number,
                    'email' => $order->email
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error sending cancellation email:', [
                'order_number' => $order->order_number,
                'error' => $e->getMessage()
            ]);
        }

        // Send FCM notification if user has FCM token
        if ($order->user && $order->user->fcm_token) {
            $this->notificationController->sendNotification(new Request([
                'token' => $order->user->fcm_token,
                'title' => 'Order Cancelled',
                'body' => "Your order #{$order->order_number} has been cancelled.",
                'data' => [
                    'orderId' => (string)$order->id,  // Convert to string explicitly
                    'orderNumber' => $order->order_number,
                    'status' => 'cancelled',
                    'type' => 'order_update'
                ]
            ]));
        }

        return response()->json([
            'status' => true,
            'message' => 'Order cancelled successfully',
            'data' => $order->fresh(['items', 'processedBy'])
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error cancelling order:', [
            'order_id' => $id,
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'status' => false,
            'message' => 'Error cancelling order'
        ], 500);
    }
}

private function generateCancellationEmail($orderData)
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
                <h1 style="color: #333;">Order Cancelled</h1>
                <p>Hello ' . $orderData['name'] . ',</p>
                <p>Your order #' . $orderData['order_number'] . ' has been cancelled.</p>
            </div>
            <div style="background-color: #f8f8f8; padding: 20px; border-radius: 5px;">
                <h3 style="margin-top: 0;">Order Details</h3>
                <p>Order Number: ' . $orderData['order_number'] . '</p>
                <p>Date: ' . $orderData['date'] . '</p>
                <div style="margin-top: 20px;">
                    <h4>Cancelled Items:</h4>
                    <ul style="list-style: none; padding: 0;">' .
                    collect($orderData['items'])->map(function($item) {
                        return '<li style="margin-bottom: 10px;">
                            ' . $item['name'] . ' - Size: ' . $item['size'] . ' (Qty: ' . $item['quantity'] . ')
                        </li>';
                    })->join('') . '
                    </ul>
                </div>
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


public function getOrderById($id)
{
    try {
        $order = Order::where('id', $id)
            ->with(['items' => function($query) {
                $query->select(
                    'id', 
                    'order_id', 
                    'product_name', 
                    'product_image', 
                    'quantity', 
                    'selling_price',
                    'size',
                    'total'
                );
            }])
            ->first();

        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'Order not found'
            ], 404);
        }

        // Calculate shipping fee
        $shippingFee = $order->delivery_type === 'pickup' ? 0 : 400;
        if ($order->delivery_type === 'delivery') {
            $totalWeight = $order->items->sum(function($item) {
                return $item->quantity * ($item->stock->product->weight ?? 0);
            });
            
            $weightInKg = $totalWeight / 1000;
            if ($weightInKg > 1) {
                $extraKgs = ceil($weightInKg - 1);
                $shippingFee += ($extraKgs * 100);
            }
        }

        $response = [
            'status' => true,
            'data' => [
                'orderID' => (string)$order->id,
                'orderNumber' => $order->order_number,
                'orderDate' => $order->created_at->format('Y-m-d'),
                'orderStatus' => $order->status,
                'deliveryType' => $order->delivery_type,
                'pickupId' => $order->pickup_id,
                'shippingFee' => (string)$shippingFee,
                'subTotal' => (string)$order->items->sum('total'),
                'total' => (string)($order->items->sum('total') + $shippingFee),
                'deliveryDetails' => [
                    'name' => $order->delivery_name,
                    'phone' => $order->delivery_phone,
                    'address' => $order->delivery_address,
                    'city' => $order->delivery_city
                ],
                'items' => $order->items->map(function($item) {
                    return [
                        'productName' => $item->product_name,
                        'productImage' => $item->product_image,
                        'quantity' => (string)$item->quantity,
                        'price' => (string)$item->selling_price,
                        'size' => $item->size
                    ];
                })
            ]
        ];

        return response()->json($response);

    } catch (\Exception $e) {
        Log::error('Error fetching order:', [
            'order_id' => $id,
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'status' => false,
            'message' => 'Error fetching order'
        ], 500);
    }
}


public function updateTracking(Request $request, $id)
{
    try {
        // Validate request
        $request->validate([
            'tracking_number' => 'required|string',
            'courier_id' => 'required|exists:curriers,id'
        ]);

        $order = Order::with(['user'])->findOrFail($id);

        DB::beginTransaction();

        // Update order with tracking details
        $updateData = [
            'tracking_number' => $request->tracking_number,
            'status' => 'shipped'
        ];

        // Only include courier_id if not withoutTracking
        if ($request->tracking_number !== 'withoutTracking') {
            $updateData['courier_id'] = $request->courier_id;
        }

        $order->update($updateData);

        // Only send notifications if tracking number is not "withoutTracking"
        if ($request->tracking_number !== 'withoutTracking') {
            // Send email notification
            if ($order->email) {
                try {
                    Mail::to($order->email)->send(new \App\Mail\TrackingUpdate([
                        'name' => $order->delivery_name,
                        'order_number' => $order->order_number,
                        'tracking_number' => $request->tracking_number,
                        'courier' => $order->courier->description,
                        'service' => $order->courier->name
                    ]));

                    Log::info('Tracking update email sent:', [
                        'order_number' => $order->order_number,
                        'email' => $order->email
                    ]);
                } catch (\Exception $e) {
                    Log::error('Error sending tracking update email:', [
                        'order_number' => $order->order_number,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Send FCM notification if user has FCM token
            if ($order->user && $order->user->fcm_token) {
                $this->notificationController->sendNotification(new Request([
                    'token' => $order->user->fcm_token,
                    'title' => 'Order Shipped',
                    'body' => "Your order #{$order->order_number} has been shipped. Track your order with number: {$request->tracking_number}",
                    'data' => [
                        'orderId' => (string)$order->id,
                        'orderNumber' => $order->order_number,
                        'status' => 'shipped',
                        'type' => 'order_update',
                        'tracking_number' => $request->tracking_number
                    ]
                ]));
            }
        }

        DB::commit();

        return response()->json([
            'status' => true,
            'message' => 'Tracking details updated successfully',
            'data' => $order->fresh(['courier'])
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error updating tracking details:', [
            'order_id' => $id,
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'status' => false,
            'message' => 'Error updating tracking details'
        ], 500);
    }
}


public function updateReadyForPickup(Request $request, $id)
{
    try {
        $order = Order::with(['user'])->findOrFail($id);

        // Check if order is pickup type
        if ($order->delivery_type !== 'pickup') {
            return response()->json([
                'status' => false,
                'message' => 'This order is not a pickup order'
            ], 400);
        }

        DB::beginTransaction();

        // Update order status
        $order->update([
            'status' => 'ready_for_pickup'
        ]);

        // Send email notification
        if ($order->email) {
            try {
                Mail::to($order->email)->send(new \App\Mail\OrderReadyForPickup([
                    'name' => $order->delivery_name,
                    'order_number' => $order->order_number,
                    'pickup_id' => $order->pickup_id,
                    'branch_name' => $order->branch ? $order->branch->name : ''
                ]));

                Log::info('Ready for pickup email sent:', [
                    'order_number' => $order->order_number,
                    'email' => $order->email
                ]);
            } catch (\Exception $e) {
                Log::error('Error sending ready for pickup email:', [
                    'order_number' => $order->order_number,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Send FCM notification if user has FCM token
        if ($order->user && $order->user->fcm_token) {
            $this->notificationController->sendNotification(new Request([
                'token' => $order->user->fcm_token,
                'title' => 'Order Ready for Pickup',
                'body' => "Your order #{$order->order_number} is ready for pickup. Pickup ID: {$order->pickup_id}",
                'data' => [
                    'orderId' => (string)$order->id,
                    'orderNumber' => $order->order_number,
                    'status' => 'ready_for_pickup',
                    'type' => 'order_update',
                    'pickup_id' => $order->pickup_id
                ]
            ]));
        }

        DB::commit();

        return response()->json([
            'status' => true,
            'message' => 'Order status updated to ready for pickup',
            'data' => $order->fresh(['branch'])
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error updating order to ready for pickup:', [
            'order_id' => $id,
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'status' => false,
            'message' => 'Error updating order status'
        ], 500);
    }
}

public function updatePickedUp(Request $request, $id)
{
    try {
        $order = Order::with(['user'])->findOrFail($id);

        // Check if order is pickup type
        if ($order->delivery_type !== 'pickup') {
            return response()->json([
                'status' => false,
                'message' => 'This order is not a pickup order'
            ], 400);
        }

        // Check if order is ready for pickup
        if ($order->status !== 'ready_for_pickup') {
            return response()->json([
                'status' => false,
                'message' => 'Order must be ready for pickup first'
            ], 400);
        }

        DB::beginTransaction();

        // Update order status
        $order->update([
            'status' => 'picked_up',
            'picked_up_at' => now()
        ]);

        // Send FCM notification if user has FCM token
        if ($order->user && $order->user->fcm_token) {
            $this->notificationController->sendNotification(new Request([
                'token' => $order->user->fcm_token,
                'title' => 'Order Picked Up',
                'body' => "Your order #{$order->order_number} has been picked up.",
                'data' => [
                    'orderId' => (string)$order->id,
                    'orderNumber' => $order->order_number,
                    'status' => 'picked_up',
                    'type' => 'order_update'
                ]
            ]));
        }

        DB::commit();

        return response()->json([
            'status' => true,
            'message' => 'Order status updated to picked up',
            'data' => $order->fresh(['branch'])
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error updating order to picked up:', [
            'order_id' => $id,
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'status' => false,
            'message' => 'Error updating order status'
        ], 500);
    }
}


public function updateDelivered(Request $request, $id)
{
    try {
        $order = Order::with(['user'])->findOrFail($id);

        // Check if order is delivery type
        if ($order->delivery_type !== 'delivery') {
            return response()->json([
                'status' => false,
                'message' => 'This order is not a delivery order'
            ], 400);
        }

        // Check if order is shipped
        if ($order->status !== 'shipped') {
            return response()->json([
                'status' => false,
                'message' => 'Order must be shipped first'
            ], 400);
        }

        DB::beginTransaction();

        // Update order status
        $order->update([
            'status' => 'delivered',
            'delivered_at' => now()
        ]);

        // Send email notification
        if ($order->email) {
            try {
                Mail::to($order->email)->send(new \App\Mail\OrderDelivered([
                    'name' => $order->delivery_name,
                    'order_number' => $order->order_number,
                    'delivery_address' => $order->delivery_address,
                    'delivery_city' => $order->delivery_city
                ]));

                Log::info('Delivery confirmation email sent:', [
                    'order_number' => $order->order_number,
                    'email' => $order->email
                ]);
            } catch (\Exception $e) {
                Log::error('Error sending delivery confirmation email:', [
                    'order_number' => $order->order_number,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Send FCM notification if user has FCM token
        if ($order->user && $order->user->fcm_token) {
            $this->notificationController->sendNotification(new Request([
                'token' => $order->user->fcm_token,
                'title' => 'Order Delivered',
                'body' => "Your order #{$order->order_number} has been delivered successfully!",
                'data' => [
                    'orderId' => (string)$order->id,
                    'orderNumber' => $order->order_number,
                    'status' => 'delivered',
                    'type' => 'order_update'
                ]
            ]));
        }

        DB::commit();

        return response()->json([
            'status' => true,
            'message' => 'Order status updated to delivered',
            'data' => $order->fresh()
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error updating order to delivered:', [
            'order_id' => $id,
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'status' => false,
            'message' => 'Error updating order status'
        ], 500);
    }
}

}


