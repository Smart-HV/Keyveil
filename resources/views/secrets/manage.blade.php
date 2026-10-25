@extends('layouts.app')

@section('page', 'manage')

@section('content')
    <div id="manage-secret" class="stack" data-public-id="{{ $secret }}">
        <h1>Secret verwalten</h1>

        <noscript>
            <p class="notice notice-error">JavaScript ist erforderlich.</p>
        </noscript>

        <p>Hier kannst du das Secret vor Ablauf endgültig löschen. Der Inhalt wird dabei nicht abgerufen.</p>

        <button id="delete-button" type="button" class="button-danger" disabled>Secret endgültig löschen</button>

        <p id="manage-status" class="notice" role="status" hidden></p>

        <dialog id="delete-dialog">
            <form method="dialog" class="stack">
                <p>Das Secret wird unwiderruflich gelöscht. Fortfahren?</p>
                <div class="row">
                    <button value="cancel" type="submit">Abbrechen</button>
                    <button id="delete-confirm" value="confirm" type="submit" class="button-danger">Löschen</button>
                </div>
            </form>
        </dialog>
    </div>
@endsection
