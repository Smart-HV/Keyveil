@extends('layouts.app')

@section('page', 'manage')

@section('content')
    <div id="manage-secret" class="sheet envelope" data-public-id="{{ $secret }}">
        <span class="envelope-icon envelope-icon-danger"><x-icon name="trash" /></span>

        <div class="envelope-head">
            <h1>Secret verwalten</h1>
            <p class="lead">Lösche das Secret vor Ablauf endgültig. Der Inhalt wird dabei nicht abgerufen und danach kann niemand es mehr öffnen.</p>
        </div>

        <noscript>
            <p class="notice notice-danger">JavaScript ist erforderlich.</p>
        </noscript>

        <button id="delete-button" type="button" class="button button-danger button-block" disabled>
            <x-icon name="trash" />
            <span>Secret endgültig löschen</span>
        </button>

        <p id="manage-status" class="notice" role="status" hidden></p>

        <dialog id="delete-dialog" class="dialog" aria-labelledby="delete-dialog-title">
            <form method="dialog" class="stack">
                <h2 id="delete-dialog-title">Secret löschen?</h2>
                <p>Das Secret wird unwiderruflich gelöscht. Der Link funktioniert danach nicht mehr.</p>
                <div class="dialog-actions">
                    <button value="cancel" type="submit" class="button button-secondary">Abbrechen</button>
                    <button id="delete-confirm" value="confirm" type="submit" class="button button-danger">Löschen</button>
                </div>
            </form>
        </dialog>
    </div>
@endsection
