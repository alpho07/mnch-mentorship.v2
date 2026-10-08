@extends('errors.layout')
@section('code', '408')
@section('icon', 'clock')
@section('headline', "That took too long")
@section('lead', "The connection timed out before we could finish. Nothing has been lost.")
@section('retry', '1')
@section('tips')
    <li>Check your internet connection.</li>
    <li>Try again in a moment.</li>
@endsection
