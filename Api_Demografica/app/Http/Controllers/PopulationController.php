<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Municipality;
use App\Models\Population;
use App\Models\Island;
use OpenApi\Attributes as OA;

class PopulationController extends Controller
{
    // --- LÓGICA PRIVADA ---

    private function applyFilters($query, Request $request, $defaultToLatestYear = false)
    {
        // 1. Filtro por Año
        if ($request->has('year')) {
            $query->where('year', $request->year);
        } elseif ($defaultToLatestYear) {
            // Si NO pide año y está activado el modo automático, buscamos el último
            $maxYear = Population::max('year');
            $query->where('year', $maxYear);
        }

        // 2. Filtro por Género
        if ($request->has('gender'))
            $query->where('gender', $request->gender);

        // 3. Filtro por Edad
        if ($request->has('age'))
            $query->where('age', $request->age);

        // 4. Filtro por Rango
        if ($request->has('age_range')) {
            $range = explode('-', $request->age_range);
            if (count($range) == 2) {
                $query->whereBetween('age', [(int) $range[0], (int) $range[1]]);
            }
        }
        return $query;
    }

    private function calculateEvolution($query)
    {
        $data = $query->selectRaw('year, SUM(population) as total_population')
            ->groupBy('year')->orderBy('year', 'asc')->get();
        $evolution = [];
        $prev = null;
        foreach ($data as $rec) {
            $curr = $rec->total_population;
            $var = ($prev !== null) ? $curr - $prev : 0;
            $perc = ($prev > 0) ? round(($var / $prev) * 100, 2) . '%' : '0%';
            $evolution[] = ['year' => $rec->year, 'population' => (int) $curr, 'variation_total' => $var, 'variation_percentage' => $perc];
            $prev = $curr;
        }
        return $evolution;
    }

    // --- ENDPOINTS PÚBLICOS ---

    #[OA\Get(
        path: '/population/municipality/{term}',
        summary: 'Obtener datos de un Municipio',
        description: 'Busca por Código (35001) o Nombre (Agaete). Si no se especifica año, devuelve el más reciente automáticamente. Incluye la suma total.',
        tags: ['Datos Demográficos'],
        parameters: [
            new OA\Parameter(name: 'term', in: 'path', required: true, description: 'Código o Nombre del municipio', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'year', in: 'query', description: 'Año específico (Opcional)', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'gender', in: 'query', description: 'Filtrar por género (Hombres, Mujeres)', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'age_range', in: 'query', description: 'Rango de edad (ej: 20-30)', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'sort_by', in: 'query', description: 'Ordenar por: population, age', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'order', in: 'query', description: 'asc o desc', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Datos encontrados correctamente'),
            new OA\Response(response: 404, description: 'Municipio no encontrado')
        ]
    )]
    public function byMunicipality(Request $request, $term)
    {
        $municipality = Municipality::where('code', $term)->orWhere('name', $term)->first();
        if (!$municipality)
            return response()->json(['error' => 'Municipio no encontrado: ' . $term], 404);

        $query = Population::where('municipality_id', $municipality->id);

        // Filtro automático activado
        $query = $this->applyFilters($query, $request, true);

        $sortBy = $request->get('sort_by', 'default');
        $order = $request->get('order', 'desc');

        if ($sortBy === 'population')
            $query->orderBy('population', $order);
        elseif ($sortBy === 'age')
            $query->orderBy('age', $order);
        else
            $query->orderBy('year', 'desc')->orderBy('age', 'asc');

        $data = $query->get();

        $totalPopulation = $data->sum('population');

        return response()->json([
            'municipality' => $municipality->name,
            'code' => $municipality->code,
            'filters_applied' => $request->all(),
            'automatic_year' => !$request->has('year'),
            'total_population' => $totalPopulation,
            'total_records' => $data->count(),
            'data' => $data
        ]);
    }

    #[OA\Get(
        path: '/population/island/{term}',
        summary: 'Obtener datos de una Isla (Total y Desglose)',
        description: 'Busca por Código o Nombre. Devuelve la población total sumada y un desglose por municipios. Usa summary=true para ocultar la lista detallada.',
        tags: ['Datos Demográficos'],
        parameters: [
            new OA\Parameter(name: 'term', in: 'path', required: true, description: 'Código o Nombre de la isla', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'summary', in: 'query', description: 'Si es "true", solo devuelve el total (sin desglose)', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'year', in: 'query', description: 'Año específico (Opcional)', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'gender', in: 'query', description: 'Filtrar por género', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Datos encontrados'),
            new OA\Response(response: 404, description: 'Isla no encontrada')
        ]
    )]
    public function byIsland(Request $request, $term)
    {
        $island = Island::where('code', $term)->orWhere('name', $term)->first();
        if (!$island)
            return response()->json(['error' => 'Isla no encontrada: ' . $term], 404);

        $query = Population::with('municipality')->where('island_id', $island->id);

        // Filtro automático activado
        $query = $this->applyFilters($query, $request, true);

        if (!$request->has('sort_by')) {
            $query->orderBy('municipality_id', 'asc')->orderBy('year', 'desc');
        } else {
            $sortBy = $request->get('sort_by');
            $order = $request->get('order', 'desc');
            if ($sortBy == 'population')
                $query->orderBy('population', $order);
        }

        $data = $query->get();

        // 1. Cálculo del TOTAL GLOBAL
        $totalPopulation = $data->sum('population');

        // 2. NUEVO: Desglose por Municipio (Agrupado y Sumado)
        $breakdown = $data->groupBy(function($item) {
            return $item->municipality->name;
        })->map(function ($rows, $municipalityName) {
            return [
                'municipality' => $municipalityName,
                'code' => $rows->first()->municipality->code,
                'total_population' => $rows->sum('population')
            ];
        })->values();

        $response = [
            'island' => $island->name,
            'code' => $island->code,
            'filters_applied' => $request->all(),
            'automatic_year' => !$request->has('year'),
            'total_population' => $totalPopulation,
            'municipalities_count' => $breakdown->count(),
            
            // Aquí está la lista de municipios con su suma total:
            'breakdown_by_municipality' => $breakdown,
            
            'total_records' => $data->count(),
        ];

        // Lógica de resumen (si NO piden summary, enviamos también los datos crudos)
        if (!$request->has('summary')) {
            $response['raw_data'] = $data;
        }

        return response()->json($response);
    }

    #[OA\Get(
        path: '/population/evolution/municipality/{code}',
        summary: 'Evolución Anual (Municipio)',
        description: 'Calcula la variación y porcentaje de crecimiento anual.',
        tags: ['Evolución y Estadísticas'],
        parameters: [
            new OA\Parameter(name: 'code', in: 'path', required: true, description: 'Código del municipio', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Cálculo exitoso')
        ]
    )]
    public function evolutionByMunicipality(Request $request, $code)
    {
        $municipality = Municipality::where('code', $code)->first();
        if (!$municipality)
            return response()->json(['error' => 'Municipio no encontrado'], 404);

        $query = Population::where('municipality_id', $municipality->id);
        $query = $this->applyFilters($query, $request); // Aquí false por defecto (todos los años)
        return response()->json(['municipality' => $municipality->name, 'filters_applied' => $request->all(), 'evolution' => $this->calculateEvolution($query)]);
    }

    #[OA\Get(
        path: '/population/evolution/island/{code}',
        summary: 'Evolución Anual (Isla)',
        tags: ['Evolución y Estadísticas'],
        parameters: [
            new OA\Parameter(name: 'code', in: 'path', required: true, description: 'Código de la isla', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Cálculo exitoso')
        ]
    )]
    public function evolutionByIsland(Request $request, $code)
    {
        $island = Island::where('code', $code)->first();
        if (!$island)
            return response()->json(['error' => 'Isla no encontrada'], 404);

        $query = Population::where('island_id', $island->id);
        $query = $this->applyFilters($query, $request);
        return response()->json(['island' => $island->name, 'filters_applied' => $request->all(), 'evolution' => $this->calculateEvolution($query)]);
    }

    #[OA\Get(
        path: '/search/{text}',
        summary: 'Buscador General',
        description: 'Busca islas y municipios por nombre o código parcial.',
        tags: ['Buscador'],
        parameters: [
            new OA\Parameter(name: 'text', in: 'path', required: true, description: 'Texto a buscar', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Resultados encontrados')
        ]
    )]
    public function search($text)
    {
        $islands = Island::where('name', 'LIKE', "%{$text}%")->get();
        $municipalities = Municipality::where('name', 'LIKE', "%{$text}%")->with('island')->get();

        $islandsFormatted = $islands->map(function ($item) {
            return ['code' => $item->code, 'name' => $item->name, 'type' => 'Isla', 'island_name' => null];
        });

        $municipalitiesFormatted = $municipalities->map(function ($item) {
            return ['code' => $item->code, 'name' => $item->name, 'type' => 'Municipio', 'island_name' => $item->island ? $item->island->name : null];
        });

        $results = $islandsFormatted->concat($municipalitiesFormatted);
        return response()->json(['query' => $text, 'total_results' => $results->count(), 'results' => $results->values()]);
    }
}