@extends('emails.layout')

@section('title', $subjectLine)
@section('preheader', $subjectLine)
@section('header', $title)

@section('content')
    <p>Olá, <strong>{{ $companyName }}</strong>.</p>
    @foreach($lines as $line)
        <p>{{ $line }}</p>
    @endforeach
    @if($actionUrl && $actionText)
        <div class="btn-wrap">
            <a href="{{ $actionUrl }}" class="btn">{{ $actionText }}</a>
        </div>
    @endif
@endsection

@section('footer', $footer ?: 'Este e-mail foi enviado automaticamente pelo sistema de faturamento.')
