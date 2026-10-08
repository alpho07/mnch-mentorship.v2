@extends('errors.layout')
@section('code', '503')
@section('label', "Back soon")
@section('icon', 'tools')
@section('headline', "We'll be right back")
@section('lead', "The site is briefly unavailable — usually for maintenance or an update. This page checks again automatically.")
@section('retry', '1')
@section('refresh', '60')
@section('tips')
    <li>There's nothing you need to do. This page will refresh by itself.</li>
    <li>Your saved work is safe.</li>
@endsection
