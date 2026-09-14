{{-- Delivery Tracking Section --}}
@if($order->shipments->count() > 0)
    <div style="background:#fff; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.06); overflow:hidden; margin-top:24px;">
        <div class="heading-section">
            <h3 style="color:#fff; font-size:1rem; font-weight:800; text-transform:uppercase; letter-spacing:1px; margin:0;">
                <i class="bi bi-truck me-2" style="color:#fff;"></i> Delivery Tracking
            </h3>
        </div>
        <div style="padding:24px 28px;">
            @foreach($order->shipments as $shipment)
                <div style="margin-bottom:20px; padding-bottom:20px; border-bottom:1px solid #f0f0f0;">
                    @if($shipment->deliveryPartner)
                        <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px; padding:12px 16px; background:#f8f9fa; border-radius:8px;">
                            <div style="width:40px; height:40px; background:#017075; border-radius:50%; display:flex; align-items:center; justify-content:center;">
                                <i class="bi bi-building" style="color:#fff; font-size:1rem;"></i>
                            </div>
                            <div>
                                <p style="font-weight:700; color:#0D0D0D; margin:0; font-size:0.95rem;">{{ $shipment->deliveryPartner->name }}</p>
                                @if($shipment->deliveryPartner->contact_phone)
                                    <p style="color:#6C757D; font-size:0.8rem; margin:2px 0 0;"><i class="bi bi-telephone me-1"></i>{{ $shipment->deliveryPartner->contact_phone }}</p>
                                @endif
                            </div>
                        </div>
                    @endif
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px; margin-bottom:16px;">
                        @if($shipment->tracking_number)
                            <div>
                                <p style="font-size:0.75rem; text-transform:uppercase; letter-spacing:1px; color:#6C757D; margin:0 0 4px; font-weight:600;">Tracking Number</p>
                                <p style="font-weight:700; color:#0D0D0D; margin:0; font-size:0.95rem; font-family:monospace;">{{ $shipment->tracking_number }}</p>
                            </div>
                        @endif
                        <div>
                            <p style="font-size:0.75rem; text-transform:uppercase; letter-spacing:1px; color:#6C757D; margin:0 0 4px; font-weight:600;">Status</p>
                            @php
                                $shipmentStatusColors = ['pending'=>'#ffc107','processing'=>'#0dcaf0','shipped'=>'#0d6efd','in_transit'=>'#6f42c1','out_for_delivery'=>'#fd7e14','delivered'=>'#198754','cancelled'=>'#dc3545'];
                                $shipmentColor = $shipmentStatusColors[$shipment->status] ?? '#6C757D';
                            @endphp
                            <span style="display:inline-block; padding:4px 12px; border-radius:20px; font-size:0.75rem; font-weight:600; text-transform:uppercase; background:{{ $shipmentColor }}22; color:{{ $shipmentColor }};">{{ ucfirst(str_replace('_', ' ', $shipment->status)) }}</span>
                        </div>
                    </div>
                    @if($shipment->shipped_at)
                        <div style="margin-bottom:12px;">
                            <p style="font-size:0.75rem; text-transform:uppercase; letter-spacing:1px; color:#6C757D; margin:0 0 4px; font-weight:600;">Shipped On</p>
                            <p style="font-weight:600; color:#0D0D0D; margin:0; font-size:0.9rem;"><i class="bi bi-calendar-check me-1"></i>{{ $shipment->shipped_at->format('M d, Y h:i A') }}</p>
                        </div>
                    @endif
                    @if($shipment->delivered_at)
                        <div style="margin-bottom:12px;">
                            <p style="font-size:0.75rem; text-transform:uppercase; letter-spacing:1px; color:#6C757D; margin:0 0 4px; font-weight:600;">Delivered On</p>
                            <p style="font-weight:600; color:#198754; margin:0; font-size:0.9rem;"><i class="bi bi-check-circle me-1"></i>{{ $shipment->delivered_at->format('M d, Y h:i A') }}</p>
                        </div>
                    @endif
                    @if($shipment->tracking_url)
                        <a href="{{ $shipment->tracking_url }}" target="_blank" rel="noopener noreferrer" style="display:inline-flex; align-items:center; gap:8px; padding:10px 20px; background:#017075; color:#fff; font-size:0.85rem; font-weight:600; text-decoration:none; border-radius:8px; transition:all 0.2s;" onmouseover="this.style.background='#015a5e';" onmouseout="this.style.background='#017075';">
                            <i class="bi bi-box-arrow-up-right"></i> Track Delivery
                        </a>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endif
