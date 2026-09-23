<?php

namespace App\Enums;

/**
 * Google requires a way to delete an account without installing the app, so a
 * request can also arrive from the web page, by email, or through support.
 */
enum DeletionSource: string
{
    case App = 'app';
    case Web = 'web';
    case Email = 'email';
    case Support = 'support';
}
