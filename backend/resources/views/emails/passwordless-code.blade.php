@extends('emails.layout')

@section('title', 'Código de acesso — ' . $companyName)
@section('preheader', 'Seu código de acesso ao ' . $companyName . ': ' . $code . '. Válido por 10 minutos.')
@section('header', $companyName)

@section('content')
    <p>Olá,</p>
    <p>Seu código de acesso ao <strong>{{ $companyName }}</strong> é:</p>
    <div class="code-box">
        <div class="code">{{ $code }}</div>
        <p class="code-validity">Válido por 10 minutos &middot; Use uma única vez</p>
    </div>
    <p>Se você não solicitou este código, ignore este e-mail. Nenhuma ação é necessária.</p>
@endsection
