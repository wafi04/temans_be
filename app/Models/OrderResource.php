<?php

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'total_amount' => $this->total_amount,
            'shipping_address' => $this->shipping_address,
            'payment_method' => $this->payment_method,
            'checkout_at' => $this->checkout_at,
            'items' => OrderItemResource::collection($this->orderItems)
        ];
}
}
class OrderItemResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'product_variant_id' => $this->product_variant_id,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'subtotal' => $this->subtotal,
            'product_name' => $this->productVariant->name,
            'product_image' => $this->productVariant->image_url
        ];
    }
}
