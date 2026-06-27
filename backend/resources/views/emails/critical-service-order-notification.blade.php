@extends('emails.layout')

@section('title', $delivery->subject)
@section('preheader', $delivery->subject)
@section('header-eyebrow', config('app.name'))
@section('header', $companyName)

@section('content')
    <p>Olá{{ $delivery->recipient_name ? ', ' . $delivery->recipient_name : '' }}!</p>

    @if(filled($payload['notification_intro_text'] ?? null))
        <p>{{ $payload['notification_intro_text'] }}</p>
    @endif

    @if(filled($payload['portal_intro_text'] ?? null))
        <p>{{ $payload['portal_intro_text'] }}</p>
    @endif

    @php
        $infoRows = [];
        $ref = $payload['service_order_reference'] ?? $delivery->serviceOrder?->reference;
        if ($ref) {
            $infoRows[] = ['label' => 'Ordem de serviço', 'value' => $ref];
        }
        if (isset($payload['valid_until'])) {
            $infoRows[] = ['label' => 'Válido até', 'value' => $payload['valid_until']];
        }
        if (isset($payload['amount_cents'])) {
            $infoRows[] = ['label' => 'Valor', 'value' => 'R$ ' . number_format((int) $payload['amount_cents'] / 100, 2, ',', '.')];
        }
        if (isset($payload['payment_method'])) {
            $infoRows[] = ['label' => 'Forma de pagamento', 'value' => $payload['payment_method']];
        }
    @endphp

    @if(count($infoRows) > 0)
        <div class="info-box">
            @foreach($infoRows as $row)
                <div class="info-row">
                    <span class="info-label">{{ $row['label'] }}</span>
                    <span class="info-value">{{ $row['value'] }}</span>
                </div>
            @endforeach
        </div>
    @endif

    @if(filled($payload['portal_url'] ?? null))
        <div class="btn-wrap">
            <a href="{{ $payload['portal_url'] }}" class="btn">Ver detalhes</a>
        </div>
    @endif

    @php
        $contactLine = $payload['customer_contact_line'] ?? $delivery->company?->name ?? null;
    @endphp
    @if(filled($contactLine))
        <p class="muted">{{ $contactLine }}</p>
    @endif

    @if(filled($payload['notification_footer_text'] ?? null))
        <hr class="divider">
        <p class="muted">{{ $payload['notification_footer_text'] }}</p>
    @endif
@endsection

@section('footer', 'Este é um e-mail automático. Não responda a esta mensagem.')
