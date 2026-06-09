<p>{{ $companyName }}</p>

<p>{{ $delivery->subject }}</p>

@if(isset($payload['portal_intro_text']))
<p>{{ $payload['portal_intro_text'] }}</p>
@endif

<p>Ordem de serviço: {{ $payload['service_order_reference'] ?? $delivery->serviceOrder?->reference }}</p>

@if(isset($payload['valid_until']))
<p>Validade: {{ $payload['valid_until'] }}</p>
@endif

@if(isset($payload['amount_cents']))
<p>Valor: {{ $payload['amount_cents'] }} {{ $payload['currency'] ?? 'BRL' }}</p>
@endif

@if(isset($payload['payment_method']))
<p>Método de pagamento: {{ $payload['payment_method'] }}</p>
@endif

@if(filled($payload['customer_contact_line'] ?? null))
<p>Contato da empresa: {{ $payload['customer_contact_line'] }}</p>
@else
<p>Contato da empresa: {{ $delivery->company?->name }}</p>
@endif

@if(filled($payload['notification_footer_text'] ?? null))
<p>{{ $payload['notification_footer_text'] }}</p>
@endif
