@extends('errors.layout')
@section('code', '405')
@section('icon', 'alert')
@section('headline', "That action isn't available here")
@section('lead', "The page can't do what was asked. Going back and trying again from the page itself usually fixes it.")
@section('tips')
    <li>Go back and use the buttons on the page.</li>
@endsection
