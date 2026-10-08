@extends('errors.layout')
@section('code', '403')
@section('icon', 'lock')
@section('headline', "You don't have access to this page")
@section('lead', "Your account isn't set up to open this page. If you think it should be, your team lead or administrator can help.")
@section('signin', '1')
@section('tips')
    <li>Check you are signed in with the right account.</li>
    <li>Ask your administrator to review your access.</li>
@endsection
