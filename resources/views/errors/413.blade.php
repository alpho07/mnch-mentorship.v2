@extends('errors.layout')
@section('code', '413')
@section('icon', 'file')
@section('headline', "That file is too big")
@section('lead', "The file you tried to upload is larger than we can accept.")
@section('tips')
    <li>Try a smaller file, or compress it first.</li>
    <li>Photos can be resized before uploading.</li>
    <li>Split very large files into parts.</li>
@endsection
