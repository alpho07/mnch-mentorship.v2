@extends('errors.layout')
@section('code', '429')
@section('label', "Slow down")
@section('icon', 'clock')
@section('headline', "Too many tries — please pause")
@section('lead', "You've made a lot of requests in a short time. Wait a moment, then try again.")
@section('retry', '1')
@section('tips')
    <li>The button below turns on when you can continue.</li>
@endsection
