<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Municipality;
use App\Models\Population;
use App\Models\Island;

class PopulationController extends Controller
{
    /**
     * Lógica privada para aplicar filtros (Año, Género, Edad, Rango).
     * Se reutiliza en todos los endpoints.
     */
    private function applyFilters($query, Request $request)
    {
        if ($request->has('year')) {
            $query->where('year', $request->year);
        }

        if ($request->has('gender')) {
            $query->where('gender', $request->gender);
        }

        if ($request->has('age')) {
            $query->where('age', $request->age);
        }

        if ($request->has('age_range')) {
            $range = explode('-', $request->age_range);
            if (count($range) == 2) {
                $query->whereBetween('age', [(int)$range[0], (int)$range[1]]);
            }
        }

        return $query;
    }

    /**
     * Lógica privada para calcular evolución (Totales por año y variación %).
     */
    private function calculateEvolution($query)
    {
        // Agrupamos por año y sumamos la población total de ese año
        $data = $query->selectRaw('year, SUM(population) as total_population')
                      ->groupBy('year')
                      ->orderBy('year', 'asc')
                      ->get();

        $evolution = [];
        $previousPopulation = null;

        foreach ($data as $record) {
            $year = $record->year;
            $currentPop = $record->total_population;
            
            $variation = 0;
            $percentage = 0;

            if ($previousPopulation !== null) {
                $variation = $currentPop - $previousPopulation;
                if ($previousPopulation > 0) {
                    $percentage = round(($variation / $previousPopulation) * 100, 2);
                }
            }

            $evolution[] = [
                'year' => $year,
                'population' => (int)$currentPop,
                'variation_total' => $variation,
                'variation_percentage' => $percentage . '%'
            ];

            $previousPopulation = $currentPop;
        }

        return $evolution;
    }

    // ==========================================
    // ENDPOINTS PÚBLICOS
    // ==========================================

    /**
     * 1. Datos por Municipio (con filtros y ordenación)
     */
    public function byMunicipality(Request $request, $code)
    {
        $municipality = Municipality::where('code', $code)->first();
        if (!$municipality) return response()->json(['error' => 'Municipio no encontrado'], 404);

        $query = Population::where('municipality_id', $municipality->id);
        $query = $this->applyFilters($query, $request);

        // Ordenación
        $sortBy = $request->get('sort_by', 'default');
        $order = $request->get('order', 'desc');

        if ($sortBy === 'population') {
            $query->orderBy('population', $order);
        } elseif ($sortBy === 'age') {
            $query->orderBy('age', $order);
        } else {
            $query->orderBy('year', 'desc')->orderBy('age', 'asc');
        }

        $data = $query->get();

        return response()->json([
            'municipality' => $municipality->name,
            'filters_applied' => $request->all(),
            'total_records' => $data->count(),
            'data' => $data
        ]);
    }

    /**
     * 2. Datos por Isla (con filtros y ordenación)
     */
    public function byIsland(Request $request, $code)
    {
        $island = Island::where('code', $code)->first();
        if (!$island) return response()->json(['error' => 'Isla no encontrada'], 404);

        $query = Population::with('municipality')
                    ->where('island_id', $island->id);

        $query = $this->applyFilters($query, $request);

        // Ordenación
        $sortBy = $request->get('sort_by', 'default');
        $order = $request->get('order', 'desc');

        if ($sortBy === 'population') {
            $query->orderBy('population', $order);
        } else {
            $query->orderBy('municipality_id', 'asc')
                  ->orderBy('year', 'desc')
                  ->orderBy('age', 'asc');
        }

        $data = $query->get();

        return response()->json([
            'island' => $island->name,
            'filters_applied' => $request->all(),
            'total_records' => $data->count(),
            'data' => $data
        ]);
    }

    /**
     * 3. Evolución por Municipio
     */
    public function evolutionByMunicipality(Request $request, $code)
    {
        $municipality = Municipality::where('code', $code)->first();
        if (!$municipality) return response()->json(['error' => 'Municipio no encontrado'], 404);

        $query = Population::where('municipality_id', $municipality->id);
        $query = $this->applyFilters($query, $request);

        $evolution = $this->calculateEvolution($query);

        return response()->json([
            'municipality' => $municipality->name,
            'filters_applied' => $request->all(),
            'evolution' => $evolution
        ]);
    }

    /**
     * 4. Evolución por Isla
     */
    public function evolutionByIsland(Request $request, $code)
    {
        $island = Island::where('code', $code)->first();
        if (!$island) return response()->json(['error' => 'Isla no encontrada'], 404);

        $query = Population::where('island_id', $island->id);
        $query = $this->applyFilters($query, $request);

        $evolution = $this->calculateEvolution($query);

        return response()->json([
            'island' => $island->name,
            'filters_applied' => $request->all(),
            'evolution' => $evolution
        ]);
    }

    /**
     * 5. Buscador General (Islas y Municipios)
     */
   /**
     * 5. Buscador General (Islas y Municipios)
     */
    public function search($text)
    {
        // Buscamos Islas
        $islands = Island::where('name', 'LIKE', "%{$text}%")->get();
        
        // Buscamos Municipios (cargando la relación 'island')
        $municipalities = Municipality::where('name', 'LIKE', "%{$text}%")
                            ->with('island')
                            ->get();

        // Formateamos Islas
        $islandsFormatted = $islands->map(function($item) {
            return [
                'code' => $item->code,
                'name' => $item->name,
                'type' => 'Isla',
                'island_name' => null
            ];
        });

        // Formateamos Municipios
        $municipalitiesFormatted = $municipalities->map(function($item) {
            return [
                'code' => $item->code,
                'name' => $item->name,
                'type' => 'Municipio',
                'island_name' => $item->island ? $item->island->name : null
            ];
        });

        // CORRECCIÓN AQUÍ: Usamos 'concat' en vez de 'merge' para evitar el error getKey()
        $results = $islandsFormatted->concat($municipalitiesFormatted);

        return response()->json([
            'query' => $text,
            'total_results' => $results->count(),
            'results' => $results->values() // .values() es importante para reindexar el array JSON
        ]);
    }
}