@extends('errors.layout')
@section('code', '422')
@section('icon', 'alert')
@section('headline', "Something needs fixing")
@section('lead', "We couldn't process what was sent. Go back and check the details you entered.")
@section('tips')
    <li>Make sure required fields are filled in.</li>
    <li>Check dates, emails and phone numbers are correct.</li>
@endsection
