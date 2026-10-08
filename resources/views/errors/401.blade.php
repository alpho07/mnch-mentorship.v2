@extends('errors.layout')
@section('code', '401')
@section('icon', 'lock')
@section('headline', "Please sign in to continue")
@section('lead', "You need to be signed in to see this page.")
@section('signin', '1')
@section('tips')
    <li>Sign in with your usual email and password.</li>
    <li>Forgot your password? Use <strong>Forgot password</strong> on the sign-in page.</li>
@endsection
