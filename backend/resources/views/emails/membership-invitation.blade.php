@extends('emails.layout')

@section('title', 'Convite para ' . $companyName)
@section('preheader', 'Você foi convidado para ' . $companyName . ' como ' . $roleName . '. Aceite em até 7 dias.')
@section('header', $companyName)

@section('content')
    <p>Olá, <strong>{{ $inviteeName }}</strong>!</p>
    <p>Você foi convidado para participar do <strong>{{ $companyName }}</strong> como <strong>{{ $roleName }}</strong>.</p>
    @if ($requiresPasswordSetup)
    <div class="note">
        <p>Como é seu primeiro acesso, você precisará criar uma senha ao aceitar o convite.</p>
    </div>
    @endif
    <div class="btn-wrap">
        <a href="{{ $inviteUrl }}" class="btn">Aceitar convite</a>
    </div>
    <p class="helper">Este convite expira em 7 dias.</p>
    <div class="link-fallback">
        <p class="link-fallback-label">Se o botão não funcionar, copie e cole o link abaixo no seu navegador:</p>
        <code>{{ $inviteUrl }}</code>
    </div>
    <p>Se você não esperava receber este convite, ignore este e-mail.</p>
@endsection
