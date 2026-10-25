@extends('errors.minimal')

@section('code', $exception->getStatusCode())
@section('message', 'Diese Seite ist nicht verfügbar.')
