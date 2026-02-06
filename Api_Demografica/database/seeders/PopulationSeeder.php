<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Island;
use App\Models\Municipality;

class PopulationSeeder extends Seeder
{
    public function run(): void
    {
        // 1. CARGAR MAPAS
        $islandsMap = Island::pluck('id', 'name')->toArray();
        $municipalities = Municipality::all();
        $muniMap = [];
        foreach ($municipalities as $muni) {
            $muniMap[$muni->name] = [
                'id' => $muni->id,
                'island_id' => $muni->island_id
            ];
        }

        $csvFile = base_path('database/data/poblacion.csv');

        if (!file_exists($csvFile)) {
            $this->command->error('Fichero no encontrado: ' . $csvFile);
            return;
        }

        $this->command->info('Iniciando importación final...');

        $handle = fopen($csvFile, "r");
        fgetcsv($handle, 1000, ",");

        $batch = [];
        $batchSize = 1000;

        while (($data = fgetcsv($handle, 2000, ",")) !== FALSE) {
            if (count($data) < 15) continue;
            $medidaCode = $data[13]; 
            
            // FILTRO 1: Solo POBLACION
            if ($medidaCode !== 'POBLACION') continue;
            $territorioNombre = trim($data[0]);
            
            // FILTRO 2: Excluir "Canarias" y Nombres de Islas
            if ($territorioNombre === 'Canarias') continue;
            // Si el nombre corresponde a una isla, lo saltamos para evitar duplicidad con sus municipios
            if (isset($islandsMap[$territorioNombre])) continue; 

            $sexo = trim($data[4]);
            
            // FILTRO 3: Excluir Sexo "Total"
            if ($sexo === 'Total') continue; 

            $edadTexto = trim($data[6]);
            
            // FILTRO 4: Limpieza de rangos escapados
            if (str_contains($edadTexto, 'De ') || 
                $edadTexto === 'Total' || 
                $edadTexto === '65 años o más' || 
                $edadTexto === '85 años o más') {
                continue;
            }

            $anio = (int)$data[2];
            $valor = (float)$data[14]; 

            // Convertir edad a número ("100 años o más" -> 100)
            $edadNumero = (int) filter_var($edadTexto, FILTER_SANITIZE_NUMBER_INT);
            $municipalityId = null;
            $islandId = null;

            if (isset($muniMap[$territorioNombre])) {
                $municipalityId = $muniMap[$territorioNombre]['id'];
                $islandId = $muniMap[$territorioNombre]['island_id'];
            } else {
                continue; 
            }

            $batch[] = [
                'island_id' => $islandId,
                'municipality_id' => $municipalityId,
                'year' => $anio,
                'gender' => $sexo,
                'age' => $edadNumero,
                'population' => (int)$valor,
            ];

            if (count($batch) >= $batchSize) {
                DB::table('populations')->insertOrIgnore($batch);
                $batch = [];
                echo ".";
            }
        }

        if (!empty($batch)) {
            DB::table('populations')->insertOrIgnore($batch);
        }

        fclose($handle);
        $this->command->info("\n¡Importación completada! Deberías tener aprox 88.880 registros.");
    }
}