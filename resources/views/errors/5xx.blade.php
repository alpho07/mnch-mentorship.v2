@extends('errors.layout')
@section('code', (string) ($exception->getStatusCode() ?? 500))
@section('icon', 'tools')
@section('headline', "Something went wrong on our side")
@section('lead', "This isn't something you did. Please try again in a few minutes.")
@section('retry', '1')
@section('tips')
    <li>Try again shortly — most issues clear quickly.</li>
    <li>If it keeps happening, email us the time shown under <strong>Details for support</strong>.</li>
@endsection
