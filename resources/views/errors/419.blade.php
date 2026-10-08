@extends('errors.layout')
@section('code', '419')
@section('label', "Session expired")
@section('icon', 'clock')
@section('headline', "Your session timed out")
@section('lead', "For your safety, pages expire after a period of inactivity. Refresh the page and try again.")
@section('retry', '1')
@section('tips')
    <li><strong>Refresh</strong> the page, then repeat what you were doing.</li>
    <li>If you were filling in a form, copy your answers first so you can paste them back.</li>
@endsection
