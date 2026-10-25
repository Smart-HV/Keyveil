@extends('errors.minimal')

@section('code', $exception->getStatusCode())
@section('message', 'Ein Fehler ist aufgetreten. Bitte später erneut versuchen.')
