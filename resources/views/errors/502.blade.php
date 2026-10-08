@extends('errors.layout')
@section('code', '502')
@section('icon', 'plug')
@section('headline', "We're having trouble connecting")
@section('lead', "Part of our system isn't responding right now. It usually fixes itself within a minute or two.")
@section('retry', '1')
@section('tips')
    <li>Wait a minute, then try again.</li>
@endsection
