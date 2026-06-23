@extends('layouts.vue-admin')

@section('title', 'Multi-Tenant Telephony Platform Admin')

{{--
    Dedicated SPA shell view for /admin/* routes.
    WHY:
    Vue admin is the primary admin experience; legacy Blade pages are
    explicitly isolated under /admin/legacy/* during migration cleanup.
--}}
