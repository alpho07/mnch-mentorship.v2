@extends('errors.layout')
@section('code', (string) ($exception->getStatusCode() ?? 400))
@section('icon', 'alert')
@section('headline', "We couldn't open that")
@section('lead', "Something about that request didn't work. Going back and trying again usually fixes it.")
@section('tips')
    <li>Go back and try again.</li>
    <li>Start from the home page and use the menu.</li>
@endsection
