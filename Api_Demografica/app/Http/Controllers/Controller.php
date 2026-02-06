<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "API Demográfica de Canarias",
    description: "Documentación de la API para consultar datos de población, evolución y estadísticas de las Islas Canarias.",
    contact: new OA\Contact(
        email: "estudiante@ejemplo.com"
    ),
    license: new OA\License(
        name: "Apache 2.0",
        url: "https://www.apache.org/licenses/LICENSE-2.0.html"
    )
)]
#[OA\Server(
    url: "http://127.0.0.1:8000/api",
    description: "Servidor Local"
)]
abstract class Controller
{
    // Clase base vacía
}
