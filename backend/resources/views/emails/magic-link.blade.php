@extends('emails.layout')

@section('title', 'Link de acesso — ' . $companyName)
@section('preheader', 'Acesse ' . $companyName . ' com um clique. Válido por 10 minutos.')
@section('header', $companyName)

@section('content')
    <p>Olá,</p>
    <p>Clique no botão abaixo para acessar o <strong>{{ $companyName }}</strong> sem precisar de senha:</p>
    <div class="btn-wrap">
        <a href="{{ $magicLinkUrl }}" class="btn">Acessar agora</a>
    </div>
    <p class="helper">Este link expira em 10 minutos e só pode ser usado uma vez.</p>
    <div class="link-fallback">
        <p class="link-fallback-label">Se o botão não funcionar, copie e cole o link abaixo no seu navegador:</p>
        <code>{{ $magicLinkUrl }}</code>
    </div>
    <p>Se você não solicitou este acesso, ignore este e-mail.</p>
@endsection
