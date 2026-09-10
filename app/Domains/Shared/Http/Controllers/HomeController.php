<?php

namespace App\Domains\Shared\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Page d'accueil du backend.
 *
 * Elle existe pour une raison pratique : quelqu'un qui ouvre l'URL de l'API
 * dans un navigateur doit comprendre ce qu'il regarde et trouver la
 * documentation, plutot que de recevoir un 404 qui laisse croire a un
 * deploiement rate.
 */
class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('home');
    }
}
