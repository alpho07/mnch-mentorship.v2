@extends('errors.layout')
@section('code', '400')
@section('icon', 'alert')
@section('headline', "That link didn't work")
@section('lead', "We couldn't understand that request. This usually happens with an old, cut-off or mistyped link.")
@section('tips')
    <li>Go back and try again.</li>
    <li>Open the page from the menu instead of a saved or forwarded link.</li>
@endsection
