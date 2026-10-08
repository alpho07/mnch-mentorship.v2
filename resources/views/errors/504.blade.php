@extends('errors.layout')
@section('code', '504')
@section('icon', 'plug')
@section('headline', "That's taking too long")
@section('lead', "Our system didn't answer in time. Please try again.")
@section('retry', '1')
@section('tips')
    <li>Wait a minute, then try again.</li>
    <li>If you were uploading or exporting something large, try a smaller one.</li>
@endsection
