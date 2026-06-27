@extends('emails.layout')

@section('title', 'Bem-vindo ao ' . config('app.name'))
@section('preheader', 'Sua empresa ' . $companyName . ' foi cadastrada com sucesso. Acesse a plataforma agora.')
@section('header', $companyName)

@section('content')
    <p>Olá,</p>
    <p>Sua empresa <strong>{{ $companyName }}</strong> foi cadastrada com sucesso na plataforma.</p>
    <p>Você já pode acessar o sistema com o e-mail cadastrado.</p>
    <div class="btn-wrap">
        <a href="{{ $loginUrl }}" class="btn">Acessar plataforma</a>
    </div>
    <p>Se você não reconhece este cadastro, ignore este e-mail.</p>
@endsection
