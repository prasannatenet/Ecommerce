<p>Hello {{ $user->name }},</p>

<p>Welcome to {{ config('app.name', 'Our Store') }}! Your account has been created successfully.</p>

<p>You can now:</p>
<ul>
    <li>Browse and order our full product catalog.</li>
    <li>Track your orders in real time.</li>
    <li>Manage your addresses, wishlist, and Gehna Coins balance.</li>
</ul>

<p>If you have any questions, reply to this email or reach us at {{ config('mail.from.address') ?? 'support@example.com' }}.</p>

<p>Thank you for joining us — we look forward to serving you!</p>
