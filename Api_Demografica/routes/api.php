<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PopulationController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/population/municipality/{code}', [PopulationController::class, 'byMunicipality']);

Route::get('/population/island/{code}', [PopulationController::class, 'byIsland']);

// Rutas de Evolución
Route::get('/population/evolution/municipality/{code}', [PopulationController::class, 'evolutionByMunicipality']);
Route::get('/population/evolution/island/{code}', [PopulationController::class, 'evolutionByIsland']);

// Buscador general (Islas y Municipios)
Route::get('/search/{text}', [PopulationController::class, 'search']);